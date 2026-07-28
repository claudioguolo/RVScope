# Runbook de implantação e migração para produção

Este documento registra o processo validado em homologação para publicar uma
imagem autocontida do RVScope, aplicar as migrations e migrar o histórico dos
CSVs para o PostgreSQL.

## Estratégia recomendada para a primeira migração

Na produção atual existe um diretório sincronizado com o GitHub e o PostgreSQL
está em container. A transição para banco externo e OpenShift deve ser dividida
em fases independentes:

1. preparar e ensaiar a migração do PostgreSQL para o servidor separado;
2. fazer o corte do banco mantendo temporariamente o runtime atual;
3. atualizar o diretório atual para um commit imutável e validar a aplicação
   contra o banco novo;
4. construir uma imagem OCP a partir exatamente do commit validado;
5. homologar a imagem no OpenShift sem tráfego de produção;
6. promover o tráfego para o OCP e manter o runtime anterior disponível para
   retorno durante a janela definida.

Essa ordem reduz o número de variáveis alteradas simultaneamente. Se o corte do
banco falhar, o runtime ainda é conhecido. Se a implantação no OCP falhar, o
banco novo já foi validado pela aplicação no runtime anterior.

O diretório sincronizado não deve acompanhar automaticamente o ramo durante a
janela. Antes da mudança, fixe o commit aprovado:

```bash
git fetch --prune origin
RELEASE_COMMIT="$(git rev-parse origin/main)"
git checkout --detach "$RELEASE_COMMIT"
git rev-parse HEAD
```

Não use `git pull` periódico nem valide uma árvore que possa mudar durante os
testes. Se o diretório atual também serve a aplicação em produção, prefira
preparar outro checkout ou worktree para validar a release sem sobrescrever o
runtime ativo.

### Fase A — migrar o PostgreSQL

Antes do corte:

- levante versão, extensões, encoding, locale, tamanho e papéis do banco atual;
- confirme conectividade, DNS, firewall e TLS entre aplicação e banco novo;
- instale no destino uma versão PostgreSQL compatível;
- faça uma restauração de ensaio e meça a duração;
- valide tabelas, sequências, constraints, índices, contagens e permissões;
- teste a aplicação contra a cópia restaurada sem permitir escritas de
  produção.

No corte definitivo:

1. coloque a aplicação em manutenção ou bloqueie novas escritas;
2. confirme que não há importação ou backfill em andamento;
3. gere e valide o backup final;
4. restaure no servidor novo;
5. valide objetos, contagens e sequências;
6. altere somente a configuração de conexão da aplicação;
7. valide leitura e escrita controlada;
8. mantenha o banco antigo intacto e sem novas escritas até encerrar a janela.

Depois que houver escrita no banco novo, o retorno ao banco antigo exige
reconciliação ou perda das escritas posteriores. O plano de retorno deve
definir esse ponto explicitamente.

### Fase B — validar o commit no runtime atual

Atualize um checkout de release para o commit fixado, aplique as migrations uma
única vez e valide:

- início da aplicação e modo degradado;
- página principal, listagens e detalhes sob demanda;
- importação do CSV do dia;
- login local e AD;
- papéis Usuário, Editor e Administrador;
- SMTP;
- logs da aplicação e do PostgreSQL;
- desempenho e tempo das consultas principais.

Essa fase é uma ponte de validação. O diretório de código não deve continuar
como mecanismo definitivo de implantação depois da entrada no OCP.

### Fase C — adequar a imagem ao OpenShift

A imagem Docker atual ainda precisa de uma variante preparada e testada para
OCP. Antes da promoção:

- execute com UID arbitrário e sem privilégio de root;
- permita escrita apenas nos diretórios necessários usando permissões de grupo;
- não copie código nem altere permissões da árvore inteira no startup;
- injete configuração por `ConfigMap` e segredos por `Secret`, sem montar
  `.env` do host;
