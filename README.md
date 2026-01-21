# RVScope

Aplicação de relatórios baseada em CodeIgniter 4 para importação e análise de CSVs do RVTools.

## Requisitos
- Docker Engine + Docker Compose
- Porta 8443 liberada (HTTPS)
- Porta 8080 opcional (HTTP)

## Estrutura
- `docker-compose.yaml` — serviços da aplicação e do Postgres
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
database.default.database=rvscope_db
database.default.username=rvscope
database.default.password=SUA_SENHA
database.default.DBDriver=Postgre
database.default.port=5432
```

## Subir a aplicação
```bash
docker compose build
docker compose up -d
```

## Migrations
```bash
docker compose exec app php /var/www/html/spark migrate
```

## Importação de CSVs
Coloque os arquivos em `imports/` com o padrão:
```
RVTools_ExportvInfo2csv_YYYY-MM-DD_HH.MM.SS.csv
```

Dispare a importação:
```bash
curl -k https://localhost:8443/import
```

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
