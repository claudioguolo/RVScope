<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Inventario historico de VMs por sistema operacional.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?php
    $pageSubtitle = $subtitle ?? 'Inventario historico de VMs por sistema operacional.';
    $pageActiveMenu = $activeMenu ?? 'inicio';
    $pageActiveSubmenu = $activeSubmenu ?? '';
    $pageBreadcrumbs = $breadcrumbs ?? [
        ['label' => 'Início', 'active' => true],
    ];
    ?>
    <?= view('reports/_topbar', [
        'subtitle' => $pageSubtitle,
        'activeMenu' => $pageActiveMenu,
        'activeSubmenu' => $pageActiveSubmenu,
        'showAdminShortcut' => true,
        'breadcrumbs' => $pageBreadcrumbs,
    ]) ?>

    <?php if (empty($days)): ?>
        <div class="alert alert-info">Nenhum dado importado.</div>
    <?php else: ?>
        <div class="app-card p-3">
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
                        $newVmCountDay = 0;
                        foreach ($day['items'] as $item) {
                            $newVmCountDay += (int) ($item['new_vm_count'] ?? 0);
                        }
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="<?= esc($headingId) ?>">
                            <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?= esc($collapseId) ?>"
                                    aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                    aria-controls="<?= esc($collapseId) ?>">
<?= esc($displayDate) ?>
                                <?php if ($newVmCountDay > 0): ?>
                                <span class="badge text-bg-danger ms-2" title="<?= esc($newVmCountDay . ($newVmCountDay === 1 ? ' VM nova' : ' VMs novas'), 'attr') ?>">
                                    <?= esc($newVmCountDay) ?>
                                </span>
                                <?php endif; ?>
                            </button>
                        </h2>
                        <div id="<?= esc($collapseId) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                             aria-labelledby="<?= esc($headingId) ?>" data-bs-parent="#inventoryAccordion">
                            <div class="accordion-body">
                                <div class="d-flex justify-content-end mb-3">
                                    <a class="btn btn-brand" href="<?= site_url('reports/vm?date=' . urlencode($day['reference_date']) . '&export=csv') ?>">Exportar CSV</a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead>
                                        <tr>
                                            <th>Sistema Operacional</th>
                                            <th class="text-end">Quantidade de VMs</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($day['items'] as $row): ?>
                                            <tr>
                                                <td>
                                                    <a href="<?= site_url('reports/detail?date=' . urlencode($day['reference_date']) . '&os=' . urlencode($row['os_name'])) ?>">
                                                        <?= esc($row['os_name']) ?>
                                                    </a>
                                                    <?php $newVmCount = (int) ($row['new_vm_count'] ?? 0); ?>
                                                    <?php if ($newVmCount > 0): ?>
                                                        <span class="badge text-bg-danger ms-2" title="<?= esc($newVmCount . ($newVmCount === 1 ? ' VM nova' : ' VMs novas'), 'attr') ?>">
                                                            <?= esc($newVmCount) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?= esc($row['vm_count']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                        <tr class="table-light fw-semibold">
                                            <td>Total</td>
                                            <td class="text-end"><?= esc($day['total']) ?></td>
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