- use `imagePullSecret` para o Harbor e uma CA confiável;
- termine TLS na `Route` ou defina conscientemente o modo re-encrypt;
- disponibilize `writable` de forma compatível com múltiplas réplicas;
- não dependa do sistema de arquivos local do pod para sessões ou dados;
- disponibilize CSVs por PVC, objeto ou job de importação;
- implemente readiness, liveness e recursos de CPU/memória;
- execute migrations em um `Job` controlado antes do rollout, não em cada
  réplica da aplicação;
- teste rollout e rollback usando a mesma tag imutável.

O entrypoint atual restaura arquivos em `/var/www/html`, sincroniza código,
executa `chown/chmod` e dispara migrations. Esses comportamentos funcionam no
Compose atual, mas devem ser separados para OCP: a imagem deve chegar pronta,
o pod web deve apenas iniciar a aplicação e a migration deve ser uma etapa
única da release.

## Premissas

- O código é enviado ao Gitea, e o Gitea Actions gera a imagem.
- A imagem é armazenada no Harbor com uma tag imutável igual aos 12 primeiros
  caracteres do commit.
- O PostgreSQL tem ciclo de vida independente da aplicação.
- A aplicação não monta o código-fonte por volume em produção.
- `.env`, certificados, credenciais e CSVs não são armazenados no Git.
- O diretório de CSVs é montado em `/app/arquivos_csv` somente para leitura.
- A tag configurada em `RVSCOPE_IMAGE_TAG` deve ser exatamente a tag da imagem
  executada.
- Os comandos abaixo devem ser adaptados para os nomes DNS, diretórios e
  registros do Harbor de produção.

## 1. Preparação antes da mudança

Registre:

- commit aprovado;
- tag imutável esperada;
- tag atualmente em execução;
- responsável e janela da mudança;
- localização e política de retenção do backup;
- parâmetros de conexão do banco, fornecidos por arquivo protegido ou cofre;
- espaço livre no servidor da aplicação e no banco.

Confirme que o repositório está limpo e obtenha a tag:

```bash
git status --short
git fetch gitea
NEW_TAG="$(git rev-parse --short=12 gitea/main)"
printf '%s\n' "$NEW_TAG"
```

O `git status --short` não deve apresentar alterações locais inesperadas.

## 2. Validar o artefato no Harbor

Não use `latest`, `homolog` ou outra tag mutável para a promoção. Verifique a
tag imutável publicada pelo pipeline:

```bash
docker manifest inspect --insecure \
  "${HARBOR_REGISTRY}/rvscope/rvscope:${NEW_TAG}" >/dev/null &&
  echo "Imagem disponível no Harbor"
```

Em produção com TLS válido, remova `--insecure`. Se o comando falhar, não
continue: verifique o pipeline e o certificado do Harbor.

## 3. Backup do PostgreSQL

O backup deve terminar com código zero e gerar um arquivo não vazio. Não use
uma tubulação sem `pipefail`, pois ela pode criar um `.gz` vazio mesmo quando
`pg_dump` falha.

Quando o PostgreSQL ainda estiver em um container local:

```bash
mkdir -p backups
set -o pipefail
BACKUP="backups/rvscope_$(date +%F_%H%M%S).sql.gz"

docker exec rvscope_db \
  sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' |
  gzip > "$BACKUP"

test -s "$BACKUP"
gzip -t "$BACKUP"
echo "Backup validado: $BACKUP"
```

Quando o PostgreSQL estiver em uma máquina física, execute `pg_dump` por uma
conexão protegida. A senha deve vir de `.pgpass`, `PGPASSFILE` protegido ou do
cofre de segredos, nunca do histórico do shell:

```bash
set -o pipefail
BACKUP="backups/rvscope_$(date +%F_%H%M%S).sql.gz"

pg_dump --host "$DB_HOST" --username "$DB_USER" --dbname "$DB_NAME" |
  gzip > "$BACKUP"

test -s "$BACKUP"
gzip -t "$BACKUP"
```

O procedimento de restauração deve ter sido testado previamente em um banco
isolado.

## 4. Atualizar a configuração versionada

No servidor da aplicação:

```bash
cd /caminho/de/producao/rvscope
git pull --ff-only gitea main
```

