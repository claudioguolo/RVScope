<?php
$formAction = trim((string) ($formAction ?? ''));
$hiddenFields = is_array($hiddenFields ?? null) ? $hiddenFields : [];
$assetRiskPlaceholder = (string) ($assetRiskPlaceholder ?? 'Classificação ou finalidade do ativo');
?>
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post"<?= $formAction !== '' ? ' action="' . esc($formAction, 'attr') . '"' : '' ?> class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalLabel">Informações da VM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <?= csrf_field() ?>
                <?php foreach ($hiddenFields as $name => $value): ?>
                    <input type="hidden" name="<?= esc((string) $name, 'attr') ?>" value="<?= esc((string) $value, 'attr') ?>">
                <?php endforeach; ?>

                <label class="form-label" for="vm">Name VMWare</label>
                <input id="vm" name="vm" class="form-control" readonly>

                <label class="form-label mt-2" for="desc">Descrição</label>
                <textarea id="desc" name="desc" class="form-control" rows="3"></textarea>

                <?= view('reports/_host_assignment_fields', [
                    'managementUnits' => $managementUnits,
                    'operatingSystems' => $operatingSystems,
                ]) ?>
                <?= view('reports/_inactive_assignment_warning') ?>

                <label class="form-label mt-2" for="asset_risk_score">Asset risk score (ASTI)</label>
                <input id="asset_risk_score" name="asset_risk_score" type="text" class="form-control" maxlength="160" placeholder="<?= esc($assetRiskPlaceholder, 'attr') ?>">

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
