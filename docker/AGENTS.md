# RVScope — Regras de Docker

Estas regras complementam o `AGENTS.md` da raiz para arquivos em `docker/`.

## Imagem

- A imagem de homologação deve ser autocontida e incluir todo o código em
  `/srv/app`.
- O desenvolvimento pode sobrescrever `/srv/app` por bind mount.
- Novos arquivos e subdiretórios de `app/` devem ser copiados corretamente pelo
  entrypoint.
- Não incluir `.env`, certificados, imports, dados ou diretórios `writable` na
  imagem.
- Fixar a versão principal das imagens base quando possível e evitar tags
  inesperadamente mutáveis em produção.

## Entrypoint

- Sincronize o código antes de verificar o banco.
- Indisponibilidade do banco não deve encerrar o Apache.
- Execute migrations somente quando a conexão ao banco estiver disponível.
- Nunca crie automaticamente o banco externo.
- Não imprima credenciais ou variáveis sensíveis.
- Mudanças no entrypoint exigem `bash -n docker/php/entrypoint.sh`.

## Dependências e TLS

- Instale apenas pacotes necessários e remova o cache do gerenciador.
- Extensões PHP devem ser compiladas durante o build e verificadas com
  `php -m`.
- HTTPS da aplicação usa os certificados montados em `/etc/ssl/private`.
- LDAPS deve exigir certificado válido; não usar `LDAP_OPT_X_TLS_NEVER`.
- Uma CA interna do AD pode ser montada como
  `/etc/ssl/private/ad-ca.crt`.

## Validação

- Execute build completo depois de mudar o Dockerfile.
- Execute lint PHP dentro da imagem.
- Valide que Apache inicia com os certificados locais ignorados pelo Git.
- Use conexões deliberadamente inválidas para testar modo degradado.
- Não inicie PostgreSQL no Mac.
- Limpe containers e redes temporários ao finalizar.
