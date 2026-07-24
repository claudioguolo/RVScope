# RVScope

Aplicação de relatórios baseada em CodeIgniter 4 para importação e análise de CSVs do RVTools.

## Requisitos
- Docker Engine + Docker Compose
- Porta 8443 liberada (HTTPS)
- Porta 8080 opcional (HTTP)

## Estrutura
- `docker-compose.yaml` — homologação com imagem autocontida do Harbor
- `docker-compose.dev.yaml` — arquivo local ignorado pelo Git, usado no Mac para desenvolvimento
- `docker/php/` — Dockerfile e configs do Apache (HTTP/HTTPS)
- `app/` — código da aplicação
- `imports/` — diretório de CSVs a serem importados
- `certs/` — certificados SSL (produção/dev)

## Configuração
Crie o arquivo `.env` na raiz do projeto (exemplo):

```ini
CI_ENVIRONMENT=production
APP_STAGE=homologation
app.baseURL="https://rvscope.local:8443/"

database.default.hostname=192.168.0.51
database.default.database=rvscope
database.default.username=rvscope
database.default.password=SUA_SENHA
database.default.DBDriver=Postgre
database.default.port=5432

POSTGRES_DB=rvscope
POSTGRES_USER=rvscope
POSTGRES_PASSWORD=SUA_SENHA
POSTGRES_HOST=192.168.0.51
POSTGRES_PORT=5432

# Protecao de operacoes sensiveis (save_info e /import)
security.adminUser=admin
security.adminPassword=troque-esta-senha

# Admin inicial do espaco administrativo (se omitido, reaproveita as credenciais acima)
security.bootstrapAdminUser=admin
security.bootstrapAdminPassword=troque-esta-senha
security.bootstrapAdminName=Administrador inicial
```

Em homologação, use `CI_ENVIRONMENT=production` e `APP_STAGE=homologation`.
O CI4 mantém integralmente o comportamento padrão e seguro de produção,
enquanto o RVScope apresenta uma identificação visual discreta. O Compose
também expõe `RVSCOPE_IMAGE_TAG` como `APP_IMAGE_TAG` para mostrar no cabeçalho
a versão exata da imagem em execução.

Em desenvolvimento, use `CI_ENVIRONMENT=development`. Nesse modo o CodeIgniter
exibe a Debug Toolbar e o RVScope apresenta a identificação correspondente.
Nunca habilite `development` em homologação ou produção, pois detalhes internos
podem ser exibidos no navegador.

O atalho de engrenagem na página inicial usa essas mesmas credenciais para liberar o acesso à tela administrativa de login.

No primeiro acesso ao login administrativo, se ainda não houver usuários na tabela, a aplicação cria automaticamente o admin inicial com essas credenciais.

Na tela **Administração > Usuários**, a opção **Acesso autenticado aos
relatórios** permite exigir uma sessão administrativa para acessar a página
inicial e todas as rotas `/reports`. A opção começa desabilitada para preservar
o acesso público atual. Quando habilitada, visitantes são encaminhados ao login
e retornam ao relatório solicitado depois da autenticação.

Na mesma tela, a integração com o **Active Directory via LDAPS** pode ser
habilitada com o host do controlador, porta (normalmente `636`) e domínio UPN.
O bind usa a própria senha do usuário; nenhuma senha de serviço é armazenada.
Usuários do AD recebem acesso somente aos relatórios, nunca à administração.
A validação do certificado TLS é obrigatória. Se o domínio usar uma CA interna,
salve sua cadeia pública em `certs/ad-ca.crt`; esse diretório já é montado na
imagem em `/etc/ssl/private`.

## Subir em desenvolvimento
```bash
docker compose -f docker-compose.dev.yaml up -d --build
```

## Subir em homologação
```bash
docker compose pull app
docker compose up -d --no-build
```

## Banco de homologação independente

O PostgreSQL possui ciclo de vida separado da aplicação, no diretório irmão
`/home/claudio/Docker/database`. Antes do primeiro uso, crie o `.env` próprio
da stack e confirme o nome do volume existente:

```bash
cd /home/claudio/Docker/database
cp .env.example .env
chmod 600 .env
docker volume ls | grep rvscope
docker compose up -d
```

## Migrations
```bash
docker compose exec app php /var/www/html/spark migrate
```

## Atualização em homologação (Gitea -> Harbor -> Servidor)
Diretório de homologação: `/home/claudio/Docker/rvscope`

### 1. Acessar o servidor e entrar na pasta da aplicação
```bash
ssh claudio@192.168.0.51
cd /home/claudio/Docker/rvscope
```

### 2. Gerar backup do banco de dados
```bash
mkdir -p backups
set -o pipefail
BACKUP="backups/rvscope_$(date +%F_%H%M%S).sql.gz"
docker exec rvscope_db pg_dump -U rvscope rvscope | gzip > "$BACKUP"
```

Validar o conteúdo:
```bash
gzip -dc "$BACKUP" | sed -n '1,15p'
```

### 3. Baixar a configuração do Gitea
```bash
git pull --ff-only gitea main
```

### 4. Baixar e aplicar a imagem do Harbor
```bash
docker compose pull app
docker compose up -d --no-build
```

### 5. Validar o deploy
```bash
docker compose ps
docker compose logs -f --tail=100 app
```

### 6. Validar migrations (se necessário)
O `entrypoint` do container `app` já executa migrations na inicialização.

Para conferir status manualmente:
```bash
docker compose exec app php /var/www/html/spark migrate:status
```

Para forçar execução:
```bash
docker compose exec app php /var/www/html/spark migrate --all
```

## Importação de CSVs
Coloque os arquivos em `imports/` com o padrão:
```
RVTools_ExportvInfo2csv_YYYY-MM-DD_HH.MM.SS.csv
```

Dispare a importação:
```bash
curl -k -u "admin:troque-esta-senha" -X POST https://localhost:8443/index.php/import
```

Para salvar alteracoes nas telas de detalhe, o navegador solicitara autenticacao HTTP Basic
com `security.adminUser` e `security.adminPassword`.

## SSL (HTTPS 8443)
Gere certificado local (dev):

```bash
mkdir -p certs
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout certs/server.key \
  -out certs/server.crt \
  -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
  -addext "basicConstraints=CA:FALSE"
```

Depois:
```bash
docker compose up -d
```

## Remover containers
```bash
docker compose down
```

## Notas
- `imports/` está fora do `app/` por convenção de dados.
- `.env`, `certs/` e `*.db` não são versionados (ver `.gitignore`).
- `docker compose down` na raiz afeta somente a aplicação e não para o PostgreSQL.
- O volume do banco é administrado exclusivamente pela stack irmã em
  `/home/claudio/Docker/database`.
