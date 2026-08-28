<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Detalhe do relatório personalizado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Hosts do relatório personalizado.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Hosts correspondentes ao resumo personalizado.',
        'activeMenu' => 'relatorios',
        'activeSubmenu' => 'personalizado',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Relatórios'],
            ['label' => 'Personalizado', 'url' => $backUrl],
            ['label' => $groupName, 'active' => true],
        ],
    ]) ?>

    <?php
    $displayDate = $criteria->date;
    $parsedDate = DateTime::createFromFormat('Y-m-d', $displayDate);
    if ($parsedDate !== false) {
        $displayDate = $parsedDate->format('d/m/Y');
    }
    $exportParameters = [
        'date' => $criteria->date,
        'group_by' => $criteria->groupBy,
        'group_name' => $groupName,
        'os' => $criteria->operatingSystems,
        'management_unit_id' => $criteria->managementUnitIds,
        'export' => 'csv',
    ];
    foreach (['legacy', 'appliance', 'migrable'] as $flag) {
        if ($criteria->{$flag}) {
            $exportParameters[$flag] = '1';
        }
    }
    ?>

    <div class="app-card p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h1 class="h5 mb-1"><?= esc($groupName) ?></h1>
                <span class="text-secondary">Data: <?= esc($displayDate) ?> · <?= count($rows) ?> host(s)</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>">Voltar ao resumo</a>
                <?php if ($rows !== []): ?>
                    <a class="btn btn-brand" href="<?= site_url('reports/personalizado/detail?' . http_build_query($exportParameters)) ?>">Exportar CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($rows === []): ?>
            <div class="alert alert-info mb-0">Nenhum host corresponde a este grupo e aos filtros selecionados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>VM</th>
                        <th>DNS</th>
                        <th>IP</th>
                        <th>Sistema operacional</th>
                        <th>Gerência</th>
                        <th>Última atualização</th>
                        <th>Legado</th>
                        <th>Appliance</th>
                        <th>Migrável</th>
                        <th>Contrato</th>
                        <th>ASTI</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php
                        $lastUpdate = (string) ($row['os_last_update_date'] ?? '');
                        $lastUpdateDate = DateTime::createFromFormat('Y-m-d', $lastUpdate);
                        if ($lastUpdateDate !== false) {
                            $lastUpdate = $lastUpdateDate->format('d/m/Y');
                        }
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= esc((string) ($row['vm'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['dns_name'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['primary_ip'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['os_name_display'] ?? $row['os_name'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['gerencia'] ?? 'Sem registro')) ?></td>
                            <td><?= esc($lastUpdate) ?></td>
                            <td><?= (int) ($row['leg'] ?? 0) === 1 ? 'Sim' : 'Não' ?></td>
                            <td><?= (int) ($row['app'] ?? 0) === 1 ? 'Sim' : 'Não' ?></td>
                            <td><?= (int) ($row['mig'] ?? 0) === 1 ? 'Sim' : 'Não' ?></td>
                            <td><?= esc((string) ($row['contract'] ?? '')) ?></td>
                            <td><?= esc((string) ($row['asset_risk_score'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