Confirme que o commit recebido corresponde ao artefato:

```bash
test "$(git rev-parse --short=12 HEAD)" = "$NEW_TAG"
```

## 5. Selecionar e implantar a imagem

Atualize somente a chave `RVSCOPE_IMAGE_TAG` do `.env`:

```bash
if grep -q '^RVSCOPE_IMAGE_TAG=' .env; then
  sed -i "s/^RVSCOPE_IMAGE_TAG=.*/RVSCOPE_IMAGE_TAG=$NEW_TAG/" .env
else
  printf '\nRVSCOPE_IMAGE_TAG=%s\n' "$NEW_TAG" >> .env
fi
```

Valide a configuração efetiva antes de recriar o serviço:

```bash
docker compose config --quiet
docker compose config --images
```

O segundo comando deve mostrar exclusivamente a tag esperada para o serviço
`app`. Faça o pull e recrie somente a aplicação:

```bash
docker compose pull app
docker compose up -d --force-recreate --no-build app
```

Alternativamente, use o script de implantação, configurando o diretório e a
URL de verificação para produção:

```bash
RVSCOPE_PROJECT_DIR=/caminho/de/producao/rvscope \
RVSCOPE_HEALTH_URL=https://rvscope.exemplo/ \
./scripts/deploy-homolog.sh "$NEW_TAG"
```

## 6. Verificações após a implantação

Confirme container, imagem, ambiente e resposta HTTP:

```bash
docker compose ps app
docker inspect rvscope_app \
  --format 'Imagem={{.Config.Image}} ID={{.Image}}'
docker inspect rvscope_app \
  --format '{{range .Config.Env}}{{println .}}{{end}}' |
  grep -E '^(CI_ENVIRONMENT|APP_STAGE|APP_IMAGE_TAG)='
curl --fail --silent --show-error --insecure \
  --output /dev/null https://127.0.0.1:8443/
```

Em produção, `CI_ENVIRONMENT` deve ser `production`. Ajuste `APP_STAGE` ao
rótulo aprovado para o ambiente e use TLS válido no acesso externo.

Confira os logs sem expor variáveis ou credenciais:

```bash
docker compose logs --tail=150 app
```

## 7. Validar as migrations e o comando

O entrypoint executa migrations quando o banco está disponível. Confira o
resultado:

```bash
docker compose exec app php spark migrate:status
```

A migration `ExpandRvtoolsInventorySnapshots` deve constar como aplicada.
Confirme também que o comando foi empacotado na imagem:

```bash
docker compose exec app php spark list |
  grep rvscope:backfill-csv
```

## 8. Analisar o histórico sem alterar dados

Monte os CSVs históricos em `/app/arquivos_csv` como somente leitura e execute:

```bash
docker compose exec app php spark rvscope:backfill-csv \
  --path /app/arquivos_csv
```

Registre:

- quantidade total de arquivos;
- quantidade de datas selecionadas;
- arquivos incompatíveis;
- motivo de cada incompatibilidade.

O comando seleciona o arquivo de nome mais recente para cada data. Sem
`--execute`, nenhum dado é alterado.

## 9. Testar uma única data

Antes do histórico completo, copie um arquivo compatível para um diretório
temporário dentro do container:

```bash
docker compose exec app mkdir -p /tmp/rvscope-backfill-test
docker compose exec app sh -c \
  'cp /app/arquivos_csv/ARQUIVO_DE_TESTE.csv /tmp/rvscope-backfill-test/'
```

Execute o teste:

```bash
docker compose exec app php spark rvscope:backfill-csv \
  --path /tmp/rvscope-backfill-test \
  --execute \
  --confirm BACKFILL
```

Só prossiga se o resultado informar `[1/1] OK` e `Datas processadas: 1`.

O problema encontrado em homologação foi o envio de `0/1` para a coluna
PostgreSQL `included_in_reports`, que é `BOOLEAN`. A imagem corrigida deve
conter:

```bash
docker compose exec app grep -n \
  "'included_in_reports' => \$included," \
  /var/www/html/app/Libraries/RvtoolsImporter.php
```

