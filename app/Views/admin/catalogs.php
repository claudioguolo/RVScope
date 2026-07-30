<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Gerências e responsáveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Cadastro de gerências e responsáveis técnicos.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Cadastro de gerências e responsáveis técnicos.',
        'activeMenu' => 'administracao',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Administração'],
            ['label' => 'Gerências e responsáveis', 'active' => true],
        ],
    ]) ?>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/users') ?>">Usuários e configurações</a>
        <a class="btn btn-brand" href="<?= site_url('admin/catalogs') ?>">Gerências e responsáveis</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= esc($message) ?></div>
    <?php endif; ?>

    <div class="app-card p-4 mb-4">
        <div class="mb-4">
            <span class="app-eyebrow">Organização</span>
            <h1 class="h4 mt-2 mb-2">Cadastrar gerência</h1>
            <p class="text-secondary mb-0">
                Essas gerências ficarão disponíveis para associação nas informações dos hosts.
            </p>
        </div>
        <form method="post" action="<?= site_url('admin/catalogs/management-units') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-lg-6">
                <label for="management_name" class="form-label fw-semibold">Gerência</label>
                <input id="management_name" name="name" class="form-control" maxlength="160" required>
            </div>
            <div class="col-12 col-lg-6">
                <label for="department" class="form-label fw-semibold">Departamento</label>
                <input id="department" name="department" class="form-control" maxlength="160" required>
            </div>
            <div class="col-12 col-lg-6">
                <label for="manager_name" class="form-label fw-semibold">Gerente</label>
                <input id="manager_name" name="manager_name" class="form-control" maxlength="160" required>
            </div>
            <div class="col-12 col-lg-6">
                <label for="management_email" class="form-label fw-semibold">E-mail da gerência</label>
                <input id="management_email" name="management_email" type="email" class="form-control" maxlength="254" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-brand">Cadastrar gerência</button>
            </div>
        </form>
    </div>

    <div class="app-card p-4 mb-4">
        <div class="mb-4">
            <span class="app-eyebrow">Organização</span>
            <h2 class="h4 mt-2 mb-2">Gerências cadastradas</h2>
        </div>
        <?php if ($managementUnits === []): ?>
            <div class="alert alert-info mb-0">Nenhuma gerência cadastrada.</div>
        <?php else: ?>
            <div class="accordion" id="managementUnitsAccordion">
                <?php foreach ($managementUnits as $managementUnit): ?>
                    <?php $managementId = (int) ($managementUnit['id'] ?? 0); ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button
                                class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#management-unit-<?= $managementId ?>"
                            ><?= esc((string) ($managementUnit['name'] ?? '')) ?></button>
                        </h3>
                        <div id="management-unit-<?= $managementId ?>" class="accordion-collapse collapse" data-bs-parent="#managementUnitsAccordion">
                            <div class="accordion-body">
                                <form method="post" action="<?= site_url('admin/catalogs/management-units/' . $managementId) ?>" class="row g-3">
                                    <?= csrf_field() ?>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label fw-semibold">Gerência</label>
                                        <input name="name" class="form-control" maxlength="160" value="<?= esc((string) ($managementUnit['name'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label fw-semibold">Departamento</label>
                                        <input name="department" class="form-control" maxlength="160" value="<?= esc((string) ($managementUnit['department'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label fw-semibold">Gerente</label>
                                        <input name="manager_name" class="form-control" maxlength="160" value="<?= esc((string) ($managementUnit['manager_name'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label fw-semibold">E-mail da gerência</label>
                                        <input name="management_email" type="email" class="form-control" maxlength="254" value="<?= esc((string) ($managementUnit['management_email'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-outline-primary">Salvar alterações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="app-card p-4 mb-4">
        <div class="mb-4">
            <span class="app-eyebrow">Equipe técnica</span>
            <h2 class="h4 mt-2 mb-2">Cadastrar responsável técnico</h2>
            <p class="text-secondary mb-0">
                Um responsável pode ser vinculado a uma ou mais gerências.
            </p>
        </div>
        <form method="post" action="<?= site_url('admin/catalogs/technical-responsibles') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-lg-4">
                <label for="responsible_name" class="form-label fw-semibold">Nome</label>
                <input id="responsible_name" name="name" class="form-control" maxlength="160" required>
            </div>
            <div class="col-12 col-lg-4">
                <label for="responsible_phone" class="form-label fw-semibold">Telefone</label>
                <input id="responsible_phone" name="phone" class="form-control" maxlength="40" required>
            </div>
            <div class="col-12 col-lg-4">
                <label for="responsible_email" class="form-label fw-semibold">E-mail</label>
                <input id="responsible_email" name="email" type="email" class="form-control" maxlength="254" required>
            </div>
            <div class="col-12">
                <label for="responsible_management_units" class="form-label fw-semibold">Gerências</label>
                <select id="responsible_management_units" name="management_unit_ids[]" class="form-select" multiple size="5" required>
                    <?php foreach ($managementUnits as $managementUnit): ?>
                        <option value="<?= (int) ($managementUnit['id'] ?? 0) ?>">
                            <?= esc((string) ($managementUnit['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Use Ctrl ou Command para selecionar mais de uma gerência.</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-brand" <?= $managementUnits === [] ? 'disabled' : '' ?>>Cadastrar responsável</button>
            </div>
        </form>
    </div>

    <div class="app-card p-4">
        <div class="mb-4">
            <span class="app-eyebrow">Equipe técnica</span>
            <h2 class="h4 mt-2 mb-2">Responsáveis cadastrados</h2>
        </div>
        <?php if ($technicalResponsibles === []): ?>
            <div class="alert alert-info mb-0">Nenhum responsável técnico cadastrado.</div>
        <?php else: ?>
            <div class="accordion" id="technicalResponsiblesAccordion">
                <?php foreach ($technicalResponsibles as $responsible): ?>
                    <?php
                    $responsibleId = (int) ($responsible['id'] ?? 0);
                    $linkedManagementIds = array_map('intval', $managementIdsByResponsible[$responsibleId] ?? []);
                    ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button
                                class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#technical-responsible-<?= $responsibleId ?>"
                            ><?= esc((string) ($responsible['name'] ?? '')) ?></button>
                        </h3>
                        <div id="technical-responsible-<?= $responsibleId ?>" class="accordion-collapse collapse" data-bs-parent="#technicalResponsiblesAccordion">
                            <div class="accordion-body">
                                <form method="post" action="<?= site_url('admin/catalogs/technical-responsibles/' . $responsibleId) ?>" class="row g-3">
                                    <?= csrf_field() ?>
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label fw-semibold">Nome</label>
                                        <input name="name" class="form-control" maxlength="160" value="<?= esc((string) ($responsible['name'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label fw-semibold">Telefone</label>
                                        <input name="phone" class="form-control" maxlength="40" value="<?= esc((string) ($responsible['phone'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label fw-semibold">E-mail</label>
                                        <input name="email" type="email" class="form-control" maxlength="254" value="<?= esc((string) ($responsible['email'] ?? ''), 'attr') ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Gerências</label>
                                        <select name="management_unit_ids[]" class="form-select" multiple size="5" required>
                                            <?php foreach ($managementUnits as $managementUnit): ?>
                                                <?php $managementId = (int) ($managementUnit['id'] ?? 0); ?>
                                                <option value="<?= $managementId ?>" <?= in_array($managementId, $linkedManagementIds, true) ? 'selected' : '' ?>>
                                                    <?= esc((string) ($managementUnit['name'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-outline-primary">Salvar alterações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
