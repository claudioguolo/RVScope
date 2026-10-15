# Funcionalidades e regras de negócio

Este documento descreve o comportamento funcional atualmente implementado no
RVScope. Ele deve ser atualizado no mesmo pull request que alterar uma regra de
importação, relatório, permissão ou cadastro administrativo.

## Acesso e papéis

- O acesso aos relatórios pode ser público ou exigir autenticação. Em produção,
  a autenticação é o padrão enquanto o administrador não decidir explicitamente.
- Usuários locais podem ter os papéis **Usuário**, **Editor** ou
  **Administrador**.
- Usuário consulta relatórios; Editor também importa CSVs e edita hosts;
  Administrador acessa todas as configurações.
- A autenticação Active Directory usa LDAPS e concede somente acesso de leitura
  aos relatórios.
- Não existem usuário ou senha administrativos de fallback no código.

## Importação e histórico

- A importação aceita exports `vInfo` do RVTools e extrai a data do nome do
  arquivo.
- Cada data é substituída em uma transação, evitando registros duplicados ao
  reimportar o mesmo snapshot.
- A linha original é preservada em `raw_data` (`JSONB`) para auditoria.
- Hosts desligados não entram nos resumos. Para um host ligado cujo SO esteja
  temporariamente vazio, é usado o último SO conhecido em data anterior.
- O backfill histórico é executado por `rvscope:backfill-csv`; sem `--execute`,
  o comando apenas analisa os arquivos.
- A interface de importação exige Editor ou Administrador. Automações usam
  `POST /api/import` com um token exclusivo de pelo menos 32 caracteres.

## Sistema operacional efetivo

O sistema mantém separadas três informações:

1. `os_name_raw`: valor recebido do campo **OS according to the VMware Tools**;
2. SO efetivo do snapshot: valor bruto, fallback histórico ou SO fixado;
3. `hosts_info.operating_system_override`: SO fixado manualmente no cadastro do
   host.

O SO fixado tem prioridade nos resumos, relatórios e gráficos. Essa regra evita
falsos desaparecimentos quando o RVTools alterna a detecção do mesmo host, por
exemplo entre `VMware Photon OS (64-bit)` e `Other 3.x Linux (64-bit)`. O valor
bruto permanece disponível para auditoria.

Em **Administração > Sistemas operacionais**, o administrador vê os sistemas
detectados, quantidade de ocorrências e última data, e seleciona quais devem ser
ignorados. Alterar essa configuração recalcula a inclusão dos snapshots e os
resumos históricos. Hosts desligados continuam excluídos independentemente da
política.

## Inventário e cadastros

- Os detalhes do host armazenam descrição, proprietário, flags de legado,
  appliance e migrável, gerência, responsável técnico, contrato, risco e SO
  fixado.
- O checkbox **Existe Contrato** controla o campo **Contrato**. Quando marcado,
  uma seção com a descrição obrigatória e a validade opcional do contrato é
  expandida. Quando desmarcado, a seção permanece oculta e os dois campos são
  inativados. O salvamento altera somente o indicador de existência entre os
  dados contratuais; descrição e validade já armazenadas são preservadas para
  casos de cancelamento ou desmarcação acidental.
- Gerências e responsáveis técnicos são tabelas normalizadas e relacionadas ao
  host por identificador.
- Ambos aceitam telefone opcional, edição, ativação, inativação e exclusão
  lógica. Um item excluído deixa de aparecer na interface; seus dados não são
  apagados do banco.
- A inativação não exige completar os campos do cadastro. A reativação volta a
  aplicar as validações obrigatórias.
- Itens inativos aparecem depois dos ativos nas telas administrativas e não
  podem ser escolhidos na edição de hosts ou nos filtros de relatório.
- O detalhe do host alerta quando sua gerência atualmente vinculada está
  inativa.
- Administradores podem migrar hosts em lote entre gerências ativas.

## Relatórios

A seção **VMs** oferece os relatórios por sistema operacional, por gerência,
legados, migráveis e o **Relatório Personalizado**. As antigas opções separadas
de Appliances foram removidas do menu; rotas antigas permanecem somente por
compatibilidade.

O Relatório Personalizado permite:

- escolher uma data;
- selecionar vários sistemas operacionais ou todos;
- selecionar várias gerências ativas ou todas;
- filtrar legados, appliances e migráveis;
- resumir por gerência ou por sistema operacional.

