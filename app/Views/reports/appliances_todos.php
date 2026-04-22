<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Appliances - Todos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Relatorio geral de appliances por data.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Relatorio geral de appliances por data.',
        'activeMenu' => 'relatorios',
        'activeSubmenu' => 'appliances-todos',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Relatórios'],
            ['label' => 'Appliances'],
            ['label' => 'Todos', 'active' => true],
        ],
    ]) ?>

    <?php if (empty($days)): ?>
        <div class="alert alert-info">Nenhum dado importado.</div>
    <?php else: ?>
        <div class="app-card p-3">
            <div class="d-flex justify-content-end mb-3">
                <a class="btn btn-brand" href="<?= site_url('reports/appliances/todos?export=csv') ?>">Exportar CSV</a>
            </div>
            <div class="accordion" id="appliancesTodosAccordion">
                <?php foreach ($days as $index => $day): ?>
                    <?php
                    $collapseId = 'collapse' . $index;
                    $headingId = 'heading' . $index;
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
                             aria-labelledby="<?= esc($headingId) ?>" data-bs-parent="#appliancesTodosAccordion">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead>
                                        <tr>
                                            <th>Escopo</th>
                                            <th class="text-end">Quantidade de VMs</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>
                                                <a href="<?= site_url('reports/appliances/detail?date=' . urlencode($day['reference_date'])) ?>">
                                                    Todos
                                                </a>
                                            </td>
                                            <td class="text-end"><?= esc((string) $day['vm_count']) ?></td>
                                        </tr>
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
