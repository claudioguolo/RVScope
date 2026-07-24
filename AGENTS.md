# RVScope — Instruções permanentes

Estas regras se aplicam a todo o repositório. Instruções em `AGENTS.md`
localizados em subdiretórios complementam estas regras para o respectivo
escopo.

## Arquitetura e ambientes

- A aplicação usa PHP 8.2, CodeIgniter 4, Apache e PostgreSQL.
- Não altere arquivos internos do framework ou de `vendor/`. Implemente
  extensões e configurações somente no código da aplicação.
- O PostgreSQL é um serviço separado da aplicação.
- Não inicie um servidor PostgreSQL no Mac.
- O desenvolvimento usa `docker-compose.dev.yaml`, código montado por volume e
  `CI_ENVIRONMENT=development`.
- `docker-compose.dev.yaml` e `.env` são locais e devem permanecer ignorados
  pelo Git.
- A homologação usa imagem autocontida do Harbor, `CI_ENVIRONMENT=production` e
  `APP_STAGE=homologation`.
- A tag exibida pela aplicação deve corresponder à imagem realmente executada.

## Banco de dados

- Toda alteração de estrutura deve ser implementada por migration do
  CodeIgniter.
- Migrations devem ser idempotentes quando aplicável e preservar dados
  existentes.
- A aplicação deve continuar iniciando em modo degradado quando o banco estiver
  indisponível.
- O banco de `192.168.0.51` pode ser usado para validações de leitura.
- Não execute migrations, inserts, updates, deletes ou testes destrutivos no
  banco remoto sem autorização explícita.
- Ao simular indisponibilidade, use uma conexão deliberadamente inválida; não
  interrompa o banco compartilhado.

## Segurança

- Nunca versione `.env`, senhas, tokens, chaves privadas ou certificados
  privados.
- Nunca mostre senhas ou hashes completos em logs e saídas de diagnóstico.
- Formulários que alteram estado devem usar CSRF.
- Senhas locais devem ser armazenadas com `password_hash`.
- Credenciais reversíveis, como senha SMTP, devem usar criptografia autenticada
  e chave mantida fora do Git.
- LDAPS deve validar o certificado TLS. Nunca desabilite a validação para fazer
  um teste passar.
- Usuários autenticados pelo AD podem acessar relatórios, mas não recebem
  privilégios administrativos.
- Redirecionamentos após login devem aceitar apenas destinos internos gerados
  pela aplicação.

## Comportamento e interface

- Preserve o tema visual do RVScope em páginas novas e estados de erro.
- Em desenvolvimento, exiba `Ambiente de Desenvolvimento`.
- Em homologação, exiba `Ambiente de Homologação` e a tag da imagem.
- Recursos de debug do CodeIgniter devem existir somente em `development`.
- Novas configurações operacionais devem ficar na área administrativa e
  possuir mensagens claras de sucesso e erro.
- Preserve e não altere ou suprima, itens visuais, funções, recursos e posição de layout sem que seja solicitado. 

## Verificações obrigatórias

Antes de declarar uma alteração concluída:

1. Execute `git diff --check`.
2. Execute lint em todos os arquivos PHP alterados.
3. Execute `docker compose config --quiet` para Compose versionado.
4. Recompile a imagem se o Dockerfile, extensões PHP ou dependências mudarem.
5. Confira `php spark routes` quando rotas ou filtros forem alterados.
6. Faça testes funcionais proporcionais ao risco, sem criar PostgreSQL local.
7. Diferencie claramente testes executados de testes que dependem de serviços
   externos ainda não configurados.
8. Remova containers e redes criados exclusivamente para validação.

## Git e publicação

- Preserve alterações existentes do usuário e não reverta trabalho não
  relacionado.
- Não versione arquivos locais ignorados.
- Use mensagens de commit objetivas em português.
- Não faça push ou implantação externa sem que isso esteja dentro da solicitação
  do usuário.
- O pipeline do Gitea deve construir a imagem autocontida e publicá-la no
  Harbor com uma tag imutável baseada no commit.
- Na homologação, confirme a imagem e as variáveis efetivas após recriar o
  container.
