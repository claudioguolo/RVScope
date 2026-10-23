<!doctype html>
<html lang="pt-BR">
<head><?= view('erratas/_head', ['title' => 'Dados de erratas']) ?></head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', ['activeMenu' => 'erratas', 'subtitle' => 'Importação de históricos e configuração da coleta de erratas.', 'breadcrumbs' => [
        ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Administração', 'url' => site_url('admin/users')], ['label' => 'Dados de erratas', 'active' => true],
    ]]) ?>
    <div class="d-flex flex-wrap gap-2 mb-4"><a class="btn btn-outline-secondary" href="<?= site_url('erratas/por-dia') ?>">Erratas por Dia</a><a class="btn btn-outline-secondary" href="<?= site_url('erratas/com-host') ?>">Erratas com Host</a></div>
    <?php if ($message): ?><div class="alert alert-success" role="status"><?= esc($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>
    <div class="app-card p-4 mb-4">
        <h1 class="h4">Importar histórico de erratas</h1>
        <p class="text-secondary">Importe o TXT atual do Satellite ou o CSV por host. As datas anteriores são preservadas. Reenviar a mesma coleta atualiza o registro, sem duplicá-lo.</p>
        <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/erratas') ?>" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4"><label class="form-label" for="kind">Fonte</label><select class="form-select" id="kind" name="kind"><option value="daily">Erratas por Dia (.txt)</option><option value="hosts">Erratas com Host (.csv)</option></select></div>
            <div class="col-md-5"><label class="form-label" for="file">Arquivo UTF-8</label><input class="form-control" type="file" id="file" name="file" accept=".txt,.csv,text/plain,text/csv" required><div class="form-text">Até 10 MiB e 50 mil registros, respeitando também o limite de upload do servidor.</div></div>
            <div class="col-md-3"><button class="btn btn-brand" type="submit">Importar histórico</button></div>
        </form>
    </div>
    <div class="row g-4 mb-4">
        <?php foreach (['Diário' => $dailySource, 'Por host' => $hostSource] as $name => $source): ?>
            <div class="col-md-6"><div class="app-card p-4 h-100"><h2 class="h5">Histórico <?= esc(mb_strtolower($name)) ?></h2>
                <?php if ($source['error'] !== null): ?><div class="alert alert-danger mb-0"><?= esc($source['error']) ?></div><?php else: ?><p class="mb-0"><?= count($source['records']) ?> registro(s) disponível(is).</p><?= view('erratas/_source_notice', ['source' => $source]) ?><?php endif; ?>
            </div></div>
        <?php endforeach; ?>
    </div>
    <div class="app-card p-4 mb-4">
        <h2 class="h5">Coleta automática por dia</h2>
        <p>Envie <code>data</code> e <code>dados</code> por POST para <code><?= esc(site_url('api/erratas/por-dia')) ?></code>, com o token no cabeçalho <code>Authorization: Bearer</code>.</p>
        <div class="alert <?= $tokenConfigured ? 'alert-success' : 'alert-warning' ?>"><?= $tokenConfigured ? 'Token da coleta de erratas configurado no ambiente.' : 'Configure security.errataImportToken no .env com um token exclusivo de pelo menos 32 caracteres. A coleta automática permanece desabilitada enquanto não estiver configurado.' ?></div>
        <p class="small text-secondary">O token não é exibido nem armazenado no cadastro. O script do Satellite deve obtê-lo de um arquivo ou variável protegida. Preserve a validação HTTPS do coletor.</p>
        <h3 class="h6">Formato diário aceito</h3>
        <pre class="border rounded p-3 bg-light text-wrap">23-10-202610:00 - 260 bugfix ; 5 enhancement ; 469 security ; 3 unspecified ;</pre>
        <p class="mb-0">Esse exemplo representa <strong>737 erratas</strong>. O horário é UTC, como no <code>date -u</code> do cron.</p>
    </div>
    <div class="app-card p-4">
        <h2 class="h5">Coleta por host</h2>
        <p>O TXT agregado não permite descobrir quais hosts possuem cada errata. Para essa etapa, gere um CSV separado com uma linha por host e horário:</p>
        <pre class="border rounded p-3 bg-light text-wrap">collected_at;host;hostname;ip;security;bugfix;enhancement;unspecified
2026-10-23 10:00:00;dsa-vra-0001;dsa-vra-0001.exemplo.org;192.0.2.10;12;4;1;0</pre>
        <p>O vínculo com o RVTools usa nome ou hostname completos, ou IP, na mesma data. Correspondências ambíguas são desconsideradas. A busca textual aceita partes desses valores.</p>
        <p>Para enviar automaticamente o lote CSV, use POST em <code><?= esc(site_url('api/erratas/com-host')) ?></code> com <code>Content-Type: text/csv</code> e o mesmo token de erratas. A importação manual também está disponível acima.</p>
        <p class="text-secondary mb-0 small">Os arquivos ficam no volume persistente <code>writable/erratas</code>. O RVScope somente consulta as coletas recebidas; não aplica atualizações nos hosts.</p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
