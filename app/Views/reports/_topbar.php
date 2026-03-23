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
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'vm-gerencia' ? 'active' : '' ?>"
                           href="<?= site_url('reports/vm-por-gerencia') ?>">VM por Gerência</a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $activeSubmenu === 'appliances' ? 'active' : '' ?>"
                           href="<?= site_url('reports/appliances') ?>">Appliances por Gerência</a>
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
