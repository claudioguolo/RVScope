<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Hosts por Gerência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Inventário de hosts agrupado por gerência.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Inventário atual de hosts agrupado por gerência.',
        'activeMenu' => 'relatorios',
        'activeSubmenu' => 'inventario-gerencia',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Relatórios'],
            ['label' => 'Hosts por Gerência', 'active' => true],
        ],
    ]) ?>

    <?php if ($error !== null): ?>
        <div class="alert alert-warning"><?= esc($error) ?></div>
    <?php elseif ($referenceDate === '' || $groups === []): ?>
        <div class="alert alert-info">Nenhum dado importado.</div>
    <?php else: ?>
        <?php
        $displayDate = $referenceDate;
        $date = DateTime::createFromFormat('Y-m-d', $referenceDate);
        if ($date !== false) {
            $displayDate = $date->format('d/m/Y');
        }
        ?>
        <div class="app-card p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <span class="fw-semibold">Inventário de <?= esc($displayDate) ?></span>
                    <span class="text-muted ms-2"><?= esc((string) array_sum(array_map('count', $groups))) ?> hosts</span>
                </div>
                <a class="btn btn-brand" href="<?= site_url('reports/inventario-por-gerencia?export=csv') ?>">Exportar CSV</a>
            </div>

            <div class="accordion" id="hostInventoryAccordion">
                <?php foreach ($groups as $management => $items): ?>
                    <?php
                    $management = (string) $management;
                    $collapseId = 'management-' . substr(hash('sha256', $management), 0, 12);
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#<?= esc($collapseId, 'attr') ?>" aria-expanded="false">
                                <?= esc($management) ?>
                                <span class="badge text-bg-secondary ms-2"><?= esc((string) count($items)) ?></span>
                            </button>
                        </h2>
                        <div id="<?= esc($collapseId, 'attr') ?>" class="accordion-collapse collapse" data-bs-parent="#hostInventoryAccordion">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Nome VMware</th>
                                            <th>DNS</th>
                                            <th>IP</th>
                                            <th>Última atualização</th>
                                            <th>Informações de contrato</th>
                                            <th>Asset risk score (ASTI)</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($items as $row): ?>
                                            <?php
                                            $lastUpdate = (string) ($row['os_last_update_date'] ?? '');
                                            $lastUpdateDate = DateTime::createFromFormat('Y-m-d', $lastUpdate);
                                            if ($lastUpdateDate !== false) {
                                                $lastUpdate = $lastUpdateDate->format('d/m/Y');
                                            }
                                            ?>
                                            <tr>
                                                <td><?= esc((string) ($row['vm'] ?? '')) ?></td>
                                                <td><?= esc((string) ($row['dns_name'] ?? '')) ?></td>
                                                <td><?= esc((string) ($row['primary_ip'] ?? '')) ?></td>
                                                <td><?= esc($lastUpdate) ?></td>
                                                <td><?= esc((string) ($row['contract'] ?? '')) ?></td>
                                                <td><?= esc((string) ($row['asset_risk_score'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
