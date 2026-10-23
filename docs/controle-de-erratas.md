# Controle de Erratas — RVScope 1.4.0

O menu **Controle de Erratas** contém **Erratas por Dia** e **Erratas com Host**.
As consultas seguem a configuração de acesso autenticado dos demais relatórios.
Somente administradores podem importar históricos em **Usuários e configurações
→ Dados de erratas** (`/admin/erratas`). Os formulários de importação usam CSRF.

## Arquivo diário existente

Importe o arquivo `erratas.txt` existente selecionando **Erratas por Dia (.txt)**.
Não é necessário mudar seu formato:

```text
19-09-202610:00 - 242 bugfix ; 4 enhancement ; 377 security ; 2 unspecified ;
23-10-202610:00 - 260 bugfix ; 5 enhancement ; 469 security ; 3 unspecified ;
```

O primeiro registro contém 625 erratas; o segundo, **737**. Cada item contém uma
quantidade, portanto contar apenas os itens separados por `;` produziria um
resultado incorreto. O parser valida os números e as datas e converte a data
internamente para `YYYY-MM-DD`. A interface exibe `DD/MM/YYYY` e horários UTC,
preservando a origem em `date -u` do cron do Satellite.

Também são aceitas datas ISO com horário, contagens `security: 12` e listas de
identificadores RHSA/RHBA/RHEA. Dentro de uma coleta, identificadores repetidos
são contados uma vez. Uma importação com registros inválidos é recusada inteira
e preserva o histórico anterior; um arquivo alterado externamente que contenha
linhas inválidas gera um aviso na consulta.

A página diária apresenta os totais da última coleta no período, gráfico por
tipo e acordeão por data, da mais recente para a mais antiga. Se houver mais
de uma coleta no dia, a última fornece o saldo exibido. As anteriores continuam
disponíveis no acordeão. Não se somam snapshots de horários ou dias diferentes,
pois uma mesma errata pode continuar instalável em várias coletas. A consulta
oferece filtro por período e paginação de até 50 dias; o gráfico mostra até os
90 dias mais recentes com coleta nesse período, inclusive quando os totais são zero.

Esses números são os totais da organização informados pelo Satellite. O TXT
agregado não identifica hosts e não permite aplicar os filtros de SO ou estado
das VMs aos totais diários.

## Coleta automática do cron

O recebimento diário usa **POST `/api/erratas/por-dia`**. Requisições GET não
alteram o histórico. Os campos continuam sendo `data` e `dados`, enviados como
formulário. A autenticação usa `Authorization: Bearer`, com um token exclusivo
para erratas, diferente do token da importação RVTools.

No `.env` local do RVScope, configure uma sequência aleatória de pelo menos
32 caracteres:

```dotenv
security.errataImportToken=SUBSTITUA_POR_TOKEN_ALEATORIO_EXCLUSIVO
```

Também é aceita a variável de ambiente `RVSCOPE_ERRATA_IMPORT_TOKEN`. A área
administrativa informa se a configuração está presente, sem mostrar o token.
Sem token configurado a API retorna 503; token ausente ou inválido retorna 401;
dados inválidos retornam 422. A API não usa sessão nem CSRF, pois exige Bearer.
Suas requisições são excluídas da Debug Toolbar, inclusive em desenvolvimento.

No Satellite, configure as variáveis em um arquivo protegido, disponível no
ambiente do cron, e execute [scripts/send_satellite_errata.sh](../scripts/send_satellite_errata.sh):

```bash
export RVSCOPE_ERRATA_URL='https://rvscope.exemplo.org/api/erratas/por-dia'
export SATELLITE_ORGANIZATION='TJMT'
# RVSCOPE_ERRATA_IMPORT_TOKEN deve vir de um arquivo/segredo protegido,
# com o mesmo valor configurado no RVScope. Não o inclua no repositório.
bash /caminho/send_satellite_errata.sh
```

O script substitui o `urlencode` manual por `curl --data-urlencode`, envia os
campos por POST e mantém o token em um arquivo temporário com permissões
restritas, removido ao concluir. A coleta usa a coluna TYPE do relatório Hammer
já utilizado. Falha do Hammer ou ausência desse cabeçalho impede o envio, em
vez de registrar um falso zero. Uma listagem vazia válida gera zeros explícitos.

