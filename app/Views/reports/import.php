<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Importação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Execução de importação dos arquivos RVTools.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Execução manual da importação de arquivos RVTools.',
        'activeMenu' => 'importacao',
        'breadcrumbs' => [
            ['label' => 'Início', 'url' => site_url('/')],
            ['label' => 'Importação', 'active' => true],
        ],
    ]) ?>

    <div class="app-card p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-2">Importar dados</h1>
                <p class="text-secondary mb-0">
                    Esta ação processa os arquivos CSV disponíveis no diretório configurado e atualiza o inventário.
                </p>
            </div>
            <form method="post" action="<?= site_url('import') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-brand btn-lg">Executar importação</button>
            </form>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= esc($errorMessage) ?></div>
        <?php endif; ?>

        <?php if (is_array($result)): ?>
            <?php $hasErrors = !empty($result['errors']); ?>
            <div class="alert <?= $hasErrors ? 'alert-warning' : 'alert-success' ?>">
                <?= $hasErrors ? 'Importação concluída com alertas.' : 'Importação concluída com sucesso.' ?>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="small text-secondary">Arquivos processados</div>
                        <div class="display-6 fw-semibold"><?= esc((string) ($result['processed'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="small text-secondary">Arquivos ignorados</div>
                        <div class="display-6 fw-semibold"><?= esc((string) ($result['skipped'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="small text-secondary">Diretório de importação</div>
                        <div class="fw-semibold text-break"><?= esc((string) ($result['import_path'] ?? '')) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($hasErrors): ?>
                <div class="border rounded-4 p-3">
                    <h2 class="h5 mb-3">Erros encontrados</h2>
                    <ul class="mb-0">
                        <?php foreach (($result['errors'] ?? []) as $error): ?>
                            <li><?= esc((string) $error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="border rounded-4 p-3 bg-light">
                <div class="fw-semibold mb-1">Como usar</div>
                <div class="text-secondary">
                    Abra esta página no navegador e use o botão acima. Para chamadas automatizadas, use
                    <code>POST /import</code> exige um usuário Editor ou Administrador.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
