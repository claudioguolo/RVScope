<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Configuração de perfil administrativo.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Configuracao do perfil autenticado.',
        'activeMenu' => 'usuarios',
        'showAdminShortcut' => true,
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Administração'],
            ['label' => 'Perfil', 'active' => true],
        ],
    ]) ?>

    <div class="row g-4 justify-content-center">
        <div class="col-12 col-xl-5">
            <div class="app-card p-4 h-100">
                <span class="app-eyebrow">Perfil</span>
                <h1 class="h3 mt-2 mb-4">Dados do perfil</h1>

                <?php if ($profileError): ?>
                    <div class="alert alert-danger"><?= esc($profileError) ?></div>
                <?php endif; ?>

                <?php if ($profileMessage): ?>
                    <div class="alert alert-success"><?= esc($profileMessage) ?></div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="app-inline-note h-100">
                            <div class="small text-secondary">Usuario</div>
                            <div class="fw-semibold"><?= esc($username) ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="app-inline-note h-100">
                            <div class="small text-secondary">Perfil</div>
                            <div class="fw-semibold"><?= esc($role) ?></div>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?= site_url('admin/profile') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label for="display_name" class="form-label fw-semibold">Nome exibido</label>
                        <input id="display_name" name="display_name" class="form-control" type="text" maxlength="120" value="<?= esc($displayName, 'attr') ?>" required>
                    </div>
                    <div class="col-12 d-flex flex-column flex-sm-row gap-2">
                        <button type="submit" class="btn btn-brand">Salvar perfil</button>
                        <a class="btn btn-outline-secondary" href="<?= site_url('/') ?>">Voltar ao inicio</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="app-card p-4 h-100">
                <span class="app-eyebrow">Seguranca</span>
                <h2 class="h3 mt-2 mb-4">Alterar senha</h2>

                <?php if ($passwordError): ?>
                    <div class="alert alert-danger"><?= esc($passwordError) ?></div>
                <?php endif; ?>

                <?php if ($passwordMessage): ?>
                    <div class="alert alert-success"><?= esc($passwordMessage) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('admin/profile/password') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label for="current_password" class="form-label fw-semibold">Senha atual</label>
                        <input id="current_password" name="current_password" class="form-control" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="col-12">
                        <label for="new_password" class="form-label fw-semibold">Nova senha</label>
                        <input id="new_password" name="new_password" class="form-control" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="col-12">
                        <label for="confirm_password" class="form-label fw-semibold">Confirmar nova senha</label>
                        <input id="confirm_password" name="confirm_password" class="form-control" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-brand">Alterar senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
