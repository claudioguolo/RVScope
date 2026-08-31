<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Relatório Personalizado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Relatório Personalizado de hosts.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Resumo de VMs com filtros combináveis.',
        'activeMenu' => 'relatorios',
        'activeSubmenu' => 'personalizado',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Relatórios'],
            ['label' => 'Relatório Personalizado', 'active' => true],
        ],
    ]) ?>

    <div class="app-card p-4 mb-4">
        <div class="mb-4">
            <span class="app-eyebrow">Filtros</span>
            <h1 class="h4 mt-2 mb-2">Gerar relatório</h1>
            <p class="text-secondary mb-0">
                Sistema operacional e gerência são cumulativos. Quando Legados, Appliances ou Migráveis forem marcados juntos, serão consideradas VMs de qualquer uma dessas categorias.
            </p>
        </div>

        <form method="get" action="<?= site_url('reports/personalizado') ?>" class="row g-3">
            <input type="hidden" name="generate" value="1">
            <div class="col-12 col-md-6 col-lg-3">
                <label for="report_date" class="form-label fw-semibold">Data</label>
                <select id="report_date" name="date" class="form-select" required>
                    <option value="">Selecione</option>
                    <?php foreach ($dates as $date): ?>
                        <?php
                        $dateValue = (string) $date;
                        $dateLabel = $dateValue;
                        $parsedDate = DateTime::createFromFormat('Y-m-d', $dateValue);
                        if ($parsedDate !== false) {
                            $dateLabel = $parsedDate->format('d/m/Y');
                        }
                        ?>
                        <option value="<?= esc($dateValue, 'attr') ?>" <?= $criteria->date === $dateValue ? 'selected' : '' ?>>
                            <?= esc($dateLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label for="report_group_by" class="form-label fw-semibold">Tipo de resumo</label>
                <select id="report_group_by" name="group_by" class="form-select" required>
                    <option value="management_unit" <?= $criteria->groupBy === 'management_unit' ? 'selected' : '' ?>>VMs por gerência</option>
                    <option value="operating_system" <?= $criteria->groupBy === 'operating_system' ? 'selected' : '' ?>>VMs por sistema operacional</option>
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label for="report_os" class="form-label fw-semibold">Sistema operacional</label>
                <div class="form-check mb-2">
                    <input id="report_os_all" type="checkbox" class="form-check-input">
                    <label for="report_os_all" class="form-check-label">Selecionar todos</label>
                </div>
                <select id="report_os" name="os[]" class="form-select" multiple size="6">
                    <?php foreach ($operatingSystems as $operatingSystem): ?>
                        <option value="<?= esc((string) $operatingSystem, 'attr') ?>" <?= in_array((string) $operatingSystem, $criteria->operatingSystems, true) ? 'selected' : '' ?>>
                            <?= esc((string) $operatingSystem) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Sem seleção considera todos. Use Ctrl ou Command para selecionar vários.</div>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label for="report_management" class="form-label fw-semibold">Gerência</label>
                <div class="form-check mb-2">
                    <input id="report_management_all" type="checkbox" class="form-check-input">
                    <label for="report_management_all" class="form-check-label">Selecionar todas</label>
                </div>
                <select id="report_management" name="management_unit_id[]" class="form-select" multiple size="6">
                    <?php foreach ($managementUnits as $managementUnit): ?>
                        <?php $managementId = (int) ($managementUnit['id'] ?? 0); ?>
                        <option value="<?= $managementId ?>" <?= in_array($managementId, $criteria->managementUnitIds, true) ? 'selected' : '' ?>>
                            <?= esc((string) ($managementUnit['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Sem seleção considera todas. Use Ctrl ou Command para selecionar várias.</div>
            </div>

            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column justify-content-end gap-2">
                <div class="form-check">
                    <input id="report_legacy" name="legacy" value="1" type="checkbox" class="form-check-input" <?= $criteria->legacy ? 'checked' : '' ?>>
                    <label for="report_legacy" class="form-check-label">Hosts legados</label>
                </div>
                <div class="form-check">
                    <input id="report_appliance" name="appliance" value="1" type="checkbox" class="form-check-input" <?= $criteria->appliance ? 'checked' : '' ?>>
                    <label for="report_appliance" class="form-check-label">Appliances</label>
                </div>
                <div class="form-check">
                    <input id="report_migrable" name="migrable" value="1" type="checkbox" class="form-check-input" <?= $criteria->migrable ? 'checked' : '' ?>>
                    <label for="report_migrable" class="form-check-label">Migráveis</label>
                </div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-brand">Gerar relatório</button>
                <a href="<?= site_url('reports/personalizado') ?>" class="btn btn-outline-secondary">Limpar filtros</a>
            </div>
        </form>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-warning"><?= esc($error) ?></div>
    <?php elseif ($submitted): ?>
        <div class="app-card p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <?php $totalVms = array_sum(array_map(static fn (array $row): int => (int) ($row['vm_count'] ?? 0), $rows)); ?>
                <span class="fw-semibold"><?= esc((string) $totalVms) ?> VM(s) encontrada(s)</span>
                <?php if ($rows !== []): ?>
                    <?php
                    $exportParameters = [
                        'generate' => '1',
                        'export' => 'csv',
                        'date' => $criteria->date,
                        'group_by' => $criteria->groupBy,
                        'os' => $criteria->operatingSystems,
                        'management_unit_id' => $criteria->managementUnitIds,
                    ];
                    if ($criteria->legacy) {
                        $exportParameters['legacy'] = '1';
                    }
                    if ($criteria->appliance) {
                        $exportParameters['appliance'] = '1';
                    }
                    if ($criteria->migrable) {
                        $exportParameters['migrable'] = '1';
                    }
                    ?>
                    <a class="btn btn-brand" href="<?= site_url('reports/personalizado?' . http_build_query($exportParameters)) ?>">Exportar CSV</a>
                <?php endif; ?>
            </div>

            <?php if ($rows === []): ?>
                <div class="alert alert-info mb-0">Nenhuma VM corresponde aos filtros selecionados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th><?= $criteria->groupBy === 'operating_system' ? 'Sistema operacional' : 'Gerência' ?></th>
                            <th class="text-end">Quantidade de VMs</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $detailParameters = [
                            'date' => $criteria->date,
                            'group_by' => $criteria->groupBy,
                            'os' => $criteria->operatingSystems,
                            'management_unit_id' => $criteria->managementUnitIds,
                        ];
                        if ($criteria->legacy) {
                            $detailParameters['legacy'] = '1';
                        }
                        if ($criteria->appliance) {
                            $detailParameters['appliance'] = '1';
                        }
                        if ($criteria->migrable) {
                            $detailParameters['migrable'] = '1';
                        }
                        ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowDetailParameters = $detailParameters + [
                                'group_name' => (string) ($row['group_name'] ?? 'Sem registro'),
                            ];
                            ?>
                            <tr>
                                <td>
                                    <a target="_blank" rel="noopener noreferrer"
                                       href="<?= site_url('reports/personalizado/detail?' . http_build_query($rowDetailParameters)) ?>">
                                        <?= esc((string) ($row['group_name'] ?? 'Sem registro')) ?>
                                    </a>
                                </td>
                                <td class="text-end"><?= esc((string) ((int) ($row['vm_count'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr class="table-light fw-semibold">
                            <td>Total</td>
                            <td class="text-end"><?= esc((string) $totalVms) ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function bindSelectAll(checkboxId, selectId) {
  const checkbox = document.getElementById(checkboxId);
  const select = document.getElementById(selectId);
  if (!checkbox || !select) {
    return;
  }

  const updateCheckbox = () => {
    const options = Array.from(select.options);
    checkbox.checked = options.length > 0 && options.every((option) => option.selected);
    checkbox.indeterminate = options.some((option) => option.selected) && !checkbox.checked;
  };

  checkbox.addEventListener('change', () => {
    Array.from(select.options).forEach((option) => {
      option.selected = checkbox.checked;
    });
    checkbox.indeterminate = false;
  });
  select.addEventListener('change', updateCheckbox);
  updateCheckbox();
}

bindSelectAll('report_os_all', 'report_os');
bindSelectAll('report_management_all', 'report_management');
</script>
</body>
</html>
