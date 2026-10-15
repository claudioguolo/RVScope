<?php
$canEditHosts = \App\Libraries\UserAuthorization::canEditHosts();
$returnTo = 'reports/buscar?' . http_build_query(['q' => $term, 'date' => $date, 'page' => $result['page']]);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Buscar VM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', ['activeMenu' => 'buscar', 'subtitle' => 'Busca por nome da VM, IP ou hostname', 'breadcrumbs' => [
        ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Buscar VM', 'active' => true],
    ]]) ?>
    <?php if (!empty($alert)): ?><div class="alert alert-<?= esc($alert['type'], 'attr') ?>"><?= esc($alert['message']) ?></div><?php endif; ?>
    <div class="app-card p-4 mb-4">
        <h1 class="h4">Buscar VM</h1>
        <p class="text-secondary">Digite o nome, IP ou hostname completo ou parte dele. A busca ignora maiúsculas e minúsculas e retorna todas as correspondências na data selecionada.</p>
        <?php if ($dates === []): ?>
            <div class="alert alert-info mb-0">Nenhum inventário importado para consulta.</div>
        <?php else: ?>
            <form method="get" action="<?= site_url('reports/buscar') ?>" class="row g-3 align-items-end">
                <div class="col-md-7"><label for="q" class="form-label">Nome da VM, IP ou hostname</label><input id="q" name="q" type="search" class="form-control" maxlength="254" required placeholder="Ex.: dsa ou 192.168.0." value="<?= esc($term, 'attr') ?>"></div>
                <div class="col-md-3"><label for="date" class="form-label">Data do inventário</label><select id="date" name="date" class="form-select"><?php foreach ($dates as $option): ?><option value="<?= esc($option, 'attr') ?>" <?= $option === $date ? 'selected' : '' ?>><?= esc(date('d/m/Y', strtotime($option))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-brand w-100">Buscar</button></div>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php elseif ($submitted && $dates !== []): ?>
        <div class="app-card p-4">
            <h2 class="h5"><?= (int) $result['total'] ?> VM(s) encontrada(s)</h2>
            <?php if ($result['rows'] === []): ?>
                <div class="alert alert-info mb-0">Nenhuma VM encontrada para o termo e a data informados.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-striped align-middle">
                    <thead><tr><th>VM</th><th>Hostname / DNS</th><th>IP</th><th>Sistema operacional</th><th>Estado</th><th>Info</th></tr></thead>
                    <tbody><?php foreach ($result['rows'] as $row): ?><tr>
                        <?php foreach (['vm', 'dns_name', 'primary_ip', 'os_name', 'power_state'] as $field): ?><td><?= esc($row[$field] ?: '—') ?></td><?php endforeach; ?>
                        <td><?= view('reports/_search_detail_button', ['row' => $row, 'canEditHosts' => $canEditHosts]) ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
                <nav class="d-flex flex-wrap gap-3 align-items-center" aria-label="Paginação da busca">
                    <?php if ($result['page'] > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('reports/buscar?' . http_build_query(['q' => $term, 'date' => $date, 'page' => $result['page'] - 1])), 'attr') ?>">Anterior</a><?php endif; ?>
                    <span>Página <?= (int) $result['page'] ?> de <?= (int) $result['pages'] ?> · Até 50 VMs por página</span>
                    <?php if ($result['page'] < $result['pages']): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('reports/buscar?' . http_build_query(['q' => $term, 'date' => $date, 'page' => $result['page'] + 1])), 'attr') ?>">Próxima</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?= view('reports/_host_detail_modal', [
    'canEditHosts' => $canEditHosts, 'managementUnits' => $managementUnits, 'operatingSystems' => $operatingSystems,
    'formAction' => site_url('reports/host-info'), 'hiddenFields' => ['return_to' => $returnTo],
]) ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?= view('reports/_host_detail_script', ['canEditHosts' => $canEditHosts, 'technicalResponsiblesByManagementUnit' => $technicalResponsiblesByManagementUnit]) ?>
</body>
</html>
