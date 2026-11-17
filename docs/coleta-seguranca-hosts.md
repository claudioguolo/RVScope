# Coleta de segurança dos hosts (v1.5.4)

O menu **Segurança dos Hosts** apresenta SELinux e firewalld recebidos dos hosts
pelo Satellite, com busca, accordion por data, paginação de 50 hosts e o modal
de detalhes da própria coleta, em modo de consulta. Desde a versão 1.5.1, a
coleta e o relatório são independentes por padrão. Na versão 1.5.4, o
administrador pode ativar o vínculo dos relatórios ao inventário RVTools.
Respeita a configuração de autenticação dos relatórios; somente administradores
acessam **Configurar coleta**.

## Configuração

No servidor RVScope, configure `security.hostSecurityToken` no `.env` ou
`RVSCOPE_HOST_SECURITY_TOKEN` no ambiente, com um segredo aleatório de pelo menos
32 caracteres. Use uma credencial exclusiva para esta integração. Não versione
o segredo nem o coloque na URL. Guarde a mesma credencial como parâmetro protegido
no Satellite. Reinicie o serviço ao alterar variáveis do ambiente do container.
A página administrativa informa se há token configurado, sem revelá-lo.

## Job template do Satellite

Execute em cada host. No exemplo abaixo, substitua `COLOQUE_O_TOKEN_AQUI` pelo
mesmo token configurado em `security.hostSecurityToken` no `.env` do RVScope
(mínimo de 32 caracteres). A linha `export` define a variável usada na validação
e no cabeçalho do curl. Mantenha o nome `RVSCOPE_HOST_SECURITY_TOKEN` dentro de
`${...}`; substitua somente o valor entre aspas na atribuição.

Se o Satellite já disponibilizar a variável por um parâmetro protegido no
ambiente de execução, omita a linha `export` para preservar esse valor.
Não versione o token, não habilite shell tracing (`set -x`) nem registre o segredo
na saída do job.

```bash
#!/bin/bash
set -eu
export RVSCOPE_HOST_SECURITY_TOKEN='COLOQUE_O_TOKEN_AQUI'
: "${RVSCOPE_HOST_SECURITY_TOKEN:?Configure a credencial protegida da coleta}"
selinux="$(getenforce 2>/dev/null || true)"
host_ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
firewall="$(systemctl show firewalld --property=ActiveState --value 2>/dev/null || true)"
[ -n "$selinux" ] || selinux=unknown
[ -n "$firewall" ] || firewall=unknown
curl --fail-with-body --silent --show-error \
  --connect-timeout 10 --max-time 30 \
  -H "Authorization: Bearer ${RVSCOPE_HOST_SECURITY_TOKEN}" \
  --data-urlencode "hostname=$(hostname)" \
  --data-urlencode "ip=${host_ip}" \
  --data-urlencode "sestatus=${selinux}" \
  --data-urlencode "firewall=${firewall}" \
  'https://rvscope.tjmt.jus.br/api/coletas/seguranca'
```

O POST substitui o antigo GET para `coletor.php`. Não é necessário login interativo
ou CSRF para a API: a autenticação usa o Bearer token. O HTTPS deve validar a CA;
para CA interna, instale a cadeia no host ou use `--cacert` com o certificado
público. Não use `-k`. Em versões antigas do curl, substitua `--fail-with-body`
por `--fail`.

Campos obrigatórios: `hostname` (nome DNS válido, até 253 caracteres), `sestatus`
(`enforcing`, `permissive`, `disabled`, `unknown`, sem distinguir maiúsculas) e
`firewall` (ActiveState do systemd, `not-found` ou `unknown`). `active` informa o
estado do serviço, sem atestar suas regras ou a proteção efetiva do host.

Resposta JSON: 200 salvo; 401 token inválido; 413 corpo acima de 4 KiB; 422 campos
inválidos; 503 token ausente/curto, armazenamento ou banco indisponível. O filtro
global de banco preserva o aviso temático de indisponibilidade; nesse caso a
resposta pode ser HTML. Reexecute o job após corrigir a falha.

## Histórico e consulta independente

O servidor atribui o horário UTC. A última coleta recebida do hostname substitui
a anterior do mesmo dia; dias anteriores permanecem disponíveis. A gravação usa
bloqueio por dia e troca atômica de arquivo, sem alteração de estrutura no banco.
Os JSONs ficam fora do diretório público em `writable/host-security/YYYY-MM-DD.json`,
limitados a 10 MiB por dia. Inclua esse diretório no backup do volume writable.
Históricos corrompidos impedem gravação e devem ser recuperados do backup.

No modo independente, o relatório lê os arquivos da coleta de segurança e exibe todos os
hostnames recebidos na data selecionada, mesmo sem inventário RVTools ou coleta
Hammer nessa data. Por solicitação operacional, este relatório não aplica os
filtros de VM ligada ou sistema operacional configurados para os demais relatórios.
A busca usa o hostname coletado, sem distinguir maiúsculas e minúsculas.

