<label class="form-label mt-2" for="management_unit_id">Gerência</label>
<select id="management_unit_id" name="management_unit_id" class="form-select">
    <option value="0">Sem registro</option>
    <?php foreach ($managementUnits as $managementUnit): ?>
        <option value="<?= (int) ($managementUnit['id'] ?? 0) ?>">
            <?= esc((string) ($managementUnit['name'] ?? '')) ?>
        </option>
    <?php endforeach; ?>
</select>

<label class="form-label mt-2" for="technical_responsible_id">Responsável Técnico</label>
<select id="technical_responsible_id" name="technical_responsible_id" class="form-select">
    <option value="0">Sem registro</option>
</select>

<label class="form-label mt-2" for="operating_system_override">Sistema operacional fixado</label>
<select id="operating_system_override" name="operating_system_override" class="form-select">
    <option value="">Usar o valor detectado pelo RVTools</option>
    <?php foreach (($operatingSystems ?? []) as $operatingSystem): ?>
        <option value="<?= esc((string) $operatingSystem, 'attr') ?>"><?= esc((string) $operatingSystem) ?></option>
    <?php endforeach; ?>
</select>
<div class="form-text">Quando selecionado, este SO terá precedência em todas as datas e relatórios desta VM.</div>
