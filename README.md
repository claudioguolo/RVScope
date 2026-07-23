# RVScope

Aplicação de relatórios baseada em CodeIgniter 4 para importação e análise de CSVs do RVTools.

## Requisitos
- Docker Engine + Docker Compose
- Porta 8443 liberada (HTTPS)
- Porta 8080 opcional (HTTP)

## Estrutura
- `docker-compose.yaml` — homologação com imagem autocontida do Harbor
- `docker-compose.dev.yaml` — desenvolvimento com build local e bind mount de `./app`
- `docker/php/` — Dockerfile e configs do Apache (HTTP/HTTPS)
- `app/` — código da aplicação
- `imports/` — diretório de CSVs a serem importados
- `certs/` — certificados SSL (produção/dev)

## Configuração
Crie o arquivo `.env` na raiz do projeto (exemplo):

```ini
CI_ENVIRONMENT=production
app.baseURL="https://rvscope.local:8443/"

database.default.hostname=db
database.default.database=rvscope
database.default.username=rvscope
database.default.password=SUA_SENHA
database.default.DBDriver=Postgre
database.default.port=5432

POSTGRES_DB=rvscope
POSTGRES_USER=rvscope
POSTGRES_PASSWORD=SUA_SENHA

# Protecao de operacoes sensiveis (save_info e /import)
security.adminUser=admin
security.adminPassword=troque-esta-senha

# Admin inicial do espaco administrativo (se omitido, reaproveita as credenciais acima)
security.bootstrapAdminUser=admin
security.bootstrapAdminPassword=troque-esta-senha
security.bootstrapAdminName=Administrador inicial
```

O atalho de engrenagem na página inicial usa essas mesmas credenciais para liberar o acesso à tela administrativa de login.

No primeiro acesso ao login administrativo, se ainda não houver usuários na tabela, a aplicação cria automaticamente o admin inicial com essas credenciais.

## Subir em desenvolvimento
```bash
docker compose -f docker-compose.dev.yaml up -d --build
```

## Subir em homologação
```bash
docker compose pull app
docker compose up -d --no-build
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
docker compose exec -T db pg_dump -U rvscope rvscope | gzip > "$BACKUP"
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

## Limpar volumes (apaga banco)
```bash
docker compose down -v
```

## Notas
- `imports/` está fora do `app/` por convenção de dados.
- `.env`, `certs/` e `*.db` não são versionados (ver `.gitignore`).
