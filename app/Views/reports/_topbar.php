<?php
$subtitle = $subtitle ?? '';
$activeMenu = $activeMenu ?? 'inicio';
$activeSubmenu = $activeSubmenu ?? '';
$showAdminShortcut = (bool) ($showAdminShortcut ?? true);
$applicationStage = strtolower(trim((string) env('APP_STAGE', '')));
$imageTag = trim((string) env('APP_IMAGE_TAG', ''));
$environmentLabel = match (true) {
    ENVIRONMENT === 'development' => 'Ambiente de Desenvolvimento',
    $applicationStage === 'homologation' => 'Ambiente de Homologação',
    default => null,
};
$adminDisplayName = '';
$authenticatedDisplayName = '';
$authenticatedRole = strtolower(trim((string) session('auth_role')));
if ((bool) session('user_authenticated')) {
    $authenticatedDisplayName = trim((string) session('auth_display_name'));
    if ($authenticatedDisplayName === '') {
        $authenticatedDisplayName = trim((string) session('auth_username'));
    }
}
if ((bool) session('admin_logged_in')) {
    $adminDisplayName = trim((string) session('admin_display_name'));
    if ($adminDisplayName === '') {
        $adminDisplayName = trim((string) session('admin_username'));
    }
}
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Início', 'active' => true],
];
?>

<div class="app-header mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <div class="app-title h4 mb-0">RVScope</div>
                <?php if ($environmentLabel !== null || $imageTag !== ''): ?>
                    <div class="app-environment-badges">
                        <?php if ($environmentLabel !== null): ?>
                            <span class="app-environment-badge"><?= esc($environmentLabel) ?></span>
                        <?php endif; ?>
                        <?php if ($imageTag !== ''): ?>
                            <span class="app-environment-badge app-image-tag-badge">Imagem: <?= esc($imageTag) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($subtitle !== ''): ?>
                <div class="small text-white-50"><?= esc($subtitle) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($authenticatedDisplayName !== '' && $adminDisplayName === ''): ?>
            <div class="app-admin-cluster">
                <span class="app-admin-user"><?= esc($authenticatedDisplayName) ?></span>
                <form method="post" action="<?= site_url('auth/logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="app-login-link border-0">Sair</button>
                </form>
                <?php if ($showAdminShortcut && $authenticatedRole === 'admin'): ?>
                    <a class="app-settings-link" href="<?= site_url('admin/access') ?>" aria-label="Acessar administração">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M19.14 12.94c.04-.31.06-.63.06-.94s-.02-.63-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.2 7.2 0 0 0-1.63-.94l-.36-2.54a.5.5 0 0 0-.49-.42h-3.84a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.13.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.7 8.84a.5.5 0 0 0 .12.64l2.03 1.58c-.04.31-.06.63-.06.94s.02-.63.06-.94L2.82 14.52a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.71 1.63.94l.36 2.54c.04.24.24.42.49.42h3.84c.25 0 .45-.18.49-.42l.36-2.54c.58-.23 1.13-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php elseif ($showAdminShortcut): ?>
            <div class="app-admin-cluster">
                <?php if ($adminDisplayName !== ''): ?>
                    <div class="dropdown">
                        <button class="app-admin-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= esc($adminDisplayName, 'attr') ?>">
                            <?= esc($adminDisplayName) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end app-menu-dropdown">
                            <li>
                                <a class="dropdown-item" href="<?= site_url('admin/profile') ?>">Configurar perfil</a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= site_url('admin/logout') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item">Sair</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    <a class="app-settings-link" href="<?= site_url('admin/access') ?>" aria-label="Acessar administração">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M19.14 12.94c.04-.31.06-.63.06-.94s-.02-.63-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.2 7.2 0 0 0-1.63-.94l-.36-2.54a.5.5 0 0 0-.49-.42h-3.84a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.13.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.7 8.84a.5.5 0 0 0 .12.64l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94L2.82 14.52a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.71 1.63.94l.36 2.54a.5.5 0 0 0 .49.42h3.84a.5.5 0 0 0 .49-.42l.36-2.54c.58-.23 1.13-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <a class="app-settings-link" href="<?= site_url('admin/access') ?>" aria-label="Acessar administração">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M19.14 12.94c.04-.31.06-.63.06-.94s-.02-.63-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.2 7.2 0 0 0-1.63-.94l-.36-2.54a.5.5 0 0 0-.49-.42h-3.84a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.13.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.7 8.84a.5.5 0 0 0 .12.64l2.03 1.58c-.04.31-.06.63-.06.94s-.02.63-.06.94L2.82 14.52a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96c.5.4 1.05.71 1.63.94l.36 2.54c.04.24.24.42.49.42h3.84c.25 0 .45-.18.49-.42l.36-2.54c.58-.23 1.13-.54 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="app-card app-menu p-3 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <ul class="nav app-menu-nav">
            <li class="nav-item">
                <a class="nav-link <?= $activeMenu === 'inicio' ? 'active' : '' ?>"
                   <?= $activeMenu === 'inicio' ? 'aria-current="page"' : '' ?>
                   href="<?= site_url('/') ?>">Início</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?= $activeMenu === 'relatorios' ? 'active' : '' ?>"
                   href="#"
                   role="button"
                   data-bs-toggle="dropdown"
                   aria-expanded="false">Relatórios</a>
                <ul class="dropdown-menu app-menu-dropdown">
                    <li><h6 class="dropdown-header">Appliances</h6></li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'appliances-todos' ? 'active' : '' ?>"
                           href="<?= site_url('reports/appliances/todos') ?>">Todos</a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'appliances-gerencia' ? 'active' : '' ?>"
                           href="<?= site_url('reports/appliances') ?>">Por Gerência</a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'appliances-gerencia-legados' ? 'active' : '' ?>"
                           href="<?= site_url('reports/appliances?legacy=1') ?>">Por Gerência - Legados</a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">VM</h6></li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'vm-todos' ? 'active' : '' ?>"
                           href="<?= site_url('reports/vm') ?>">Por Sistema Op.</a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'vm-gerencia' ? 'active' : '' ?>"
                           href="<?= site_url('reports/vm-por-gerencia') ?>">Por Gerência</a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'vm-gerencia-legados' ? 'active' : '' ?>"
                           href="<?= site_url('reports/vm-por-gerencia?legacy=1') ?>">Por Gerência - Legados</a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'vm-migraveis' ? 'active' : '' ?>"
                           href="<?= site_url('reports/vm-migraveis') ?>">Migráveis</a>
                    </li>
                </ul>
            </li>
        </ul>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb app-breadcrumb mb-0">
                <?php foreach ($breadcrumbs as $item): ?>
                    <?php
                    $label = (string) ($item['label'] ?? '');
                    $url = (string) ($item['url'] ?? '');
                    $isActive = (bool) ($item['active'] ?? false);
                    ?>
                    <?php if ($isActive): ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= esc($label) ?></li>
                    <?php elseif ($url !== ''): ?>
                        <li class="breadcrumb-item"><a href="<?= esc($url, 'attr') ?>"><?= esc($label) ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><?= esc($label) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
</div>
