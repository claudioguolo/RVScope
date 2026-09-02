const hasContractCheckbox = document.getElementById('has_contract');
const contractDetails = document.getElementById('contract_details');
const contractInput = document.getElementById('contract');
const contractValidUntilInput = document.getElementById('contract_valid_until');
const contractCollapse = bootstrap.Collapse.getOrCreateInstance(contractDetails, { toggle: false });

function updateContractState() {
    const hasContract = hasContractCheckbox.checked;
    if (hasContract) {
        contractCollapse.show();
    } else {
        contractCollapse.hide();
    }
    hasContractCheckbox.setAttribute('aria-expanded', hasContract ? 'true' : 'false');
    contractDetails.setAttribute('aria-hidden', hasContract ? 'false' : 'true');
    contractInput.disabled = !canEditHostAssignments || !hasContract;
    contractInput.required = canEditHostAssignments && hasContract;
    contractValidUntilInput.disabled = !canEditHostAssignments || !hasContract;
}

function loadContractFields(button) {
    hasContractCheckbox.checked = button.getAttribute('data-has-contract') === '1';
    contractInput.value = button.getAttribute('data-contract') || '';
    contractValidUntilInput.value = button.getAttribute('data-contract-valid-until') || '';
    updateContractState();
}

hasContractCheckbox.addEventListener('change', () => updateContractState());
