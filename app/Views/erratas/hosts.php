<?php
$canEditHosts = \App\Libraries\UserAuthorization::canEditHosts();
$parameters = ['q' => $criteria->term, 'date' => $criteria->date];
$returnTo = 'erratas/com-host?' . http_build_query($parameters + ['page' => $result['page']]);
$formatDate = static fn ($date) => \DateTimeImmutable::createFromFormat('!Y-m-d', $date)->format('d/m/Y');
?>
<!doctype html>
<html lang="pt-BR">
<head><?= view('erratas/_head', ['title' => 'Erratas com Host']) ?></head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', ['activeMenu' => 'erratas', 'activeSubmenu' => 'erratas-host',
        'subtitle' => 'Erratas por host do Satellite relacionadas ao inventário RVTools da mesma data.', 'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Controle de Erratas'], ['label' => 'Erratas com Host', 'active' => true],
        ]]) ?>
    <?php if (! empty($alert)): ?><div class="alert alert-<?= esc($alert['type'], 'attr') ?>"><?= esc($alert['message']) ?></div><?php endif; ?>
    <div class="app-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h1 class="h4">Erratas com Host</h1><p class="text-secondary mb-0">Busque por parte do nome da VM, nome do host no Satellite, hostname ou IP. A busca ignora maiúsculas e minúsculas.</p></div>
            <?php if (\App\Libraries\UserAuthorization::canAdminister()): ?><a class="btn btn-outline-secondary" href="<?= site_url('admin/erratas') ?>">Importar / Configurar coleta</a><?php endif; ?>
        </div>
        <form method="get" action="<?= site_url('erratas/com-host') ?>" class="row g-3 align-items-end">
            <div class="col-md-6"><label class="form-label" for="q">Nome, IP ou hostname</label><input class="form-control" type="search" name="q" id="q" maxlength="254" value="<?= esc($criteria->term, 'attr') ?>" placeholder="Ex.: dsa ou 192.168.0."></div>
            <div class="col-md-3"><label class="form-label" for="date">Data da coleta (UTC)</label><select class="form-select" name="date" id="date"><option value="">Selecione</option><?php foreach ($dates as $date): ?><option value="<?= esc($date, 'attr') ?>" <?= $date === $criteria->date ? 'selected' : '' ?>><?= esc($formatDate($date)) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-brand" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('erratas/com-host') ?>">Limpar</a></div>
        </form>
    </div>
    <?= view('erratas/_source_notice', ['source' => $source]) ?>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php elseif ($dates === []): ?>
        <div class="app-card p-4"><p class="mb-0">Nenhuma coleta de erratas por host recebida. O arquivo diário agregado não contém identificação de hosts. Esta consulta será preenchida ao receber o CSV por host do Satellite.</p></div>
    <?php elseif ($criteria->date === ''): ?>
        <div class="alert alert-info">Selecione uma data para consultar os hosts.</div>
    <?php elseif (! $hasInventory): ?>
        <div class="alert alert-info">Não há inventário RVTools na data selecionada. Importe o CSV dessa mesma data para relacionar os hosts.</div>
    <?php else: ?>
        <div class="app-card p-4">
            <h2 class="h5"><?= (int) $result['total'] ?> host(s) encontrado(s) · <?= (int) $result['errata_total'] ?> errata(s) nos hosts filtrados</h2>
            <p class="small text-secondary">Somente VMs ligadas e incluídas nos relatórios. Cada host usa sua última coleta do dia. Uma errata pode afetar vários hosts, por isso a soma por host pode diferir do total diário da organização.</p>
            <?php if ($result['unmatched'] + $result['ambiguous'] + $result['excluded'] > 0): ?>
                <div class="alert alert-light border small">Na data selecionada: <?= (int) $result['unmatched'] ?> host(s) sem correspondência, <?= (int) $result['ambiguous'] ?> com vínculo ambíguo e <?= (int) $result['excluded'] ?> fora dos critérios dos relatórios. Esses registros não aparecem na listagem.</div>
            <?php endif; ?>
            <div class="accordion" id="errata-host-days">
                <div class="accordion-item">
                    <h3 class="accordion-header" id="errata-host-heading"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#errata-host-day" aria-expanded="true" aria-controls="errata-host-day"><?= esc($formatDate($criteria->date)) ?></button></h3>
                    <div class="accordion-collapse collapse show" id="errata-host-day" aria-labelledby="errata-host-heading" data-bs-parent="#errata-host-days">
                        <div class="accordion-body">
                            <?php if ($result['rows'] === []): ?><p class="mb-0">Nenhum host corresponde aos filtros e aos critérios do inventário nessa data.</p><?php else: ?>
                            <div class="table-responsive"><table class="table table-striped align-middle">
                                <thead><tr><th>Host Satellite</th><th>VM</th><th>Hostname / DNS</th><th>IP</th><th>Sistema operacional</th><?php foreach (\App\Libraries\ErrataReportBuilder::LABELS as $label): ?><th class="text-end"><?= esc($label) ?></th><?php endforeach; ?><th class="text-end">Total</th><th>Coleta UTC</th><th>Detalhes</th></tr></thead>
                                <tbody><?php foreach ($result['rows'] as $row): ?><tr>
                                    <td><?= esc($row['satellite_host'] ?: ($row['satellite_hostname'] ?: '—')) ?></td>
                                    <td><?= esc($row['vm']) ?></td><td><?= esc($row['satellite_hostname'] ?: ($row['dns_name'] ?: '—')) ?></td><td><?= esc($row['satellite_ip'] ?: ($row['primary_ip'] ?: '—')) ?></td><td><?= esc($row['os_name'] ?: '—') ?></td>
                                    <?php foreach (array_keys(\App\Libraries\ErrataReportBuilder::LABELS) as $type): ?><td class="text-end"><?= (int) $row['errata']['counts'][$type] ?></td><?php endforeach; ?>
                                    <td class="text-end fw-semibold"><?= (int) $row['errata']['total'] ?></td><td class="text-nowrap"><?= esc(substr($row['errata']['collected_at'], 11)) ?></td>
                                    <td><?= view('reports/_search_detail_button', ['row' => $row, 'canEditHosts' => $canEditHosts]) ?>
                                        <?php if ($row['errata']['format'] === 'items'): ?><details class="mt-2"><summary class="small">Ver erratas</summary><ul class="small ps-3 mb-0"><?php foreach ($row['errata']['items'] as $item): ?><li><?= esc($item['label']) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
                                    </td>
                                </tr><?php endforeach; ?></tbody>
                            </table></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="d-flex flex-wrap align-items-center gap-3 mt-3" aria-label="Paginação dos hosts">
                <?php if ($result['page'] > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('erratas/com-host?' . http_build_query($parameters + ['page' => $result['page'] - 1])), 'attr') ?>">Anterior</a><?php endif; ?>
                <span>Página <?= (int) $result['page'] ?> de <?= (int) $result['pages'] ?> · Até 50 hosts por página</span>
                <?php if ($result['page'] < $result['pages']): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('erratas/com-host?' . http_build_query($parameters + ['page' => $result['page'] + 1])), 'attr') ?>">Próxima</a><?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>
<?php if ($result['rows'] !== []): ?>
<?= view('reports/_host_detail_modal', ['canEditHosts' => $canEditHosts, 'managementUnits' => $managementUnits, 'operatingSystems' => $operatingSystems,
    'formAction' => site_url('reports/host-info'), 'hiddenFields' => ['return_to' => $returnTo]]) ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($result['rows'] !== []): ?><?= view('reports/_host_detail_script', ['canEditHosts' => $canEditHosts, 'technicalResponsiblesByManagementUnit' => $technicalResponsiblesByManagementUnit]) ?><?php endif; ?>
</body>
</html>
