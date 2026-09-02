<div class="form-check mt-3">
    <input class="form-check-input" type="checkbox" id="has_contract" name="has_contract" value="1">
    <label class="form-check-label" for="has_contract">Existe Contrato</label>
</div>

<div id="contract_details" class="collapse border rounded p-3 mt-2" aria-hidden="true">
    <label class="form-label" for="contract">Contrato</label>
    <input
        id="contract"
        name="contract"
        type="text"
        class="form-control"
        maxlength="500"
        placeholder="Informações do contrato com terceiros"
        disabled
    >

    <label class="form-label mt-3" for="contract_valid_until">Validade</label>
    <input
        id="contract_valid_until"
        name="contract_valid_until"
        type="date"
        class="form-control"
        disabled
    >
</div>
