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
    <div class="app-header mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="app-title h4 mb-1">RVScope</div>
                <div class="small text-white-50">Inventario historico de VMs por sistema operacional.</div>
            </div>
        </div>
    </div>

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
                        $hasNewDay = false;
                        foreach ($day['items'] as $item) {
                            if (!empty($item['has_new'])) {
                                $hasNewDay = true;
                                break;
                            }
                        }
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="<?= esc($headingId) ?>">
                            <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?= esc($collapseId) ?>"
                                    aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                    aria-controls="<?= esc($collapseId) ?>">
<?= esc($displayDate) ?>
                                <?php if ($hasNewDay): ?>
                                <span class="text-danger small ms-2" title="VM nova">&#9679;</span>
                                <?php endif; ?>
                            </button>
                        </h2>
                        <div id="<?= esc($collapseId) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                             aria-labelledby="<?= esc($headingId) ?>" data-bs-parent="#inventoryAccordion">
                            <div class="accordion-body">
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
                                                    <?php if (!empty($row['has_new'])): ?>
                                                        <span class="text-danger small ms-2" title="VM nova">&#9679;</span>
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
