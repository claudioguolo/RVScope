<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Filtros de sistemas operacionais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Configuração de sistemas operacionais ignorados.">
    <?= view('reports/_theme') ?>
    <style>
        .os-policy-list { max-height: 65vh; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Configuração dos sistemas operacionais incluídos nos relatórios.',
        'activeMenu' => 'administracao',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Administração'],
            ['label' => 'Sistemas operacionais', 'active' => true],
        ],
    ]) ?>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/users') ?>">Usuários e configurações</a>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/catalogs') ?>">Gerências e responsáveis</a>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/host-management-migration') ?>">Migrar hosts</a>
        <a class="btn btn-brand" href="<?= site_url('admin/operating-systems') ?>">Sistemas operacionais</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= esc($message) ?></div>
    <?php endif; ?>

    <div class="app-card p-4">
        <div class="mb-4">
            <span class="app-eyebrow">Filtros do inventário</span>
            <h1 class="h4 mt-2 mb-2">Sistemas operacionais ignorados</h1>
            <p class="text-secondary mb-0">
                Marque os sistemas que não devem entrar nos relatórios. Ao salvar, todo o histórico será recalculado.
                VMs desligadas continuam excluídas independentemente desta configuração.
            </p>
        </div>

        <?php if ($operatingSystems === []): ?>
            <div class="alert alert-info mb-0">Nenhum sistema operacional foi detectado nos CSVs importados.</div>
        <?php else: ?>
            <form method="post" action="<?= site_url('admin/operating-systems') ?>">
                <?= csrf_field() ?>
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-12 col-lg">
                        <label for="os_policy_search" class="form-label fw-semibold">Pesquisar</label>
                        <input id="os_policy_search" class="form-control" type="search" placeholder="Digite parte do nome do sistema operacional">
                    </div>
                    <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                        <button id="select_visible" type="button" class="btn btn-outline-secondary">Marcar visíveis</button>
                        <button id="clear_visible" type="button" class="btn btn-outline-secondary">Desmarcar visíveis</button>
                    </div>
                </div>

                <div class="table-responsive border rounded os-policy-list">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 7rem">Ignorar</th>
                            <th>Sistema operacional detectado</th>
                            <th class="text-end">VMs</th>
                            <th class="text-end">Registros</th>
                            <th>Última detecção</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($operatingSystems as $index => $operatingSystem): ?>
                            <?php
                            $name = (string) ($operatingSystem['os_name'] ?? '');
                            $ignored = in_array(
                                $operatingSystem['is_ignored'] ?? false,
                                [true, 1, '1', 't', 'true'],
                                true
                            );
                            $lastSeen = (string) ($operatingSystem['last_seen'] ?? '');
                            $date = DateTime::createFromFormat('Y-m-d', $lastSeen);
                            if ($date !== false) {
                                $lastSeen = $date->format('d/m/Y');
                            }
                            ?>
                            <tr data-os-row data-search="<?= esc(mb_strtolower($name, 'UTF-8'), 'attr') ?>">
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input os-policy-checkbox" type="checkbox"
                                               id="ignored_os_<?= $index ?>" name="ignored_os[]"
                                               value="<?= esc($name, 'attr') ?>" <?= $ignored ? 'checked' : '' ?>>
                                        <label class="visually-hidden" for="ignored_os_<?= $index ?>">Ignorar <?= esc($name) ?></label>
                                    </div>
                                </td>
                                <td><?= esc($name) ?></td>
                                <td class="text-end"><?= (int) ($operatingSystem['vm_count'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($operatingSystem['snapshot_count'] ?? 0) ?></td>
                                <td><?= esc($lastSeen) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                    <span class="text-secondary"><span id="ignored_count">0</span> sistema(s) marcado(s) para ignorar.</span>
                    <button type="submit" class="btn btn-brand" onclick="return confirm('Salvar os filtros e recalcular todo o histórico de relatórios?');">Salvar e recalcular relatórios</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const search = document.getElementById('os_policy_search');
    const rows = Array.from(document.querySelectorAll('[data-os-row]'));
    const checkboxes = Array.from(document.querySelectorAll('.os-policy-checkbox'));
    const count = document.getElementById('ignored_count');
    if (!search || rows.length === 0) {
        return;
    }
    const updateCount = () => {
        count.textContent = String(checkboxes.filter((checkbox) => checkbox.checked).length);
    };
    const visibleCheckboxes = () => rows
        .filter((row) => !row.classList.contains('d-none'))
        .map((row) => row.querySelector('.os-policy-checkbox'));
    search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase('pt-BR');
        rows.forEach((row) => row.classList.toggle('d-none', !row.dataset.search.includes(term)));
    });
    document.getElementById('select_visible').addEventListener('click', () => {
        visibleCheckboxes().forEach((checkbox) => { checkbox.checked = true; });
        updateCount();
    });
    document.getElementById('clear_visible').addEventListener('click', () => {
        visibleCheckboxes().forEach((checkbox) => { checkbox.checked = false; });
        updateCount();
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    updateCount();
})();
</script>
</body>
</html>
