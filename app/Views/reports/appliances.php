<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Appliances</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Relatorio de VMs marcadas como appliance.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?php
    $legacyOnly = !empty($legacyOnly);
    $pageTitle = $legacyOnly ? 'Appliances por Gerência - Legados' : 'Appliances por Gerência';
    $pageSubtitle = $legacyOnly
        ? 'Relatorio de appliances legados agrupado por gerencia.'
        : 'Relatorio de appliances agrupado por gerencia.';
    ?>
    <?= view('reports/_topbar', [
        'subtitle' => $pageSubtitle,
        'activeMenu' => 'relatorios',
        'activeSubmenu' => $legacyOnly ? 'appliances-gerencia-legados' : 'appliances-gerencia',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Relatórios'],
            ['label' => 'Appliances'],
            ['label' => $pageTitle, 'active' => true],
        ],
    ]) ?>

    <?php if (empty($days)): ?>
        <div class="alert alert-info">Nenhum dado importado.</div>
    <?php else: ?>
        <div class="app-card p-3">
            <div class="d-flex justify-content-end mb-3">
                <a class="btn btn-brand" href="<?= site_url('reports/appliances?export=csv' . ($legacyOnly ? '&legacy=1' : '')) ?>">Exportar CSV</a>
            </div>
            <div class="accordion" id="inventoryAccordion">
                <?php foreach ($days as $index => $day): ?>
                    <?php $collapseId = 'collapse' . $index; ?>
                    <?php $headingId = 'heading' . $index; ?>
                    <?php
                        $displayDate = $day['reference_date'];
                        $dt = DateTime::createFromFormat('Y-m-d', $displayDate);
                        if ($dt !== false) {
                            $displayDate = $dt->format('d-m-Y');
                        }
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="<?= esc($headingId) ?>">
                            <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?= esc($collapseId) ?>"
                                    aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                    aria-controls="<?= esc($collapseId) ?>">
                                <?= esc($displayDate) ?>
                            </button>
                        </h2>
                        <div id="<?= esc($collapseId) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                             aria-labelledby="<?= esc($headingId) ?>" data-bs-parent="#inventoryAccordion">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead>
                                        <tr>
                                            <th>Gerência</th>
                                            <th class="text-end">Quantidade de VMs</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($day['items'] as $row): ?>
                                            <tr>
                                                <td>
                                                    <a href="<?= site_url('reports/appliances/detail?date=' . urlencode($day['reference_date']) . '&gerencia=' . urlencode($row['gerencia']) . ($legacyOnly ? '&legacy=1' : '')) ?>">
                                                        <?= esc($row['gerencia']) ?>
                                                    </a>
                                                </td>
                                                <td class="text-end"><?= esc((string) $row['vm_count']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                        <tr class="table-light fw-semibold">
                                            <td>Total</td>
                                            <td class="text-end"><?= esc((string) $day['total']) ?></td>
                                        </tr>
                                        </tfoot>
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
