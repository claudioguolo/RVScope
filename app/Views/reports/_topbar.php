<?php
$subtitle = $subtitle ?? '';
$activeMenu = $activeMenu ?? 'inicio';
$activeSubmenu = $activeSubmenu ?? '';
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Início', 'active' => true],
];
?>

<div class="app-header mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="app-title h4 mb-1">RVScope</div>
            <?php if ($subtitle !== ''): ?>
                <div class="small text-white-50"><?= esc($subtitle) ?></div>
            <?php endif; ?>
        </div>
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