O script solicita `--full-result true` para não contar apenas a primeira página.
Confira o suporte com `hammer erratum list --help` na versão instalada. A chamada
consulta somente erratas instaláveis; não aplica erratas. A Red Hat documenta
a diferença entre erratas aplicáveis e instaláveis e o filtro por host:
[referência do Hammer](https://docs.redhat.com/en/documentation/red_hat_satellite/6.19/html/hammer_reference/hammer-erratum).

O HTTPS do coletor continua sendo validado. Se o RVScope usar CA interna,
configure `RVSCOPE_HTTPS_CA_FILE` com o arquivo público da CA confiável para a
URL do RVScope. Essa confiança HTTPS é independente da opção `smtp.caFile`.

O horário faz parte da chave da coleta. Reenviar o mesmo horário atualiza o
registro sem duplicá-lo. Uma nova coleta no mesmo dia preserva a anterior.
Os retries automáticos do script reutilizam o mesmo horário e payload.

## Erratas com Host

O relatório já oferece filtro parcial por nome da VM, nome do host no Satellite,
hostname/DNS e IP, sem distinguir maiúsculas de minúsculas, como **Buscar VM**.
Consulta a última coleta de cada host na data selecionada e apresenta quantidades
por tipo, total, horário e o botão **Detalhes / Editar** conforme o papel do usuário.
Os campos importados do CSV continuam inativos no modal de detalhes da VM.

O TXT atual não contém essa relação. Para alimentar a consulta, gere uma coleta
por host no Satellite e envie um CSV UTF-8, com `;` ou `,`, por importação manual
ou **POST `/api/erratas/com-host`** (`Content-Type: text/csv`, corpo CSV e o mesmo
Bearer token exclusivo de erratas). Exemplo de contagens por host:

```csv
collected_at;host;hostname;ip;security;bugfix;enhancement;unspecified
2026-10-23 10:00:00;dsa-vra-0001;dsa-vra-0001.exemplo.org;192.0.2.10;12;4;1;0
2026-10-23 10:00:00;vra-DSA-0002;vra-dsa-0002.exemplo.org;192.0.2.11;3;2;0;1
```

- `collected_at`: data/hora UTC. Também aceita o formato de data do TXT.
- Pelo menos um identificador não vazio: `host`, `hostname` ou `ip`.
- Quantidades inteiras não negativas por tipo. A coluna opcional `total`, quando
  acompanhada de tipos, deve corresponder à soma deles. Se houver somente total,
  a quantidade é exibida como não especificada.
- Alternativamente, envie `erratum_id` com um identificador RHSA/RHBA/RHEA por
  linha, sem preencher as colunas de contagens nessa linha. A aplicação conta os
  identificadores únicos por host e horário; não misture contagens agregadas e
  identificadores no mesmo host e horário.

O vínculo com o RVTools é exato por nome completo da VM, hostname/DNS ou IP na
mesma data; ignora diferenças de caixa e ponto final do DNS. O filtro de busca
aceita trechos, mas a associação entre os sistemas não usa correspondência parcial.
Sinais que apontem para VMs diferentes, como IP reutilizado, ficam sem vínculo
automático. Não há fallback para um inventário de outra data.

Só aparecem VMs `poweredOn` com `included_in_reports = TRUE`, respeitando os SOs
selecionados no RVScope. O índice considera também as VMs excluídas para detectar
ambiguidades. A tela informa quantidades de registros sem vínculo, ambíguos ou
excluídos e pagina até 50 hosts. Uma mesma errata pode afetar vários hosts;
somar as contagens dos hosts não equivale ao total de erratas distintas da organização.

O script fornecido nesta versão coleta o total diário da organização. A extração
real por host ainda depende da coleta específica do Satellite; a interface e o
contrato CSV já estão disponíveis, sem inventar dados a partir do TXT agregado.

## Persistência e validação

Os históricos ficam em `writable/erratas/erratas.txt` e
`writable/erratas/erratas_hosts.csv`, fora do diretório público e do Git, no volume
persistente existente. A escrita usa bloqueio e substituição atômica, preserva
outras datas e hosts e não executa SQL de alteração. O diretório precisa ser
gravável pelo usuário do PHP. Não há mudança de estrutura nem migration nova.

Arquivos e históricos estão limitados a 10 MiB e 50 mil registros por fonte;
limites menores do PHP/Apache para upload também se aplicam. Não há truncamento
silencioso: uma importação que ultrapasse o limite é recusada e mantém o arquivo
anterior. Para volumes maiores por host, a persistência deverá evoluir para tabelas
próprias por migration, mantendo esse contrato de coleta e os relatórios.
Não edite os arquivos diretamente enquanto houver coleta ou importação em andamento.

Os testes de parser incluem o formato real fornecido, totais, datas, ordenação,
reenvios e preservação de históricos. Os testes de associação cobrem busca parcial,
ambiguidades, filtros de VM/SO, paginação e identificadores repetidos.
Use `composer test` para executar as suítes. A validação funcional usa SQLite
temporário e servidor HTTP isolado, sem PostgreSQL ou acesso ao Satellite real.
