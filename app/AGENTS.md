# RVScope — Regras da aplicação

Estas regras complementam o `AGENTS.md` da raiz para arquivos em `app/`.

## Organização

- Controllers coordenam requisições; regras reutilizáveis devem ficar em
  Models, Libraries ou Filters.
- Não copie nem modifique classes internas do CodeIgniter.
- Mantenha rotas explícitas; o autoroute deve continuar desabilitado.
- Novas tabelas e colunas exigem migration em `app/Database/Migrations`.
- Models devem limitar `allowedFields` e não aceitar campos arbitrários.

## Autenticação e autorização

- Mantenha separados:
  - acesso administrativo local;
  - sessão autenticada para relatórios;
  - autenticação pelo Active Directory.
- Nunca use apenas a presença de um nome de usuário para conceder autorização.
- Sessões AD devem usar perfil não administrativo.
- Rotas administrativas devem validar uma sessão administrativa local.
- Rotas protegidas de relatórios devem retornar o usuário à URL interna
  originalmente solicitada depois do login.
- Logout deve remover todos os campos de sessão pertencentes ao fluxo
  correspondente.
- Regenerar o identificador de sessão depois de autenticar.

## Active Directory

- Usar somente LDAPS com validação obrigatória do certificado.
- O bind deve usar a senha fornecida pelo próprio usuário; não armazená-la.
- Não registrar DN, usuário e erro bruto juntos se isso puder expor informação
  sensível.
- A CA interna opcional deve ser lida de
  `/etc/ssl/private/ad-ca.crt`.
- Falha do AD não pode conceder acesso nem afetar o login local.

## SMTP

- Nunca armazenar a senha SMTP em texto aberto.
- Usar `security.settingsEncryptionKey` somente a partir do ambiente.
- Não exibir a senha armazenada novamente na interface.
- Campo de senha vazio deve preservar a credencial existente.
- Não alterar silenciosamente a chave de criptografia.
- Envios de teste só devem ocorrer por ação explícita de administrador.
- Logs de SMTP não devem incluir senha ou conteúdo sensível da mensagem.

## Views e erros

- Reutilize `reports/_theme` e `reports/_topbar`.
- Escape todo conteúdo dinâmico com `esc`.
- Formulários de escrita devem incluir `csrf_field()`.
- Preserve o aviso temático de banco indisponível.
- A engrenagem administrativa deve aparecer para visitantes e administradores
  locais, mas não para usuários AD.
- Novos controles devem ser responsivos e seguir os componentes Bootstrap já
  usados pelo projeto.

## Validação

- Execute lint PHP em todo arquivo alterado.
- Confira as rotas e a ordem dos filtros após mudanças de autenticação.
- Teste os estados habilitado e desabilitado de configurações booleanas.
- Teste sucesso e falha sem reduzir validações de segurança.
