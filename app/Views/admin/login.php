<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Acesso ao controle de usuários.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Entrada protegida para a futura area de controle de usuarios.',
        'activeMenu' => 'usuarios',
        'showAdminShortcut' => false,
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Login', 'active' => true],
        ],
    ]) ?>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7 col-xl-6">
            <div class="app-card p-4 p-lg-5">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <span class="app-eyebrow">Area administrativa</span>
                        <h1 class="h3 mt-2 mb-2">Login do controle de usuários</h1>
                        <p class="text-secondary mb-0">
                            O acesso a esta tela ja foi validado por autenticacao administrativa.
                            Este sera o ponto de entrada para o gerenciamento de usuarios da aplicacao.
                        </p>
                    </div>
                    <div class="app-gear-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M19.14 12.94c.04-.31.06-.63.06-.94s-.02-.63-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.2 7.2 0 0 0-1.63-.94l-.36-2.54a.5.5 0 0 0-.49-.42h-3.84a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.13.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.7 8.84a.5.5 0 0 0 .12.64l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94L2.82 14.52a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.71 1.63.94l.36 2.54a.5.5 0 0 0 .49.42h3.84a.5.5 0 0 0 .49-.42l.36-2.54c.58-.23 1.13-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/>
                        </svg>
                    </div>
                </div>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger"><?= esc($errorMessage) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('admin/login') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label for="user-login" class="form-label fw-semibold">Usuário</label>
                        <input id="user-login" name="username" class="form-control form-control-lg" type="text" value="<?= esc($initialAdminUser, 'attr') ?>" autocomplete="username" required>
                    </div>
                    <div class="col-12">
                        <label for="user-password" class="form-label fw-semibold">Senha</label>
                        <input id="user-password" name="password" class="form-control form-control-lg" type="password" placeholder="Sua senha" autocomplete="current-password" required>
                    </div>
                    <div class="col-12 d-flex flex-column flex-sm-row gap-2 pt-2">
                        <button type="submit" class="btn btn-brand btn-lg">Entrar</button>
                        <a href="<?= site_url('/') ?>" class="btn btn-outline-secondary btn-lg">Voltar</a>
                    </div>
                </form>

                <div class="app-inline-note mt-4">
                    <strong>Entrada administrativa validada para:</strong>
                    <?= esc($gateUser) ?>
                </div>
                <div class="app-inline-note mt-3">
                    <strong>Admin inicial previsto:</strong>
                    <?= esc($initialAdminUser) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
