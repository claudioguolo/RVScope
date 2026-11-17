# Coleta de segurança dos hosts (v1.5.0)

O menu **Segurança dos Hosts** apresenta SELinux e firewalld recebidos dos hosts
pelo Satellite, com busca, accordion por data, paginação de 50 hosts e o modal
padrão de detalhes. Respeita a configuração de autenticação dos relatórios e os
papéis de consulta/edição existentes. Administradores acessam **Configurar coleta**.

## Configuração

No servidor RVScope, configure `security.hostSecurityToken` no `.env` ou
`RVSCOPE_HOST_SECURITY_TOKEN` no ambiente, com um segredo aleatório de pelo menos
32 caracteres. Use uma credencial exclusiva para esta integração. Não versione
o segredo nem o coloque na URL. Guarde a mesma credencial como parâmetro protegido
no Satellite. Reinicie o serviço ao alterar variáveis do ambiente do container.
A página administrativa informa se há token configurado, sem revelá-lo.

## Job template do Satellite

Execute em cada host. Disponibilize `RVSCOPE_HOST_SECURITY_TOKEN` por mecanismo
protegido do ambiente de execução; adapte a referência ao parâmetro secreto do
seu template. Não habilite shell tracing (`set -x`) nem registre o token no job.

```bash
#!/bin/bash
set -eu
: "${RVSCOPE_HOST_SECURITY_TOKEN:?Configure a credencial protegida da coleta}"
selinux="$(getenforce 2>/dev/null || true)"
firewall="$(systemctl show firewalld --property=ActiveState --value 2>/dev/null || true)"
[ -n "$selinux" ] || selinux=unknown
[ -n "$firewall" ] || firewall=unknown
curl --fail-with-body --silent --show-error \
  --connect-timeout 10 --max-time 30 \
  -H "Authorization: Bearer ${RVSCOPE_HOST_SECURITY_TOKEN}" \
  --data-urlencode "hostname=$(hostname)" \
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

## Histórico e associação

O servidor atribui o horário UTC. A última coleta recebida do hostname substitui
a anterior do mesmo dia; dias anteriores permanecem disponíveis. A gravação usa
bloqueio por dia e troca atômica de arquivo, sem alteração de estrutura no banco.
Os JSONs ficam fora do diretório público em `writable/host-security/YYYY-MM-DD.json`,
limitados a 10 MiB por dia. Inclua esse diretório no backup do volume writable.
Históricos corrompidos impedem gravação e devem ser recuperados do backup.

O relatório associa o hostname normalizado ao nome da VM ou DNS do RVTools da
mesma data UTC. Nomes curtos só são vinculados quando a correspondência é única;
um FQDN deve corresponder ao nome completo registrado. Somente VMs `poweredOn`
e com `included_in_reports` aparecem. Hosts sem vínculo, ambíguos ou excluídos
são contabilizados separadamente. Sem RVTools na data, importe o inventário para
habilitar a listagem. Coletas recebidas são preservadas mesmo sem correspondência.
Quando dois nomes coletados apontam para a mesma VM, exibe-se a coleta mais recente.
