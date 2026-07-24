<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Autenticação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Autenticação necessária para acessar os relatórios.',
        'activeMenu' => '',
        'showAdminShortcut' => false,
        'breadcrumbs' => [
            ['label' => 'Autenticação', 'active' => true],
        ],
    ]) ?>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            <div class="app-card p-4">
                <span class="app-eyebrow">Acesso</span>
                <h1 class="h4 mt-2 mb-2">Entrar no RVScope</h1>
                <p class="text-secondary">
                    Use sua conta local<?= ! empty($adEnabled) ? ' ou suas credenciais do Active Directory' : '' ?>.
                </p>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger"><?= esc($errorMessage) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('auth/login') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label for="username" class="form-label fw-semibold">Usuário</label>
                        <input id="username" name="username" class="form-control" type="text" autocomplete="username" required autofocus>
                    </div>
                    <div class="col-12">
                        <label for="password" class="form-label fw-semibold">Senha</label>
                        <input id="password" name="password" class="form-control" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-brand w-100">Entrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
