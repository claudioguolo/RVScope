<?php
$parameters = ['date' => $date, 'q' => $term];
?>
<!doctype html>
<html lang="pt-BR">
<head><?= view('erratas/_head', ['title' => 'Segurança dos Hosts']) ?></head>
<body><div class="container py-4">
<?= view('reports/_topbar', ['activeMenu' => 'host-security', 'subtitle' => 'Coletas de SELinux e firewalld enviadas pelo Satellite.', 'breadcrumbs' => [
    ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Segurança dos Hosts', 'active' => true],
]]) ?>
<div class="app-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3"><h1 class="h4">Segurança dos Hosts</h1>
    <?php if (\App\Libraries\UserAuthorization::canAdminister()): ?><a class="btn btn-outline-secondary" href="<?= site_url('admin/coletas/seguranca') ?>">Configurar coleta</a><?php endif; ?></div>
    <p class="text-secondary">Último registro por hostname ou IP de cada dia (UTC). <?= $linkedToRvtools ? 'Vinculado ao inventário RVTools da mesma data: somente VMs ligadas e incluídas nos relatórios.' : 'Modo independente: todos os hosts recebidos.' ?></p>
    <form method="get" action="<?= site_url('reports/seguranca-hosts') ?>" class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label" for="q">Hostname ou IP</label><input class="form-control" id="q" name="q" type="search" maxlength="254" value="<?= esc($term, 'attr') ?>"></div>
        <div class="col-md-4"><label class="form-label" for="date">Data da coleta (UTC)</label><select class="form-select" id="date" name="date"><?php foreach ($dates as $day): ?><option value="<?= esc($day, 'attr') ?>" <?= $day === $date ? 'selected' : '' ?>><?= esc(date('d/m/Y', strtotime($day))) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-brand">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('reports/seguranca-hosts') ?>">Limpar</a></div>
    </form>
</div>
<?php if ($error !== null): ?><div class="alert alert-danger"><?= esc($error) ?></div>
<?php elseif ($dates === []): ?><div class="app-card p-4">Nenhuma coleta de segurança recebida.</div>
<?php else: ?>
<div class="app-card p-4">
    <h2 class="h5"><?= (int) $total ?> host(s) encontrado(s)</h2>
    <?php if ($linkedToRvtools && ! $hasInventory): ?><div class="alert alert-info">O vínculo com o RVTools está ativado, mas não há inventário para esta data. Importe o inventário da mesma data ou desative o vínculo na administração. As coletas de segurança recebidas continuam preservadas.</div><?php endif; ?>
    <p class="small text-secondary">Totais da data selecionada e da busca atual, considerando todas as páginas. Percentuais sobre <?= (int) $total ?> host(s); o arredondamento pode fazer a soma diferir de 100%.</p>
    <div class="row g-3 mb-4">
        <div class="col-lg-6"><?= view('reports/_host_security_summary', ['title' => 'SELinux', 'statuses' => $result['summary']['sestatus']]) ?></div>
        <div class="col-lg-6"><?= view('reports/_host_security_summary', ['title' => 'firewalld', 'statuses' => $result['summary']['firewall']]) ?></div>
    </div>
    <div class="accordion" id="security-days">
    <?php foreach ($dates as $i => $day): $selected = $day === $date; ?>
        <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button <?= $selected ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#security-day-<?= (int) $i ?>" aria-expanded="<?= $selected ? 'true' : 'false' ?>" aria-controls="security-day-<?= (int) $i ?>"><?= esc(date('d/m/Y', strtotime($day))) ?></button></h3>
            <div class="accordion-collapse collapse <?= $selected ? 'show' : '' ?>" id="security-day-<?= (int) $i ?>" data-bs-parent="#security-days"><div class="accordion-body">
            <?php if (! $selected): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('reports/seguranca-hosts?' . http_build_query(['date' => $day, 'q' => $term])), 'attr') ?>">Consultar esta data</a>
            <?php elseif ($result['rows'] === []): ?><p class="mb-0">Nenhum host corresponde à busca nesta data.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Hostname coletado</th><?php if ($linkedToRvtools): ?><th>VM RVTools</th><?php endif; ?><th>IP coletado</th><th>SELinux</th><th>firewalld</th><th>Coleta UTC</th><th>Detalhes</th></tr></thead><tbody>
                <?php foreach ($result['rows'] as $collection): ?>
                    <tr><td><?= esc($collection['hostname']) ?></td><?php if ($linkedToRvtools): ?><td><?= esc($collection['inventory_vm']) ?></td><?php endif; ?><td><?= esc(($collection['ip'] ?? '') ?: '—') ?></td><td><?= esc($collection['sestatus']) ?></td><td><?= esc($collection['firewall']) ?></td><td class="text-nowrap"><?= esc(substr($collection['collected_at'], 11)) ?></td>
                        <td><button class="btn btn-brand btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#securityDetailModal"
                            data-hostname="<?= esc($collection['hostname'], 'attr') ?>" data-ip="<?= esc($collection['ip'] ?? '', 'attr') ?>" data-sestatus="<?= esc($collection['sestatus'], 'attr') ?>"
                            data-firewall="<?= esc($collection['firewall'], 'attr') ?>" data-collected-at="<?= esc($collection['collected_at'], 'attr') ?>">Detalhes</button></td></tr>
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
<div class="modal fade" id="securityDetailModal" tabindex="-1" aria-labelledby="securityDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="securityDetailTitle">Detalhes da coleta de segurança</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><dl class="row mb-0">
            <dt class="col-sm-4">Hostname</dt><dd class="col-sm-8 text-break" data-security-field="hostname"></dd>
            <dt class="col-sm-4">IP coletado</dt><dd class="col-sm-8 text-break" data-security-field="ip"></dd>
            <dt class="col-sm-4">SELinux</dt><dd class="col-sm-8" data-security-field="sestatus"></dd>
            <dt class="col-sm-4">firewalld</dt><dd class="col-sm-8" data-security-field="firewall"></dd>
            <dt class="col-sm-4">Coleta UTC</dt><dd class="col-sm-8" data-security-field="collected-at"></dd>
        </dl></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Fechar</button></div>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('securityDetailModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;
    this.querySelectorAll('[data-security-field]').forEach(function (element) {
        element.textContent = button.getAttribute('data-' + element.getAttribute('data-security-field')) || '—';
    });
});
</script>
</body></html>
