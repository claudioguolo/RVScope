<!doctype html>
<?php $canEditHosts = \App\Libraries\UserAuthorization::canEditHosts(); ?>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Detalhe do Relatório Personalizado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Hosts do Relatório Personalizado.">
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
            ['label' => 'Relatório Personalizado', 'url' => $backUrl],
            ['label' => $groupName, 'active' => true],
        ],
    ]) ?>

    <?php
    $displayDate = $criteria->date;
    $parsedDate = DateTime::createFromFormat('Y-m-d', $displayDate);
    if ($parsedDate !== false) {
        $displayDate = $parsedDate->format('d/m/Y');
    }
    $detailParameters = [
        'date' => $criteria->date,
        'group_by' => $criteria->groupBy,
        'group_name' => $groupName,
        'os' => $criteria->operatingSystems,
        'management_unit_id' => $criteria->managementUnitIds,
    ];
    foreach (['legacy', 'appliance', 'migrable'] as $flag) {
        if ($criteria->{$flag}) {
            $detailParameters[$flag] = '1';
        }
    }
    $exportParameters = $detailParameters + ['export' => 'csv'];
    $returnTo = 'reports/personalizado/detail?' . http_build_query($detailParameters);
    ?>

    <?php if (!empty($alert)): ?>
        <div class="alert alert-<?= esc((string) ($alert['type'] ?? 'info'), 'attr') ?>">
            <?= esc((string) ($alert['message'] ?? '')) ?>
        </div>
    <?php endif; ?>

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
                        <th>Info</th>
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
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-brand btn-sm text-nowrap"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoModal"
                                    data-vm="<?= esc((string) ($row['vm'] ?? ''), 'attr') ?>"
                                    data-desc="<?= esc((string) ($row['description'] ?? 'Sem registro'), 'attr') ?>"
                                    data-management-unit-id="<?= (int) ($row['management_unit_id'] ?? 0) ?>"
                                    data-management-unit-active="<?= in_array($row['management_unit_is_active'] ?? false, [true, 1, '1', 't', 'true'], true) ? '1' : '0' ?>"
                                    data-technical-responsible-id="<?= (int) ($row['technical_responsible_id'] ?? 0) ?>"
                                    data-technical-responsible-active="<?= in_array($row['technical_responsible_is_active'] ?? false, [true, 1, '1', 't', 'true'], true) ? '1' : '0' ?>"
                                    data-has-contract="<?= in_array($row['has_contract'] ?? false, [true, 1, '1', 't', 'true'], true) ? '1' : '0' ?>"
                                    data-contract="<?= esc((string) ($row['contract'] ?? ''), 'attr') ?>"
                                    data-contract-valid-until="<?= esc((string) ($row['contract_valid_until'] ?? ''), 'attr') ?>"
                                    data-asset-risk-score="<?= esc((string) ($row['asset_risk_score'] ?? ''), 'attr') ?>"
                                    data-operating-system-override="<?= esc((string) ($row['operating_system_override'] ?? ''), 'attr') ?>"
                                    data-conv="<?= esc((string) ($row['conv'] ?? 'Nao informado'), 'attr') ?>"
                                    data-leg="<?= (int) ($row['leg'] ?? 0) ?>"
                                    data-migration-target="<?= esc((string) ($row['migration_target'] ?? 'none'), 'attr') ?>"
                                    data-app="<?= (int) ($row['app'] ?? 0) ?>"
                                    data-worker="<?= esc((string) ($row['worker'] ?? 'none'), 'attr') ?>"
                                    data-creation="<?= esc((string) ($row['host_creation_date'] ?? ''), 'attr') ?>"
                                    data-os-last-update-date="<?= esc((string) ($row['os_last_update_date'] ?? ''), 'attr') ?>"
                                    data-annotation="<?= esc((string) ($row['annotation'] ?? ''), 'attr') ?>"
                                ><?= $canEditHosts ? 'Detalhes / Editar' : 'Detalhes' ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('reports/host-info') ?>" class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalLabel">Informações da VM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">

                <label class="form-label" for="vm">Name VMWare</label>
                <input id="vm" name="vm" class="form-control" readonly>

                <label class="form-label mt-2" for="desc">Descrição</label>
                <textarea id="desc" name="desc" class="form-control" rows="3"></textarea>

                <?= view('reports/_host_assignment_fields', ['managementUnits' => $managementUnits, 'operatingSystems' => $operatingSystems]) ?>
                <?= view('reports/_inactive_assignment_warning') ?>

                <label class="form-label mt-2" for="asset_risk_score">Asset risk score (ASTI)</label>
                <input id="asset_risk_score" name="asset_risk_score" type="text" class="form-control" maxlength="160">

                <label class="form-label mt-2" for="conv">Conversando</label>
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
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="has_contract" name="has_contract" value="1">
                            <label class="form-check-label" for="has_contract">Existe Contrato</label>
                        </div>
                    </div>
                </div>

                <?= view('reports/_host_contract_fields') ?>

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

                <label class="form-label mt-3" for="creation_date">Criação (dd/mm/aaaa)</label>
                <input id="creation_date" name="creation_date" class="form-control" maxlength="10" placeholder="dd/mm/aaaa">

                <label class="form-label mt-3" for="os_last_update_date">Última atualização do SO</label>
                <input id="os_last_update_date" name="os_last_update_date" type="date" class="form-control">

                <label class="form-label mt-3" for="annotation">VCenter Notes</label>
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
<?= view('reports/_inactive_assignment_warning_script') ?>
<?= view('reports/_host_contract_script') ?>

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
if (infoModal && !canEditHostAssignments) {
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
        updateInactiveAssignmentWarning(button);
        const managementUnitId = button.getAttribute('data-management-unit-id') || '0';
        managementUnitSelect.value = managementUnitId;
        const availableManagementUnitId = managementUnitSelect.value || '0';
        updateTechnicalResponsibleOptions(
            availableManagementUnitId,
            availableManagementUnitId === '0' ? '0' : (button.getAttribute('data-technical-responsible-id') || '0')
        );
        loadContractFields(button);
        document.getElementById('asset_risk_score').value = button.getAttribute('data-asset-risk-score') || '';
        document.getElementById('operating_system_override').value = button.getAttribute('data-operating-system-override') || '';
        document.getElementById('conv').value = button.getAttribute('data-conv') || '';
        document.getElementById('legacy').checked = button.getAttribute('data-leg') === '1';
        document.getElementById('migration_target').value = button.getAttribute('data-migration-target') || 'none';
        document.getElementById('appliance').checked = button.getAttribute('data-app') === '1';
        document.getElementById('worker').value = button.getAttribute('data-worker') || 'none';
        document.getElementById('creation_date').value = button.getAttribute('data-creation') || '';
        document.getElementById('os_last_update_date').value = button.getAttribute('data-os-last-update-date') || '';
        document.getElementById('annotation').value = button.getAttribute('data-annotation') || '';
    });
}
</script>
</body>
</html>
