<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Banco de dados indisponível</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="robots" content="noindex,nofollow">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', [
        'subtitle' => 'Serviço temporariamente indisponível.',
        'activeMenu' => '',
        'activeSubmenu' => '',
        'showAdminShortcut' => false,
        'breadcrumbs' => [
            ['label' => 'Indisponibilidade temporária', 'active' => true],
        ],
    ]) ?>

    <main class="app-card p-4 p-md-5" role="alert" aria-live="polite">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
            <div class="app-gear-badge" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M12 2a10 10 0 1 0 10 10A10.01 10.01 0 0 0 12 2Zm1 15h-2v-2h2Zm0-4h-2V7h2Z"/>
                </svg>
            </div>
            <div class="flex-grow-1">
                <span class="app-eyebrow mb-3">Conectividade</span>
                <h1 class="h3 mb-3">Banco de dados não disponível</h1>
                <p class="text-secondary mb-3">
                    O RVScope está em execução, mas não conseguiu acessar o serviço de banco de dados.
                    Os relatórios e a administração voltarão automaticamente quando a conexão for restabelecida.
                </p>
                <div class="app-inline-note mb-4">
                    Nenhuma ação é necessária no navegador. Você pode tentar novamente em alguns instantes.
                </div>
                <a class="btn btn-brand" href="<?= site_url('/') ?>">Tentar novamente</a>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