## 10. Executar o backfill completo

Para uma execução longa, use `tmux` ou o mecanismo de jobs adotado pela
operação:

```bash
tmux new -s rvscope-backfill
```

Dentro da sessão:

```bash
docker compose exec app php spark rvscope:backfill-csv \
  --path /app/arquivos_csv \
  --execute \
  --confirm BACKFILL
```

Para desacoplar sem interromper o processo, pressione `Ctrl+B` e depois `D`.
Para retornar:

```bash
tmux attach -t rvscope-backfill
```

Cada data é processada em uma transação independente. Uma falha reverte a data
afetada e preserva as demais. O comando também ignora snapshots completos do
mesmo arquivo, permitindo retomada segura.

Se houver erro, interrompa a execução completa e identifique primeiro o erro
original do PostgreSQL:

```bash
docker logs rvscope_db --since 5m 2>&1 |
  grep 'ERROR:' |
  head -20
```

Mensagens `current transaction is aborted` são consequências; o primeiro erro
é a causa.

## 11. Validação funcional e de dados

Após o backfill:

- acesse a tela principal e confira os totais;
- abra listagens de hosts;
- abra detalhes de hosts de datas diferentes;
- valide filtros e exportações;
- confirme que uma data histórica não depende mais da presença do CSV;
- valide login e permissões de Usuário, Editor e Administrador;
- confira que configurações administrativas permanecem restritas;
- acompanhe logs da aplicação e do PostgreSQL.

Consultas de conferência podem ser executadas dentro do PostgreSQL:

```sql
SELECT COUNT(*) AS snapshots
FROM rvtools_vm_inventory;

SELECT MIN(reference_date) AS primeira_data,
       MAX(reference_date) AS ultima_data,
       COUNT(DISTINCT reference_date) AS datas
FROM rvtools_vm_inventory;

SELECT reference_date,
       COUNT(*) AS linhas,
       COUNT(*) FILTER (WHERE included_in_reports) AS linhas_relatorios
FROM rvtools_vm_inventory
GROUP BY reference_date
ORDER BY reference_date DESC
LIMIT 10;
```

Compare amostras com os CSVs de origem e com os totais exibidos antes de
encerrar a mudança.

## 12. Operação diária após a migração

Em produção não é necessário manter todo o histórico disponível para a
aplicação. O processo diário deve:

1. baixar somente o CSV do dia para `imports/`;
2. validar nome, integridade e colunas obrigatórias;
3. disparar a importação;
4. confirmar o registro em `rvtools_import_log`;
5. confirmar o snapshot da data em `rvtools_vm_inventory`;
6. arquivar ou remover o CSV conforme a política de retenção e auditoria.

A página principal consulta os resumos. Listagens, detalhes e exportações
consultam o inventário no PostgreSQL sob demanda.

## 13. Retorno e recuperação

Antes da mudança, registre `PREVIOUS_TAG`:

```bash
PREVIOUS_TAG="$(
  awk -F= '/^RVSCOPE_IMAGE_TAG=/{print $2; exit}' .env
)"
```

Para retornar somente a aplicação:

```bash
sed -i "s/^RVSCOPE_IMAGE_TAG=.*/RVSCOPE_IMAGE_TAG=$PREVIOUS_TAG/" .env
docker compose pull app
docker compose up -d --force-recreate --no-build app
```

Não reverta migrations automaticamente. Se houver corrupção ou necessidade de
retornar os dados, interrompa as escritas, preserve evidências e siga o
procedimento aprovado de restauração do backup em um banco isolado ou na
instância de produção.

## Critérios de conclusão

- tag imutável aprovada e confirmada no container;
- aplicação respondendo por HTTPS;
- ambiente efetivo correto;
- migrations aplicadas;
- teste de uma data concluído;
- backfill concluído ou incompatibilidades documentadas;
- amostras de relatórios, hosts e detalhes conferidas;
- autenticação e autorização conferidas;
- logs sem erros novos;
- backup identificado e retido;
- registro da mudança atualizado com tag, horários e resultados.
