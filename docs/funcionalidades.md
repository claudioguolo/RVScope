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

## Controle de Erratas — versão 1.4.0

O menu principal inclui **Controle de Erratas → Erratas por Dia / Erratas com
Host**, preservando as demais opções e o tema. Os relatórios seguem a configuração
de acesso autenticado do sistema. Somente administradores importam dados em
**Usuários e configurações → Dados de erratas**.

O diário aceita o TXT existente do cron, inclusive `23-10-202610:00 - 260 bugfix ;
5 enhancement ; 469 security ; 3 unspecified ;`. A quantidade vem dos números
por tipo: nesse exemplo, **737**. Datas e horários são tratados como UTC, conforme
o `date -u` do coletor. O relatório mantém o acordeão por dia, mostra o saldo da
última coleta, preserva horários anteriores, oferece filtro por período, gráfico
dos últimos 90 dias com dados e paginação de 50 dias. Não soma snapshots entre dias.

O relatório com host recebe um CSV separado, pois o TXT agregado não identifica
hosts. A busca usa trechos de nome, hostname/DNS ou IP e ignora caixa. A associação
Satellite/RVTools usa nomes completos ou IP na mesma data, recusa ambiguidades
e lista somente VMs ligadas e incluídas nas políticas de SO. O botão de detalhes
reutiliza as permissões e os campos importados inativos do cadastro de hosts.
A soma por host pode contar uma errata em vários hosts e diferir do total diário.

Automações usam POST em `/api/erratas/por-dia` (campos `data` e `dados`) ou
`/api/erratas/com-host` (CSV), com `Authorization: Bearer` e o token exclusivo
`security.errataImportToken`. Não aceitam gravação por GET nem o token RVTools.
A Debug Toolbar não coleta essas requisições. Importações pela interface usam CSRF.

Os arquivos ficam no volume `writable/erratas`, fora do Git e do diretório público.
Atualizações usam bloqueio e troca atômica, preservam datas anteriores e não
duplicam reenvios do mesmo horário/host. Arquivos inválidos são recusados sem
alterar o histórico. Cada fonte admite até 10 MiB e 50 mil registros; volumes
maiores por host exigirão uma evolução para tabelas próprias. Não há migration
ou alteração de dados do PostgreSQL nesta implementação.
O coletor por host real ainda deve ser fornecido pelo Satellite. Consulte o
[guia de formatos, coleta e instalação](controle-de-erratas.md).

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
administrador. Usa o servidor SMTP já cadastrado, mas possui uma conta
exclusiva de testes, independente da conta de notificações. A partir da versão
1.3.3, porta e segurança também podem ser definidas no cadastro de testes.
O teste rápido vem
selecionado e habilita somente o botão **Teste Rápido**. Ignora quaisquer campos
alternativos enviados diretamente ao backend.

Ao selecionar o outro rádio, o botão rápido é desabilitado e são habilitados
conta, senha, destinatário personalizado e botão **Teste Personalizado**.
O usuário informa somente o nome da conta: o sufixo é definido pela configuração
`emailTest.domain` do ambiente e validado também no backend. O domínio é exibido
automaticamente nos formulários administrativo e personalizado. A conta completa
é usada como SMTP USER e FROM.
A senha é obrigatória no modo personalizado. O destino opcional substitui os
destinatários padrão; vazio usa os padrão. Aceita até 20 endereços únicos.
Os dados temporários não são persistidos nem repopulados após o envio.
O intervalo entre tentativas é configurável pelo administrador, com padrão de
60 segundos por usuário. A opção **Intervalo entre testes (segundos)** aceita
inteiros entre 0 e 3600; 0 permite testes consecutivos sem espera (versão 1.3.5).
O valor é salvo em `email_test_interval_seconds` na tabela `app_settings`, sem
alteração de esquema. A configuração vale para os dois modos de teste e é
aplicada no backend usando o log existente, inclusive entre sessões. Tentativas
que falham no SMTP também contam no intervalo; bloquear por intervalo não
reinicia a espera. Mesmo com intervalo 0, identificação e horário são registrados.

Somente administradores acessam **Configurar teste de e-mail**
(`admin/email-test`), também disponível em **Usuários e configurações**.
Ali cadastram o usuário e a senha exclusivos dos testes, destinatários padrão,
assunto, corpo, porta, segurança SMTP e intervalo entre testes. O botão **Usar 587 com STARTTLS**
preenche porta 587 e a opção criptografada STARTTLS; é necessário salvar o
formulário para aplicar. Também permanecem disponíveis SSL/TLS e nenhuma
criptografia, além de outras portas válidas entre 1 e 65535. A seleção é
validada no backend e vale para os testes rápido e personalizado. Até o
primeiro salvamento, porta e segurança são herdadas do SMTP geral, preservando
as instalações existentes. Depois disso, ficam independentes das notificações;
o servidor e a habilitação de SMTP continuam compartilhados. STARTTLS exige
suporte do servidor e certificado confiável, sem desabilitar validação TLS nem
recorrer a uma conexão sem criptografia se a negociação falhar.
As configurações usam novas chaves na tabela existente, sem mudança de esquema.
A senha usa AES-256-GCM e a chave externa
`security.settingsEncryptionKey`, sem reutilizar a senha de notificações.
Senha vazia preserva a existente; trocar a conta exige informar a nova senha.
Cadastros anteriores precisam receber a conta exclusiva antes do primeiro envio;
não há cópia automática de credenciais. O domínio da conta cadastrada também é
restrito ao domínio configurado no ambiente. O texto inicial adapta o
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

