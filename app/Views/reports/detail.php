<?php $canEditHosts = \App\Libraries\UserAuthorization::canEditHosts(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Detalhe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Detalhe diario por VM e anotacoes.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Detalhe diario por VM e anotacoes.',
        'activeMenu' => 'inicio',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
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
                Data: <?= esc($date) ?> - SO: <?= $osName !== '' ? esc($osName) : 'Todos' ?>
            </h5>

            <form method="post" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= esc($date, 'attr') ?>">
                <input type="hidden" name="os" value="<?= esc($osName, 'attr') ?>">
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
                                        data-management-unit-active="<?= !empty($info['management_unit_is_active']) ? '1' : '0' ?>"
                                        data-owner="<?= esc($info['owner'] ?? 'Sem registro', 'attr') ?>"
                                        data-technical-responsible-id="<?= (int) ($info['technical_responsible_id'] ?? 0) ?>"
                                        data-technical-responsible-active="<?= !empty($info['technical_responsible_is_active']) ? '1' : '0' ?>"
                                        data-has-contract="<?= !empty($info['has_contract']) ? '1' : '0' ?>"
                                        data-contract="<?= esc($info['contract'] ?? '', 'attr') ?>"
                                        data-contract-valid-until="<?= esc($info['contract_valid_until'] ?? '', 'attr') ?>"
                                        data-asset-risk-score="<?= esc($info['asset_risk_score'] ?? '', 'attr') ?>"
                                        data-operating-system-override="<?= esc($info['operating_system_override'] ?? '', 'attr') ?>"
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

<?= view('reports/_host_detail_modal', [
    'canEditHosts' => $canEditHosts,
    'managementUnits' => $managementUnits,
    'operatingSystems' => $operatingSystems,
    'hiddenFields' => [
        'save_info' => '1',
        'date' => $date,
        'os' => $osName,
    ],
]) ?>


<?= view('reports/_removal_reason_modal', ['date' => $date]) ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?= view('reports/_host_detail_script', [
    'canEditHosts' => $canEditHosts,
    'technicalResponsiblesByManagementUnit' => $technicalResponsiblesByManagementUnit,
]) ?>
</body>
</html>
