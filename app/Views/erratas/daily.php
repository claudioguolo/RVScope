<?php
$formatDate = static fn ($date) => \DateTimeImmutable::createFromFormat('!Y-m-d', $date)->format('d/m/Y');
$latest = $days[0]['latest'] ?? null;
$labels = \App\Libraries\ErrataReportBuilder::LABELS;
$colors = ['security' => '#b53b42', 'bugfix' => '#166379', 'enhancement' => '#5d843b', 'unspecified' => '#8b6b25'];
$parameters = ['start_date' => $criteria->startDate, 'end_date' => $criteria->endDate];
?>
<!doctype html>
<html lang="pt-BR">
<head><?= view('erratas/_head', ['title' => 'Erratas por Dia']) ?></head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', ['activeMenu' => 'erratas', 'activeSubmenu' => 'erratas-dia',
        'subtitle' => 'Histórico de erratas instaláveis informado pelo Satellite.', 'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Controle de Erratas'], ['label' => 'Erratas por Dia', 'active' => true],
        ]]) ?>
    <div class="app-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h1 class="h4">Erratas por Dia</h1><p class="text-secondary mb-0">Totais por tipo na última coleta de cada dia. Datas e horários em UTC.</p></div>
            <?php if (\App\Libraries\UserAuthorization::canAdminister()): ?><a class="btn btn-outline-secondary" href="<?= site_url('admin/erratas') ?>">Importar / Configurar coleta</a><?php endif; ?>
        </div>
        <form method="get" action="<?= site_url('erratas/por-dia') ?>" class="row g-3 align-items-end">
            <div class="col-sm-4"><label class="form-label" for="start_date">Data inicial</label><input type="date" class="form-control" name="start_date" id="start_date" value="<?= esc($criteria->startDate, 'attr') ?>"></div>
            <div class="col-sm-4"><label class="form-label" for="end_date">Data final</label><input type="date" class="form-control" name="end_date" id="end_date" value="<?= esc($criteria->endDate, 'attr') ?>"></div>
            <div class="col-sm-4 d-flex flex-wrap gap-2"><button type="submit" class="btn btn-brand">Filtrar</button><a class="btn btn-outline-secondary" href="<?= site_url('erratas/por-dia') ?>">Limpar</a></div>
        </form>
    </div>
    <?= view('erratas/_source_notice', ['source' => $source]) ?>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php elseif ($days === []): ?>
        <div class="app-card p-4"><p class="mb-0"><?= $source['exists'] ? 'Nenhuma coleta válida corresponde ao período selecionado.' : 'Nenhum histórico de erratas recebido. O administrador pode importar o arquivo erratas.txt ou configurar o recebimento automático do Satellite.' ?></p></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg"><div class="app-card p-3 h-100"><div class="text-secondary">Total na última coleta</div><div class="h3 mb-1"><?= number_format($latest['total'], 0, ',', '.') ?></div><div class="small text-secondary"><?= esc($formatDate($latest['date'])) ?> · <?= esc(substr($latest['collected_at'], 11)) ?> UTC</div></div></div>
            <?php foreach ($labels as $type => $label): ?><div class="col-6 col-lg"><div class="app-card p-3 h-100"><div class="text-secondary"><?= esc($label) ?></div><div class="h3 mb-0"><?= number_format($latest['counts'][$type], 0, ',', '.') ?></div></div></div><?php endforeach; ?>
        </div>
        <div class="app-card p-4 mb-4">
            <h2 class="h5">Evolução diária</h2>
            <p class="text-secondary small">Últimos <?= count($chartDays) ?> dias com coleta no período (até 90). Os valores representam o saldo de erratas instaláveis em cada data.</p>
            <?php $maximum = max(1, ...array_map(static fn ($day) => $day['latest']['total'], $chartDays)); $step = 980 / count($chartDays); ?>
            <div class="overflow-auto"><svg viewBox="0 0 1100 280" style="min-width:640px;width:100%;height:auto" role="img" aria-labelledby="errata-chart-title">
                <title id="errata-chart-title">Quantidade de erratas por dia e tipo</title>
                <?php for ($tick = 0; $tick <= 4; ++$tick): $y = 220 - $tick * 50; ?>
                    <line x1="65" y1="<?= $y ?>" x2="1060" y2="<?= $y ?>" stroke="#e2e8f0"/>
                    <text x="55" y="<?= $y + 4 ?>" text-anchor="end" font-size="12" fill="#475569"><?= number_format($maximum * $tick / 4, 0, ',', '.') ?></text>
                <?php endfor; ?>
                <?php foreach ($chartDays as $index => $day): $x = 70 + $index * $step; $base = 220; ?>
                    <g><title><?= esc($formatDate($day['date'])) ?>: <?= (int) $day['latest']['total'] ?> erratas<?php foreach ($labels as $type => $label): ?>; <?= esc($label) ?>: <?= (int) $day['latest']['counts'][$type] ?><?php endforeach; ?></title>
                    <?php foreach ($labels as $type => $label): $height = $day['latest']['counts'][$type] / $maximum * 200; $base -= $height; ?>
                        <rect x="<?= $x ?>" y="<?= $base ?>" width="<?= max(2, $step * .72) ?>" height="<?= $height ?>" fill="<?= esc($colors[$type], 'attr') ?>"/>
                    <?php endforeach; ?></g>
                    <?php if ($index % max(1, (int) ceil(count($chartDays) / 10)) === 0 || $index === count($chartDays) - 1): ?>
                        <text x="<?= $x ?>" y="245" font-size="11" fill="#475569"><?= esc(substr($formatDate($day['date']), 0, 5)) ?></text>
                    <?php endif; ?>
                <?php endforeach; ?>
            </svg></div>
            <div class="d-flex flex-wrap gap-3 small"><?php foreach ($labels as $type => $label): ?><span><span aria-hidden="true" style="display:inline-block;width:12px;height:12px;background:<?= esc($colors[$type], 'attr') ?>"></span> <?= esc($label) ?></span><?php endforeach; ?></div>
        </div>
        <div class="app-card p-4">
            <h2 class="h5 mb-3">Coletas por dia <span class="text-secondary">(<?= count($days) ?>)</span></h2>
            <div class="accordion" id="errata-days">
                <?php foreach ($visibleDays as $day): $date = $day['date']; ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="heading-<?= esc($date, 'attr') ?>"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#day-<?= esc($date, 'attr') ?>" aria-expanded="false" aria-controls="day-<?= esc($date, 'attr') ?>">
                            <span class="d-flex flex-wrap align-items-center gap-3"><span class="fw-semibold"><?= esc($formatDate($date)) ?></span><span class="badge text-bg-secondary"><?= (int) $day['latest']['total'] ?> erratas</span><span class="small text-secondary"><?= count($day['collections']) ?> coleta(s)</span></span>
                        </button></h3>
                        <div id="day-<?= esc($date, 'attr') ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= esc($date, 'attr') ?>" data-bs-parent="#errata-days">
                            <div class="accordion-body">
                                <?php foreach ($day['collections'] as $collectionIndex => $collection): ?>
                                    <?php if ($collectionIndex > 0): ?><details class="mt-3"><summary>Coleta anterior: <?= esc(substr($collection['collected_at'], 11)) ?> UTC · <?= (int) $collection['total'] ?> erratas</summary><?php else: ?><p class="small text-secondary">Última coleta: <?= esc(substr($collection['collected_at'], 11)) ?> UTC</p><?php endif; ?>
                                    <ul class="list-group mt-2">
                                        <?php foreach ($collection['items'] as $item): ?><li class="list-group-item d-flex justify-content-between align-items-center gap-3"><span><?= esc($item['label']) ?></span><span class="badge rounded-pill text-bg-light"><?= (int) $item['quantity'] ?></span></li><?php endforeach; ?>
                                        <li class="list-group-item d-flex justify-content-between fw-semibold"><span>Total</span><span><?= (int) $collection['total'] ?></span></li>
                                    </ul>
                                    <?php if ($collectionIndex > 0): ?></details><?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <nav class="d-flex flex-wrap align-items-center gap-3 mt-3" aria-label="Paginação dos dias">
                <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('erratas/por-dia?' . http_build_query($parameters + ['page' => $page - 1])), 'attr') ?>">Anterior</a><?php endif; ?>
                <span>Página <?= $page ?> de <?= $pages ?> · Até 50 dias por página</span>
                <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="<?= esc(site_url('erratas/por-dia?' . http_build_query($parameters + ['page' => $page + 1])), 'attr') ?>">Próxima</a><?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
