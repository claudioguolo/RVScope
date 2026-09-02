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
            availableManagementUnitId === '0'
                ? '0'
                : (button.getAttribute('data-technical-responsible-id') || '0')
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
