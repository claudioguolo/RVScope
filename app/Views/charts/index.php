<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Gráficos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Evolução da quantidade de hosts por sistema operacional.">
    <?= view('reports/_theme') ?>
    <style>
        .chart-scroll { overflow-x: auto; }
        .chart-svg { min-width: 760px; width: 100%; height: auto; }
        .chart-grid { stroke: rgba(15, 23, 42, 0.13); stroke-width: 1; }
        .chart-axis { stroke: #64748b; stroke-width: 1.2; }
        .chart-label { fill: #475569; font-size: 13px; }
        .chart-line { fill: none; stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; stroke-dasharray: none; vector-effect: non-scaling-stroke; }
        .chart-legend { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; }
        .chart-legend-item { display: inline-flex; align-items: center; gap: .45rem; font-size: .875rem; }
        .chart-legend-color { width: 1.5rem; height: .22rem; border-radius: 999px; }
        .os-filter-list { max-height: 18rem; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Evolução histórica do inventário.',
        'activeMenu' => 'graficos',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Gráficos', 'active' => true],
        ],
    ]) ?>

    <?php
    $formatDate = static function (string $value): string {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date === false ? $value : $date->format('d/m/Y');
    };
    $selectedOperatingSystems = array_fill_keys($criteria->operatingSystems, true);
    ?>

    <form method="get" action="<?= site_url('graficos') ?>" class="app-card p-4 mb-4">
        <input type="hidden" name="filter" value="1">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <span class="app-eyebrow">Filtros</span>
                <h1 class="h4 mt-2 mb-1">Histórico por sistema operacional</h1>
                <p class="text-secondary mb-0">Escolha o período e as linhas que devem aparecer no gráfico.</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= site_url('graficos') ?>">Limpar filtros</a>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="start_date">Data inicial</label>
                <input class="form-control" type="date" id="start_date" name="start_date"
                       value="<?= esc($criteria->startDate, 'attr') ?>"
                       min="<?= esc($oldestDate, 'attr') ?>" max="<?= esc($newestDate, 'attr') ?>">
                <?php if ($oldestDate !== ''): ?>
                    <div class="form-text">Mais antiga: <?= esc($formatDate($oldestDate)) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="end_date">Data final</label>
                <input class="form-control" type="date" id="end_date" name="end_date"
                       value="<?= esc($criteria->endDate, 'attr') ?>"
                       min="<?= esc($oldestDate, 'attr') ?>" max="<?= esc($newestDate, 'attr') ?>">
                <?php if ($newestDate !== ''): ?>
                    <div class="form-text">Mais recente: <?= esc($formatDate($newestDate)) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-xl-6">
                <fieldset>
                    <legend class="form-label mb-2">Sistemas operacionais</legend>
                    <div class="border rounded p-3 os-filter-list">
                        <div class="form-check mb-2 pb-2 border-bottom">
                            <input class="form-check-input" type="checkbox" id="select_all_os">
                            <label class="form-check-label fw-semibold" for="select_all_os">Marcar todos</label>
                        </div>
                        <?php foreach ($availableOperatingSystems as $index => $operatingSystem): ?>
                            <?php $fieldId = 'os_' . $index; ?>
                            <div class="form-check">
                                <input class="form-check-input os-checkbox" type="checkbox" name="os[]"
                                       id="<?= esc($fieldId, 'attr') ?>"
                                       value="<?= esc($operatingSystem, 'attr') ?>"
                                       <?= isset($selectedOperatingSystems[$operatingSystem]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= esc($fieldId, 'attr') ?>"><?= esc($operatingSystem) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
        </div>

        <button class="btn btn-primary mt-3" type="submit">Aplicar filtros</button>
    </form>

    <?php if ($error !== null): ?>
        <div class="alert alert-warning"><?= esc($error) ?></div>
    <?php elseif ($availableOperatingSystems === []): ?>
        <div class="alert alert-info">Nenhum dado histórico disponível.</div>
    <?php elseif (($chart['labels'] ?? []) === [] || ($chart['series'] ?? []) === []): ?>
        <div class="alert alert-info">Nenhum dado corresponde aos filtros selecionados.</div>
    <?php else: ?>
        <?php
        $width = 1200;
        $height = 520;
        $left = 70;
        $right = 25;
        $top = 25;
        $bottom = 75;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $labels = $chart['labels'];
        $series = $chart['series'];
        $maximum = max(1, (int) ($chart['maximum'] ?? 0));
        $xDivisor = max(1, count($labels) - 1);
        $labelStep = max(1, (int) ceil(count($labels) / 8));
        ?>
        <div class="app-card p-4">
            <div class="mb-4">
                <span class="app-eyebrow">Dashboard</span>
                <h1 class="h4 mt-2 mb-2">Quantidade de hosts por sistema operacional</h1>
                <p class="text-secondary mb-0">Cada linha representa a evolução de um sistema operacional nas datas importadas.</p>
            </div>

            <div class="chart-scroll">
                <svg class="chart-svg" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-labelledby="chartTitle chartDescription">
                    <title id="chartTitle">Quantidade de hosts por sistema operacional ao longo do tempo</title>
                    <desc id="chartDescription">Gráfico de linhas baseado no histórico de importações do RVScope.</desc>

                    <?php for ($tick = 0; $tick <= 5; $tick++): ?>
                        <?php
                        $y = $top + $plotHeight - ($plotHeight * $tick / 5);
                        $tickValue = (int) round($maximum * $tick / 5);
                        ?>
                        <line class="chart-grid" x1="<?= $left ?>" y1="<?= $y ?>" x2="<?= $left + $plotWidth ?>" y2="<?= $y ?>" />
                        <text class="chart-label" x="<?= $left - 12 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= $tickValue ?></text>
                    <?php endfor; ?>

                    <line class="chart-axis" x1="<?= $left ?>" y1="<?= $top ?>" x2="<?= $left ?>" y2="<?= $top + $plotHeight ?>" />
                    <line class="chart-axis" x1="<?= $left ?>" y1="<?= $top + $plotHeight ?>" x2="<?= $left + $plotWidth ?>" y2="<?= $top + $plotHeight ?>" />

                    <?php foreach ($labels as $index => $date): ?>
                        <?php if ($index % $labelStep === 0 || $index === count($labels) - 1): ?>
                            <?php
                            $x = $left + ($plotWidth * $index / $xDivisor);
                            $dateLabel = (string) $date;
                            $parsedDate = DateTime::createFromFormat('Y-m-d', $dateLabel);
                            if ($parsedDate !== false) {
                                $dateLabel = $parsedDate->format('d/m/Y');
                            }
                            ?>
                            <text class="chart-label" x="<?= $x ?>" y="<?= $top + $plotHeight + 28 ?>" text-anchor="middle"><?= esc($dateLabel) ?></text>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php foreach ($series as $line): ?>
                        <?php
                        $points = [];
                        foreach ($line['values'] as $index => $value) {
                            $x = $left + ($plotWidth * $index / $xDivisor);
                            $y = $top + $plotHeight - ($plotHeight * (int) $value / $maximum);
                            $points[] = $x . ',' . $y;
                        }
                        ?>
                        <polyline class="chart-line" stroke="<?= esc((string) $line['color'], 'attr') ?>" points="<?= esc(implode(' ', $points), 'attr') ?>">
                            <title><?= esc((string) $line['name']) ?></title>
                        </polyline>
                    <?php endforeach; ?>
                </svg>
            </div>

            <div class="chart-legend mt-3" aria-label="Legenda do gráfico">
                <?php foreach ($series as $line): ?>
                    <span class="chart-legend-item">
                        <span class="chart-legend-color" style="background-color: <?= esc((string) $line['color'], 'attr') ?>"></span>
                        <?= esc((string) $line['name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (() => {
        const selectAll = document.getElementById('select_all_os');
        const options = Array.from(document.querySelectorAll('.os-checkbox'));
        if (!selectAll || options.length === 0) {
            return;
        }

        const updateSelectAll = () => {
            const selected = options.filter((option) => option.checked).length;
            selectAll.checked = selected === options.length;
            selectAll.indeterminate = selected > 0 && selected < options.length;
        };

        selectAll.addEventListener('change', () => {
            options.forEach((option) => {
                option.checked = selectAll.checked;
            });
            updateSelectAll();
        });
        options.forEach((option) => option.addEventListener('change', updateSelectAll));
        updateSelectAll();
    })();
</script>
</body>
</html>
