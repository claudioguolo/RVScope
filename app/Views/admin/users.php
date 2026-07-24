<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Gerenciamento inicial de usuários administrativos.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Primeiras configuracoes do espaco administrativo.',
        'activeMenu' => 'usuarios',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Administração'],
            ['label' => 'Usuários', 'active' => true],
        ],
    ]) ?>

    <div class="app-card p-4 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg">
                <span class="app-eyebrow">Segurança</span>
                <h1 class="h4 mt-2 mb-2">Acesso autenticado aos relatórios</h1>
                <p class="text-secondary mb-0">
                    Quando habilitado, a página inicial e todos os relatórios exigem uma sessão
                    administrativa autenticada. Quando desabilitado, os relatórios permanecem públicos.
                </p>
            </div>
            <div class="col-12 col-lg-auto">
                <form method="post" action="<?= site_url('admin/settings/authenticated-reports') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="authenticated_reports_enabled" value="0">
                    <div class="form-check form-switch d-flex align-items-center gap-2 mb-3">
                        <input
                            class="form-check-input mt-0"
                            type="checkbox"
                            role="switch"
                            id="authenticated_reports_enabled"
                            name="authenticated_reports_enabled"
                            value="1"
                            <?= ! empty($authenticatedReportsEnabled) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label fw-semibold" for="authenticated_reports_enabled">
                            Exigir login
                        </label>
                    </div>
                    <button type="submit" class="btn btn-brand w-100">Salvar configuração</button>
                </form>
            </div>
        </div>

        <?php if ($settingsError): ?>
            <div class="alert alert-danger mt-4 mb-0"><?= esc($settingsError) ?></div>
        <?php endif; ?>

        <?php if ($settingsMessage): ?>
            <div class="alert alert-success mt-4 mb-0"><?= esc($settingsMessage) ?></div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-5">
            <div class="app-card p-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <span class="app-eyebrow">Novo usuário</span>
                        <h1 class="h4 mt-2 mb-2">Criar acesso administrativo</h1>
                        <p class="text-secondary mb-0">
                            Use este formulário para cadastrar os primeiros administradores da aplicação.
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

                <?php if ($createdMessage): ?>
                    <div class="alert alert-success"><?= esc($createdMessage) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('admin/users') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label for="display_name" class="form-label fw-semibold">Nome</label>
                        <input id="display_name" name="display_name" class="form-control" type="text" required>
                    </div>
                    <div class="col-12">
                        <label for="username" class="form-label fw-semibold">Usuário</label>
                        <input id="username" name="username" class="form-control" type="text" placeholder="admin.local" required>
                    </div>
                    <div class="col-12">
                        <label for="password" class="form-label fw-semibold">Senha</label>
                        <input id="password" name="password" class="form-control" type="password" minlength="8" required>
                    </div>
                    <div class="col-12">
                        <label for="role" class="form-label fw-semibold">Perfil</label>
                        <select id="role" name="role" class="form-select">
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-column flex-sm-row gap-2 pt-2">
                        <button type="submit" class="btn btn-brand">Criar usuário</button>
                        <a href="<?= site_url('/') ?>" class="btn btn-outline-secondary">Voltar ao início</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="app-card p-4 h-100">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <span class="app-eyebrow">Acessos</span>
                        <h2 class="h4 mt-2 mb-1">Usuários cadastrados</h2>
                        <p class="text-secondary mb-0">
                            Sessão atual: <strong><?= esc($currentUser) ?></strong>
                        </p>
                    </div>
                    <form method="post" action="<?= site_url('admin/logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary">Sair</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Nome</th>
                            <th>Perfil</th>
                            <th>Último login</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc((string) ($user['username'] ?? '')) ?></td>
                                <td><?= esc((string) ($user['display_name'] ?? '')) ?></td>
                                <td><?= esc((string) ($user['role'] ?? 'admin')) ?></td>
                                <td><?= esc((string) (($user['last_login_at'] ?? '') ?: 'Nunca acessou')) ?></td>
                                <td>
                                    <?php if ((int) ($user['is_active'] ?? 0) === 1): ?>
                                        <span class="badge text-bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