Hostnames ou IPs repetidos no mesmo dia são consolidados antes da busca, dos
totais e da paginação, mantendo o registro mais recente. O botão **Detalhes**
mostra hostname, IP coletado (quando enviado), SELinux, firewalld e horário UTC.
Nenhum dado é consultado no inventário para estabelecer esse vínculo.
O formato dos arquivos existentes, o endpoint e o token permanecem os mesmos;
não é necessário reenviar o histórico nem executar migrations.

A independência se refere às outras coletas. A autenticação, as permissões e o
filtro global de disponibilidade do banco da aplicação continuam em vigor.

## Totais e gráficos por status (v1.5.2)

Acima do accordion, o relatório apresenta dois gráficos de barras horizontais,
um para SELinux e outro para firewalld, acompanhados de quantidades e percentuais
por status. Os totais consideram a última coleta de cada hostname na data
selecionada e respeitam a busca atual, incluindo todos os hosts das demais páginas.

SELinux mostra `enforcing`, `permissive`, `disabled` e `unknown`, inclusive quando
a quantidade é zero. firewalld mostra `active`, `inactive`, `failed` e `unknown`,
além dos demais estados recebidos na data e busca selecionadas (por exemplo,
`activating` e `not-found`). Nenhum estado recebido fica fora do denominador.

O percentual de cada status é sua quantidade dividida pelo total de hosts
filtrados, multiplicada por 100. Exibe uma casa decimal; a soma pode diferir de
100% por arredondamento. Sem hosts, todos os indicadores são zero. Os gráficos
usam os mesmos valores das quantidades e funcionam sem biblioteca externa de
gráficos. Os indicadores respeitam o modo de vínculo configurado na administração.

## Duplicidades por dia (v1.5.3)

A listagem, os totais, os percentuais e os gráficos usam apenas o registro mais
recente de cada grupo do dia UTC. Registros pertencem ao mesmo grupo quando
compartilham hostname ou IP, inclusive por vínculos sucessivos (um mesmo nome
com IPs diferentes ou nomes diferentes com o mesmo IP). Em empate de horário,
prevalece o último registro no arquivo. Dias diferentes são tratados separadamente.
Os arquivos existentes são consolidados na leitura, sem alteração retroativa.

Hostnames são comparados sem diferenciar maiúsculas ou ponto final de DNS.
Nomes curtos e FQDNs distintos só são unidos quando há um IP compartilhado.
IPv4 e IPv6 são aceitos; grafias equivalentes do mesmo IPv6 contam como um IP.
IP vazio ou ausente não gera vínculo entre hosts diferentes.

O campo opcional `ip` deve conter um único endereço. O job template acima envia
o primeiro endereço retornado por `hostname -I`; em hosts com várias interfaces,
ajuste `host_ip` para o endereço que identifica o host na sua rede. O servidor
não usa o IP da conexão HTTP, que pode pertencer a proxy ou NAT. Sem o campo,
coletas antigas e jobs anteriores continuam aceitos e são comparados por hostname.
Para eliminar duplicidades também por IP, atualize o job e execute uma nova coleta.
A busca pelo hostname ou IP é aplicada após a consolidação, sem recuperar estados
antigos descartados do relatório.

## Vínculo opcional com o RVTools (v1.5.4)

Acesse **Usuários e configurações → Coleta de segurança dos hosts** ou
**Segurança dos Hosts → Configurar coleta**. Somente administradores podem salvar
a opção **Vincular relatórios de segurança ao inventário RVTools**. O formulário
usa CSRF e informa sucesso ou falha. A preferência é persistida na tabela existente
`app_settings`, na chave `host_security_link_rvtools`, sem mudança de estrutura
ou necessidade de migration.

- **Desativado (padrão):** mantém o modo independente, mostrando todos os hosts
  recebidos após a consolidação diária de duplicidades.
- **Ativado:** associa as coletas ao RVTools da mesma data UTC. Exibe somente
  vínculos únicos com VMs `poweredOn` e `included_in_reports`, conforme a política
  de sistemas operacionais. Totais, percentuais, gráficos e paginação usam apenas
  esses hosts. A tabela mostra também o nome da VM vinculada.

A associação usa o hostname normalizado contra VM/DNS e, quando enviado, o IP
contra o IP primário do inventário. Nome curto só vincula se for único; conflitos
entre nome e IP ou múltiplas candidatas não aparecem. A associação considera
inclusive VMs excluídas para detectar ambiguidades. Se duas coletas distintas
apontarem para a mesma VM, prevalece o estado mais recente.

Sem inventário na data selecionada, o modo vinculado informa o motivo e mostra
zero hosts; não retorna automaticamente ao modo independente. Desativar a opção
restaura a consulta de todos os hosts recebidos, sem nova coleta. Alterar o vínculo
não modifica os arquivos nem bloqueia o recebimento de hosts sem correspondência.
O token, o endpoint, a autenticação e os papéis existentes permanecem os mesmos.
