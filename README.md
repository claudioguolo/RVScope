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

## Atualização em Produção (GitHub -> Servidor)
Diretório de produção: `/dados/sistemas/rvscope`

### 1. Acessar o servidor e entrar na pasta da aplicação
```bash
ssh <usuario>@<servidor>
cd /dados/sistemas/rvscope
```

### 2. Gerar backup do banco de dados
Comando utilizado em produção:
```bash
docker compose exec -T db pg_dump -U rvscope rvscope_db | gzip > ../backups/rvscope_$(date +%F_%H%M%S).sql.gz
```

Opcional: validar se o backup foi criado:
```bash
ls -lh ../backups | tail -n 5
```

### 3. Baixar as atualizações do GitHub
```bash
git fetch --all
git checkout main
git pull origin main
```

### 4. Aplicar atualização da aplicação
Para mudanças apenas de código PHP/views/routes:
```bash
docker compose restart app
```

Se houver mudança em `Dockerfile` ou `docker-compose.yaml`:
```bash
docker compose up -d --build app
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
