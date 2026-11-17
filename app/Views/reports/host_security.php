<?php
$canEditHosts = \App\Libraries\UserAuthorization::canEditHosts();
$parameters = ['date' => $date, 'q' => $term];
$returnTo = 'reports/seguranca-hosts?' . http_build_query($parameters + ['page' => $page]);
?>
<!doctype html>
<html lang="pt-BR">
<head><?= view('erratas/_head', ['title' => 'Segurança dos Hosts']) ?></head>
<body><div class="container py-4">
<?= view('reports/_topbar', ['activeMenu' => 'host-security', 'subtitle' => 'Coletas de SELinux e firewalld enviadas pelo Satellite.', 'breadcrumbs' => [
    ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Segurança dos Hosts', 'active' => true],
]]) ?>
<?php if (! empty($alert)): ?><div class="alert alert-<?= esc($alert['type'], 'attr') ?>"><?= esc($alert['message']) ?></div><?php endif; ?>
<div class="app-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3"><h1 class="h4">Segurança dos Hosts</h1>
    <?php if (\App\Libraries\UserAuthorization::canAdminister()): ?><a class="btn btn-outline-secondary" href="<?= site_url('admin/coletas/seguranca') ?>">Configurar coleta</a><?php endif; ?></div>
    <p class="text-secondary">Última coleta de cada host no dia (UTC). Somente VMs ligadas e incluídas nos relatórios no inventário RVTools da mesma data.</p>
    <form method="get" action="<?= site_url('reports/seguranca-hosts') ?>" class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label" for="q">VM, hostname ou IP</label><input class="form-control" id="q" name="q" type="search" maxlength="254" value="<?= esc($term, 'attr') ?>"></div>
        <div class="col-md-4"><label class="form-label" for="date">Data da coleta (UTC)</label><select class="form-select" id="date" name="date"><?php foreach ($dates as $day): ?><option value="<?= esc($day, 'attr') ?>" <?= $day === $date ? 'selected' : '' ?>><?= esc(date('d/m/Y', strtotime($day))) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-brand">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('reports/seguranca-hosts') ?>">Limpar</a></div>
    </form>
</div>
<?php if ($error !== null): ?><div class="alert alert-danger"><?= esc($error) ?></div>
<?php elseif ($dates === []): ?><div class="app-card p-4">Nenhuma coleta de segurança recebida.</div>
<?php elseif (! $hasInventory): ?><div class="alert alert-info">Não há inventário RVTools para esta data. Importe o CSV da mesma data para relacionar os hosts.</div>
<?php else: ?>
<div class="app-card p-4">
    <h2 class="h5"><?= (int) $total ?> host(s) encontrado(s)</h2>
    <?php if ($result['unmatched'] + $result['ambiguous'] + $result['excluded'] > 0): ?><div class="alert alert-light border small">Nesta data: <?= (int) $result['unmatched'] ?> host(s) sem correspondência, <?= (int) $result['ambiguous'] ?> com vínculo ambíguo e <?= (int) $result['excluded'] ?> fora dos critérios dos relatórios. Esses registros não aparecem na tabela.</div><?php endif; ?>
    <div class="accordion" id="security-days">
    <?php foreach ($dates as $i => $day): $selected = $day === $date; ?>
        <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button <?= $selected ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#security-day-<?= (int) $i ?>" aria-expanded="<?= $selected ? 'true' : 'false' ?>" aria-controls="security-day-<?= (int) $i ?>"><?= esc(date('d/m/Y', strtotime($day))) ?></button></h3>
            <div class="accordion-collapse collapse <?= $selected ? 'show' : '' ?>" id="security-day-<?= (int) $i ?>" data-bs-parent="#security-days"><div class="accordion-body">
            <?php if (! $selected): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('reports/seguranca-hosts?' . http_build_query(['date' => $day, 'q' => $term])), 'attr') ?>">Consultar esta data</a>
            <?php elseif ($result['rows'] === []): ?><p class="mb-0">Nenhum host corresponde aos filtros e aos critérios do inventário.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>VM</th><th>Hostname coletado</th><th>IP</th><th>Sistema operacional</th><th>SELinux</th><th>firewalld</th><th>Coleta UTC</th><th>Detalhes</th></tr></thead><tbody>
                <?php foreach ($result['rows'] as $row): $collection = $row['collection']; ?>
                    <tr><td><?= esc($row['vm']) ?></td><td><?= esc($collection['hostname']) ?></td><td><?= esc($row['primary_ip'] ?: '—') ?></td><td><?= esc($row['os_name'] ?: '—') ?></td><td><?= esc($collection['sestatus']) ?></td><td><?= esc($collection['firewall']) ?></td><td class="text-nowrap"><?= esc(substr($collection['collected_at'], 11)) ?></td><td><?= view('reports/_search_detail_button', compact('row', 'canEditHosts')) ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
            </div></div>
        </div>
    <?php endforeach; ?>
    </div>
    <nav class="d-flex flex-wrap gap-3 align-items-center mt-3" aria-label="Paginação dos hosts">
        <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('reports/seguranca-hosts?' . http_build_query($parameters + ['page' => $page - 1])), 'attr') ?>">Anterior</a><?php endif; ?>
        <span>Página <?= (int) $page ?> de <?= (int) $pages ?> · Até 50 hosts por página</span>
        <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('reports/seguranca-hosts?' . http_build_query($parameters + ['page' => $page + 1])), 'attr') ?>">Próxima</a><?php endif; ?>
    </nav>
</div>
<?php endif; ?>
</div>
<?php if ($result['rows'] !== []): ?><?= view('reports/_host_detail_modal', ['canEditHosts' => $canEditHosts, 'managementUnits' => $managementUnits, 'operatingSystems' => $operatingSystems, 'formAction' => site_url('reports/host-info'), 'hiddenFields' => ['return_to' => $returnTo]]) ?><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($result['rows'] !== []): ?><?= view('reports/_host_detail_script', ['canEditHosts' => $canEditHosts, 'technicalResponsiblesByManagementUnit' => $technicalResponsiblesByManagementUnit]) ?><?php endif; ?>
</body></html>
