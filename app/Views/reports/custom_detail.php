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

<?= view('reports/_host_detail_modal', [
    'canEditHosts' => $canEditHosts,
    'managementUnits' => $managementUnits,
    'operatingSystems' => $operatingSystems,
    'formAction' => site_url('reports/host-info'),
    'assetRiskPlaceholder' => '',
    'hiddenFields' => ['return_to' => $returnTo],
]) ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?= view('reports/_host_detail_script', [
    'canEditHosts' => $canEditHosts,
    'technicalResponsiblesByManagementUnit' => $technicalResponsiblesByManagementUnit,
]) ?>
</body>
</html>