Cada linha do resumo abre a lista correspondente de hosts. As listagens começam
com uma numeração ordinal, não exibem a coluna `PoweredOn`, permitem editar cada
host e podem ser exportadas em CSV. A coluna de sistema operacional usa o mesmo
SO efetivo da página inicial e remove o sufixo `(64-bit)` apenas na apresentação.
A coluna **Última atualização** também é exibida.

## Gráficos

A guia **Gráficos** contém a evolução histórica da quantidade de hosts por
sistema operacional. O gráfico usa linhas suavizadas, permite selecionar um ou
mais sistemas, marcar todos e limitar o período entre a data mais antiga e a
mais recente disponíveis.

## Integridade e operação

- O formulário de detalhes do host e seu comportamento JavaScript são
  componentes compartilhados por todas as listagens, evitando implementações
  divergentes entre os relatórios.
- O esquema PostgreSQL é alterado somente pelas migrations versionadas.
- Chaves estrangeiras protegem os relacionamentos normalizados.
- O container executa migrations pendentes na inicialização; se o banco ainda
  estiver indisponível, inicia em modo degradado e registra o problema.
- O pipeline valida Composer, executa testes e lint, valida o Compose, constrói
  a imagem e executa o scanner Trivy antes da publicação no Harbor.

## Teste de e-mail — versão 1.2.0

O menu **Teste de e-mail** (`tools/email-test`) está disponível para usuários
ativos autenticados, locais ou AD, dos papéis Usuário, Editor e Administrador,
mesmo quando a consulta de relatórios é pública. Esta é uma autorização específica
para executar o diagnóstico, sem conceder edição de hosts ou configurações.

O formulário envia somente o assunto e o corpo em texto simples definidos pelo
administrador. Usa o servidor, porta e segurança SMTP já cadastrados, mas possui uma conta
exclusiva de testes, independente da conta de notificações. O teste rápido vem
selecionado e habilita somente o botão **Teste Rápido**. Ignora quaisquer campos
alternativos enviados diretamente ao backend.

Ao selecionar o outro rádio, o botão rápido é desabilitado e são habilitados
conta, senha, destinatário personalizado e botão **Teste Personalizado**.
O usuário informa somente o nome da conta: o sufixo `@envio.tjmt.jus.br` é fixo
e validado também no backend. A conta completa é usada como SMTP USER e FROM.
A senha é obrigatória no modo personalizado. O destino opcional substitui os
destinatários padrão; vazio usa os padrão. Aceita até 20 endereços únicos.
Os dados temporários não são persistidos nem repopulados após o envio.
O intervalo entre tentativas é de 60 segundos por usuário.

Somente administradores acessam **Configurar teste de e-mail**
(`admin/email-test`), também disponível em **Usuários e configurações**.
Ali cadastram o usuário e a senha exclusivos dos testes, destinatários padrão,
assunto e corpo. A senha usa AES-256-GCM e a chave externa
`security.settingsEncryptionKey`, sem reutilizar a senha de notificações.
Senha vazia preserva a existente; trocar a conta exige informar a nova senha.
Cadastros anteriores precisam receber a conta exclusiva antes do primeiro envio;
não há cópia automática de credenciais. O domínio da conta cadastrada também é
restrito a `@envio.tjmt.jus.br`. O texto inicial adapta o
script anterior para identificar o RVScope, sem dados pessoais ou credenciais.
As configurações usam a tabela existente `app_settings`; não há alteração de
estrutura nem migration necessária. Nenhuma credencial do script foi incorporada.

O registro de uso fica em `writable/logs/email-test-usage.log`, com ID do usuário
e data/hora UTC da tentativa, inclusive quando o envio falha. Não registra conta
alternativa, senha, destinatários, assunto, corpo ou transcript SMTP. O arquivo
precisa ser gravável: se não for possível registrar o uso, o envio é bloqueado.
O mesmo log aplica o intervalo entre tentativas, inclusive entre sessões, em uma
instância com diretório writable compartilhado. Instâncias com writable separados
precisam de armazenamento compartilhado para aplicar esse limite em conjunto.

Os POSTs usam CSRF e rotas protegidas por papel. SMTP desabilitado impede envio.
A aceitação pelo servidor não comprova entrega na caixa de entrada; a interface
explicita essa diferença. Não há envio automático nem testes contra o servidor
real durante a validação do código. O transporte da aplicação evita registrar
transcripts SMTP em falhas. A versão funcional aparece no cabeçalho, independente
da tag efetiva da imagem, que continua sendo exibida quando configurada.

A barra de debug não coleta as requisições de teste/configuração de e-mail,
mesmo em desenvolvimento, para não persistir os campos do formulário.
