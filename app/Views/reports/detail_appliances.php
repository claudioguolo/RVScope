<?php $canEditHosts = \App\Libraries\UserAuthorization::canEditHosts(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Detalhe de Appliances</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Detalhe diario de VMs marcadas como appliance.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?php
    $legacyOnly = !empty($legacyOnly);
    $allGerencias = !empty($allGerencias);
    $pageBackUrl = $allGerencias
        ? site_url('reports/appliances/todos')
        : site_url('reports/appliances' . ($legacyOnly ? '?legacy=1' : ''));
    $pageBackLabel = $allGerencias
        ? 'Appliances - Todos'
        : ($legacyOnly ? 'Appliances por Gerência - Legados' : 'Appliances por Gerência');
    ?>
    <?= view('reports/_topbar', [
        'subtitle' => $legacyOnly
            ? 'Detalhe diario de appliances legados.'
            : 'Detalhe diario de VMs marcadas como appliance.',
        'activeMenu' => 'relatorios',
        'activeSubmenu' => $allGerencias
            ? 'appliances-todos'
            : ($legacyOnly ? 'appliances-gerencia-legados' : 'appliances-gerencia'),
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Relatórios'],
            ['label' => 'Appliances'],
            ['label' => $pageBackLabel, 'url' => $pageBackUrl],
            ['label' => 'Detalhes', 'active' => true],
        ],
    ]) ?>

    <?php if (!empty($alert)): ?>
        <div class="alert alert-<?= esc($alert['type'] ?? 'info') ?>">
            <?= esc($alert['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-warning"><?= esc($error) ?></div>
    <?php else: ?>
        <div class="app-card p-3">
            <h5 class="mb-3">
                Data: <?= esc($date) ?> - Gerência: <?= esc($allGerencias ? 'Todas' : $gerencia) ?> - Filtro: Appliances<?= $legacyOnly ? ' Legados' : '' ?>
            </h5>

            <form method="post" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= esc($date, 'attr') ?>">
                <input type="hidden" name="gerencia_filter" value="<?= esc($allGerencias ? '' : $gerencia, 'attr') ?>">
                <?php if ($legacyOnly): ?>
                    <input type="hidden" name="legacy_filter" value="1">
                <?php endif; ?>
                <button type="submit" name="export" value="1" class="btn btn-brand">Exportar CSV</button>
            </form>

            <?php if (empty($rows)): ?>
                <div class="alert alert-info">Nenhum registro encontrado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Name VMWare</th>
                            <th>DNS</th>
                            <th>IP</th>
                            <th>OS</th>
                            <th>Creation</th>
                            <th>Info</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $index => $row): ?>
                            <?php $info = $row['info'] ?? []; ?>
                            <?php $isNew = !empty($newVmMap[$row['vm'] ?? '']); ?>
                            <?php $isRemoved = !empty($row['is_removed']); ?>
                            <tr class="<?= $isRemoved ? 'text-decoration-line-through text-body-secondary' : '' ?>">
                                <td><?= esc((string) ($index + 1)) ?></td>
                                <td>
                                    <?= esc($row['vm'] ?? '') ?>
                                    <?php if (($info['worker'] ?? 'none') === 'rancher'): ?>
                                        <span class="small ms-2 text-primary-emphasis" style="opacity:0.65">Rancher</span>
                                    <?php elseif (($info['worker'] ?? 'none') === 'openshift'): ?>
                                        <span class="small ms-2 text-danger-emphasis" style="opacity:0.65">OpenShift</span>
                                    <?php endif; ?>
                                    <?php if ($isNew): ?>
                                        <span class="text-danger small ms-2" title="VM nova">&#9679;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($row['dns'] ?? '') ?></td>
                                <td><?= esc($row['ip'] ?? '') ?></td>
                                <td><?= esc($row['os'] ?? '') ?></td>
                                <td><?= esc($row['creation'] ?? '') ?></td>
                                <td>
                                    <?php if ($isRemoved): ?>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm text-decoration-none"
                                            data-bs-toggle="modal"
                                            data-bs-target="#removalReasonModal"
                                            data-vm="<?= esc($row['vm'] ?? '', 'attr') ?>"
                                            data-removal-reason="<?= esc($row['removal_reason'] ?? '', 'attr') ?>"
                                        >Removido</button>
                                    <?php else: ?>
                                    <button
                                        type="button"
                                        class="btn btn-brand btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#infoModal"
                                        data-vm="<?= esc($row['vm'] ?? '', 'attr') ?>"
                                        data-desc="<?= esc($info['desc'] ?? 'Sem registro', 'attr') ?>"
                                        data-gerencia="<?= esc($info['gerencia'] ?? 'Sem registro', 'attr') ?>"
                                        data-management-unit-id="<?= (int) ($info['management_unit_id'] ?? 0) ?>"
                                        data-owner="<?= esc($info['owner'] ?? 'Sem registro', 'attr') ?>"
                                        data-technical-responsible-id="<?= (int) ($info['technical_responsible_id'] ?? 0) ?>"
                                        data-conv="<?= esc($info['conv'] ?? 'Nao informado', 'attr') ?>"
                                        data-leg="<?= esc($info['leg'] ?? '0', 'attr') ?>"
                                        data-migration-target="<?= esc($info['migration_target'] ?? (($info['mig'] ?? '0') === '1' ? 'other_host' : 'none'), 'attr') ?>"
                                        data-app="<?= esc($info['app'] ?? '0', 'attr') ?>"
                                        data-worker="<?= esc($info['worker'] ?? 'none', 'attr') ?>"
                                        data-creation="<?= esc($row['creation'] ?? '', 'attr') ?>"
                                        data-os-last-update-date="<?= esc($info['os_last_update_date'] ?? '', 'attr') ?>"
                                        data-annotation="<?= esc($row['annotation'] ?? '', 'attr') ?>"
                                    ><?= $canEditHosts ? 'Detalhes / Editar' : 'Detalhes' ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalLabel">Informacoes da VM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="save_info" value="1">
                <input type="hidden" name="date" value="<?= esc($date, 'attr') ?>">
                <input type="hidden" name="gerencia_filter" value="<?= esc($allGerencias ? '' : $gerencia, 'attr') ?>">
                <?php if ($legacyOnly): ?>
                    <input type="hidden" name="legacy_filter" value="1">
                <?php endif; ?>

                <label class="form-label">Name VMWare</label>
                <input id="vm" name="vm" class="form-control" readonly>

                <label class="form-label mt-2">Descricao</label>
                <textarea id="desc" name="desc" class="form-control" rows="3"></textarea>

                <?= view('reports/_host_assignment_fields', ['managementUnits' => $managementUnits]) ?>

                <label class="form-label mt-2">Conversando</label>
                <textarea id="conv" name="conv" class="form-control" rows="3"></textarea>

                <div class="row mt-3">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="legacy" name="legacy" value="1">
                            <label class="form-check-label" for="legacy">Legado</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="appliance" name="appliance" value="1">
                            <label class="form-check-label" for="appliance">Appliance</label>
                        </div>
                    </div>
                </div>

                <label class="form-label mt-3" for="migration_target">Migração</label>
                <select id="migration_target" name="migration_target" class="form-select">
                    <option value="none">Não migrável</option>
                    <option value="other_host">Outro Host</option>
                    <option value="openshift">OpenShift</option>
                </select>

                <label class="form-label mt-3" for="worker">Worker</label>
                <select id="worker" name="worker" class="form-select">
                    <option value="none">Nenhum</option>
                    <option value="openshift">OpenShift</option>
                    <option value="rancher">Rancher</option>
                </select>

                <label class="form-label mt-3">Criacao (dd/mm/aaaa)</label>
                <input id="creation_date" name="creation_date" class="form-control" maxlength="10" placeholder="dd/mm/aaaa">

                <label class="form-label mt-3" for="os_last_update_date">Última atualização do SO</label>
                <input id="os_last_update_date" name="os_last_update_date" type="date" class="form-control">

                <label class="form-label mt-3">VCenter Notes</label>
                <textarea id="annotation" class="form-control" rows="3" readonly></textarea>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Fechar</button>
                <?php if ($canEditHosts): ?>
                    <button class="btn btn-brand" type="submit">Salvar</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?= view('reports/_removal_reason_modal', ['date' => $date]) ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const infoModal = document.getElementById('infoModal');
const canEditHostAssignments = <?= $canEditHosts ? 'true' : 'false' ?>;
const technicalResponsiblesByManagementUnit = <?= json_encode(
    $technicalResponsiblesByManagementUnit,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
const managementUnitSelect = document.getElementById('management_unit_id');
const technicalResponsibleSelect = document.getElementById('technical_responsible_id');
function updateTechnicalResponsibleOptions(managementUnitId, selectedResponsibleId = '0') {
  const responsibles = technicalResponsiblesByManagementUnit[managementUnitId] || [];
  technicalResponsibleSelect.innerHTML = '<option value="0">Sem registro</option>';
  responsibles.forEach((responsible) => {
    const option = document.createElement('option');
    option.value = String(responsible.id);
    option.textContent = responsible.name;
    technicalResponsibleSelect.appendChild(option);
  });
  technicalResponsibleSelect.value = String(selectedResponsibleId);
  if (technicalResponsibleSelect.value !== String(selectedResponsibleId)) {
    technicalResponsibleSelect.value = '0';
  }
  technicalResponsibleSelect.disabled = !canEditHostAssignments || String(managementUnitId) === '0';
}
managementUnitSelect.addEventListener('change', () => {
  updateTechnicalResponsibleOptions(managementUnitSelect.value);
});
if (infoModal && !<?= $canEditHosts ? 'true' : 'false' ?>) {
  infoModal.querySelectorAll('input:not([type="hidden"]), textarea:not(#annotation), select')
    .forEach((field) => { field.disabled = true; });
}
if (infoModal) {
  infoModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) {
      return;
    }
    document.getElementById('vm').value = button.getAttribute('data-vm') || '';
    document.getElementById('desc').value = button.getAttribute('data-desc') || '';
    const managementUnitId = button.getAttribute('data-management-unit-id') || '0';
    managementUnitSelect.value = managementUnitId;
    updateTechnicalResponsibleOptions(
      managementUnitId,
      button.getAttribute('data-technical-responsible-id') || '0'
    );
    document.getElementById('conv').value = button.getAttribute('data-conv') || '';
    document.getElementById('legacy').checked = (button.getAttribute('data-leg') === '1');
    document.getElementById('migration_target').value = button.getAttribute('data-migration-target') || 'none';
    document.getElementById('appliance').checked = (button.getAttribute('data-app') === '1');
    document.getElementById('worker').value = button.getAttribute('data-worker') || 'none';
    document.getElementById('creation_date').value = button.getAttribute('data-creation') || '';
    document.getElementById('os_last_update_date').value = button.getAttribute('data-os-last-update-date') || '';
    document.getElementById('annotation').value = button.getAttribute('data-annotation') || '';
  });
}
</script>
</body>
</html>