Na versão 1.3.4, configure `emailTest.domain=mail.exemplo.org` no `.env` para
usar outro domínio de origem. O valor deve ser um domínio válido, sem protocolo,
porta ou caminho; um `@` inicial opcional é normalizado. Se a chave estiver
ausente, usa `envio.tjmt.jus.br` para compatibilidade. Um valor vazio ou inválido
exibe erro na ferramenta e impede o envio, sem afetar as demais páginas.
O domínio não pode ser substituído pelo formulário e não restringe os
destinatários. Depois de mudar o domínio, recadastre a conta exclusiva e informe
a senha na administração; não há alteração automática das credenciais salvas.
O teste rápido exige que a conta cadastrada corresponda ao domínio atual.

Para falhas na negociação TLS, consulte o
[roteiro de diagnóstico do Carbonio na porta 587](smtp-carbonio-starttls.md).
As consultas de diagnóstico não enviam mensagens nem alteram o servidor SMTP.

Na versão 1.3.6, o resultado de falha do teste inclui um diagnóstico com a
etapa, servidor, porta, segurança e códigos SMTP numéricos disponíveis
(por exemplo, 454/4.7.0 para STARTTLS e 535/5.7.8 para autenticação).
Os códigos identificam a resposta, sem expor seu texto bruto. O transporte
reconhece avisos de DNS, conexão recusada, timeout, certificado não confiável,
nome de certificado divergente, validade e incompatibilidade de protocolo TLS.
Quando o aviso não identifica a causa exata, apresenta orientação para a etapa
que falhou, sem atribuir uma causa não confirmada.

O diagnóstico permanece na mensagem temporária de resultado e não amplia o
log de uso. Não contém senha, usuário SMTP, destinatários, corpo da mensagem,
transcript ou detalhes brutos de exceções. Erros de configuração, como SMTP
desabilitado ou falha ao recuperar a senha criptografada, também têm mensagens
específicas. O controller expõe apenas a exceção de diagnóstico criada pela
aplicação; erros inesperados continuam com mensagem genérica.

Na versão 1.3.7, o administrador da implantação pode definir `smtp.caFile` no
`.env` com o caminho absoluto de uma cadeia pública PEM confiável, montada no
container. Por exemplo, `certs/smtp-ca.crt` no host fica disponível como
`/etc/ssl/private/smtp-ca.crt` no Compose existente. A configuração é compartilhada
por testes e notificações e atende STARTTLS e TLS implícito. Ausente ou vazio
usa as CAs padrão do PHP/OpenSSL. Arquivo configurado ausente, ilegível ou inválido
produz erro; o transporte preserva a validação de confiança, validade e hostname.
A falha TLS interrompe o fluxo antes de autenticar ou enviar a mensagem.
O contexto não afeta HTTPS ou LDAPS. Certificados e `.env` não são versionados.
Consulte o [procedimento para a CA SMTP interna](smtp-carbonio-starttls.md#configurar-uma-ca-interna-no-rvscope-137).

A extensão do transporte interrompe o envio quando um comando necessário é
recusado, especialmente STARTTLS, e captura avisos de rede/OpenSSL dentro do
envio, preservando o handler global depois da operação. Mantém as verificações
TLS do framework e não tenta autenticar após falha de negociação. Falha no QUIT
não substitui a confirmação de aceitação da mensagem já recebida do servidor.

## Busca de VMs — versão 1.3.0

O menu **Buscar VM** (`reports/buscar`) consulta nome da VM, hostname/DNS e IP
primário importados do RVTools. A correspondência é parcial, em qualquer posição,
e ignora maiúsculas/minúsculas: `dsa` encontra `dsa-vra-0001`, `vra-DSA-0002`
e `dsateste-0001`. Um único termo pesquisa os três campos simultaneamente.
Caracteres `%` e `_` são tratados literalmente, não como curingas SQL.

A consulta usa por padrão a data mais recente disponível e permite escolher
outro snapshot. Não mistura resultados de datas diferentes. A partir da versão
1.3.2, lista somente VMs ligadas (`poweredOn`) e incluídas nos relatórios
(`included_in_reports = TRUE`), respeitando a seleção de sistemas operacionais
da administração, como a página principal. Esses critérios também se aplicam
ao total e à paginação, em buscas por nome, hostname ou IP.
Retorna nome, hostname/DNS, IP primário, sistema operacional e estado de energia,
com total de correspondências e paginação de 50 registros, preservando o termo
e a data. Não consulta IPs adicionais que não estejam no campo IP primário.

A busca é somente leitura e segue a configuração de autenticação dos relatórios,
incluindo retorno à URL solicitada após login. Usa parâmetros vinculados e escape
de HTML. Há mensagens para inventário vazio, termo inválido e nenhuma ocorrência.
Não altera estrutura de banco nem exige migrations.

### Detalhes na busca e campos importados — versão 1.3.1

Cada resultado de **Buscar VM** possui o botão **Detalhes** para consulta e
**Detalhes / Editar** para Editor/Administrador, com a mesma janela das outras
listagens. Os dados complementares são os atuais do cadastro da VM; os dados
importados correspondem ao snapshot selecionado. Ao salvar, a aplicação retorna
à busca preservando termo, data e página, mostrando o resultado da operação.
A gravação usa o endpoint existente, CSRF e a autorização por papel no backend.

Em todas as listagens que usam essa janela, nome da VM, hostname/DNS, IP, SO
detectado, criação do CSV e VCenter Notes aparecem em campos desabilitados,
sem possibilidade de edição ou envio desses valores como alterações. O nome da
VM é enviado separadamente como identificador oculto, sem alterar o inventário.
A criação importada fica separada de **Criação — registro complementar**, que
continua editável por Editor/Administrador, assim como os demais dados
complementares. Usuário comum permanece sem botão Salvar e com campos
complementares desabilitados. A alteração não exige migrations.
