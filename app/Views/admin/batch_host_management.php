<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Migração de hosts entre gerências</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Migração administrativa de hosts entre gerências.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Migração em lote de hosts entre gerências.',
        'activeMenu' => 'administracao',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Administração'],
            ['label' => 'Migrar hosts', 'active' => true],
        ],
    ]) ?>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/users') ?>">Usuários e configurações</a>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/catalogs') ?>">Gerências e responsáveis</a>
        <a class="btn btn-brand" href="<?= site_url('admin/host-management-migration') ?>">Migrar hosts</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= esc($message) ?></div>
    <?php endif; ?>

    <div class="app-card p-4 mb-4">
        <span class="app-eyebrow">Origem</span>
        <h1 class="h4 mt-2 mb-3">Selecionar gerência atual</h1>
        <form method="get" action="<?= site_url('admin/host-management-migration') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-lg-8">
                <label for="source_management_unit_id" class="form-label fw-semibold">Gerência de origem</label>
                <select id="source_management_unit_id" name="source_management_unit_id" class="form-select" required>
                    <option value="">Selecione</option>
                    <?php foreach ($managementUnits as $managementUnit): ?>
                        <?php
                        $managementId = (int) ($managementUnit['id'] ?? 0);
                        $isActive = in_array(
                            $managementUnit['is_active'] ?? false,
                            [true, 1, '1', 't', 'true'],
                            true
                        );
                        ?>
                        <option value="<?= $managementId ?>" <?= $sourceId === $managementId ? 'selected' : '' ?>>
                            <?= esc((string) ($managementUnit['name'] ?? '')) ?><?= $isActive ? '' : ' (Inativa)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-auto">
                <button type="submit" class="btn btn-brand">Carregar hosts</button>
            </div>
        </form>
    </div>

    <?php if ($sourceId > 0): ?>
        <div class="app-card p-4">
            <span class="app-eyebrow">Destino</span>
            <h2 class="h4 mt-2 mb-3">Migrar hosts selecionados</h2>

            <?php if ($hosts === []): ?>
                <div class="alert alert-info mb-0">Nenhum host está associado à gerência de origem.</div>
            <?php else: ?>
                <form method="post" action="<?= site_url('admin/host-management-migration') ?>" id="batchMigrationForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="source_management_unit_id" value="<?= $sourceId ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-lg-8">
                            <label for="destination_management_unit_id" class="form-label fw-semibold">Gerência de destino</label>
                            <select id="destination_management_unit_id" name="destination_management_unit_id" class="form-select" required>
                                <option value="">Selecione uma gerência ativa</option>
                                <?php foreach ($activeManagementUnits as $managementUnit): ?>
                                    <?php $managementId = (int) ($managementUnit['id'] ?? 0); ?>
                                    <?php if ($managementId !== $sourceId): ?>
                                        <option value="<?= $managementId ?>"><?= esc((string) ($managementUnit['name'] ?? '')) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input id="selectAllHosts" type="checkbox" class="form-check-input">
                        <label for="selectAllHosts" class="form-check-label fw-semibold">Selecionar todos os hosts</label>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                            <tr>
                                <th class="text-center">Selecionar</th>
                                <th>Host</th>
                                <th>Responsável técnico atual</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($hosts as $host): ?>
                                <tr>
                                    <td class="text-center">
                                        <input name="vms[]" value="<?= esc((string) ($host['vm'] ?? ''), 'attr') ?>" type="checkbox" class="form-check-input host-checkbox">
                                    </td>
                                    <td><?= esc((string) ($host['vm'] ?? '')) ?></td>
                                    <td><?= esc((string) ($host['technical_responsible_name'] ?? 'Sem registro')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-warning">
                        Se o responsável técnico atual não estiver vinculado à gerência de destino, o vínculo técnico do host será removido.
                    </div>
                    <button type="submit" class="btn btn-brand" onclick="return confirm('Migrar os hosts selecionados para a gerência de destino?');">Migrar hosts</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const selectAllHosts = document.getElementById('selectAllHosts');
if (selectAllHosts) {
  const hostCheckboxes = Array.from(document.querySelectorAll('.host-checkbox'));
  const updateSelectAll = () => {
    selectAllHosts.checked = hostCheckboxes.length > 0 && hostCheckboxes.every((checkbox) => checkbox.checked);
    selectAllHosts.indeterminate = hostCheckboxes.some((checkbox) => checkbox.checked) && !selectAllHosts.checked;
  };
  selectAllHosts.addEventListener('change', () => {
    hostCheckboxes.forEach((checkbox) => { checkbox.checked = selectAllHosts.checked; });
    selectAllHosts.indeterminate = false;
  });
  hostCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelectAll));
}
</script>
</body>
</html>
