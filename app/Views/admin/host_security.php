<!doctype html>
<html lang="pt-BR">
<head><?= view('erratas/_head', ['title' => 'Configurar coleta de segurança']) ?></head>
<body><div class="container py-4">
<?= view('reports/_topbar', ['activeMenu' => 'host-security', 'breadcrumbs' => [['label' => 'Segurança dos Hosts', 'url' => site_url('reports/seguranca-hosts')], ['label' => 'Configurar coleta', 'active' => true]]]) ?>
<div class="app-card p-4">
<h1 class="h4">Configurar coleta de segurança</h1>
<div class="alert alert-<?= $tokenConfigured ? 'success' : 'warning' ?>"><?= $tokenConfigured ? 'Token configurado no servidor.' : 'Coleta desabilitada: configure um token com pelo menos 32 caracteres no servidor.' ?></div>
<p>Defina <code>security.hostSecurityToken</code> no arquivo .env ou <code>RVSCOPE_HOST_SECURITY_TOKEN</code> no ambiente do servidor. Guarde a mesma credencial nos parâmetros protegidos do Satellite. O token não é exibido nesta página.</p>
<p>Endpoint: <code><?= esc(site_url('api/coletas/seguranca')) ?></code> (POST). Envie <code>Authorization: Bearer TOKEN</code> e os campos <code>hostname</code>, <code>sestatus</code> e <code>firewall</code> como formulário. Envie também <code>ip</code> (opcional, um IPv4 ou IPv6) para identificar duplicidades por endereço.</p>
<p>A coleta de segurança é independente do RVTools, Hammer e das demais coletas. O relatório exibe todos os hostnames recebidos, sem exigir inventário na mesma data.</p>
<p>O horário é atribuído pelo servidor em UTC. O relatório considera somente o último registro do dia para hostnames ou IPs duplicados. O histórico fica no volume persistente <code>writable/host-security</code>. Inclua esse diretório nos backups.</p>
<p class="mb-0">Resposta 200: coleta salva; 401: token inválido; 413: corpo acima de 4 KiB; 422: campos inválidos; 503: token ausente, banco ou armazenamento indisponível. Consulte o guia <code>docs/coleta-seguranca-hosts.md</code> para o job template.</p>
</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script></body></html>
