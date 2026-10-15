<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Teste de e-mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <?= view('reports/_topbar', ['activeMenu' => 'email-test', 'subtitle' => 'Diagnóstico do serviço de e-mail', 'breadcrumbs' => [
        ['label' => 'Início', 'url' => site_url('/')], ['label' => 'Teste de e-mail', 'active' => true],
    ]]) ?>
    <div class="app-card p-4 mb-4">
        <h1 class="h4"><?= $administrative ? 'Configurar teste de e-mail' : 'Teste de e-mail' ?></h1>
        <?php if ($error = session()->getFlashdata('email_test_error')): ?>
            <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>
        <?php if ($success = session()->getFlashdata('email_test_success')): ?>
            <div class="alert alert-success" role="status"><?= esc($success) ?></div>
        <?php endif; ?>
        <?php if ($administrative): ?>
            <p>O teste usa o servidor cadastrado em Usuários e configurações. A conta abaixo é exclusiva para os testes e não altera a conta de notificações. Somente administradores podem editar este cadastro.</p>
            <form method="post" action="<?= site_url('admin/email-test') ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-6">
                    <label for="test_username" class="form-label">Conta exclusiva de testes</label>
                    <div class="input-group"><input id="test_username" name="test_username" class="form-control" maxlength="236" autocomplete="off" required value="<?= esc(explode('@', $configuration['username'])[0], 'attr') ?>"><span class="input-group-text">@envio.tjmt.jus.br</span></div>
                </div>
                <div class="col-md-6">
                    <label for="test_password" class="form-label">Senha da conta de testes</label>
                    <input id="test_password" name="test_password" type="password" class="form-control" maxlength="4096" autocomplete="new-password" <?= $configuration['password_encrypted'] === '' ? 'required' : '' ?>>
                    <div class="form-text">Senha criptografada. Deixe vazio para manter; ao trocar a conta, informe a nova senha.</div>
                </div>
                <div class="col-12"><label for="recipients" class="form-label">Destinatários padrão</label><textarea id="recipients" name="recipients" class="form-control" maxlength="6000" required><?= esc($configuration['recipients']) ?></textarea><div class="form-text">Até 20 endereços, separados por vírgula, ponto e vírgula ou linha.</div></div>
                <div class="col-12"><label for="subject" class="form-label">Assunto padrão</label><input id="subject" name="subject" class="form-control" maxlength="200" required value="<?= esc($configuration['subject'], 'attr') ?>"></div>
                <div class="col-12"><label for="body" class="form-label">Mensagem padrão (texto simples)</label><textarea id="body" name="body" class="form-control" rows="10" maxlength="10000" required><?= esc($configuration['body']) ?></textarea></div>
                <div class="col-12"><button class="btn btn-brand" type="submit">Salvar configuração</button> <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">Voltar à administração</a></div>
            </form>
        <?php else: ?>
            <p>Escolha o teste rápido com a conta exclusiva cadastrada ou teste uma conta específica. A mensagem é definida pelo administrador.</p>
            <p><strong>Destinatários padrão:</strong> <?= esc($configuration['recipients'] ?: 'Ainda não configurados pelo administrador.') ?></p>
            <form id="email-test-form" method="post" action="<?= site_url('tools/email-test') ?>" autocomplete="off" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-12">
                    <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="mode-quick" value="quick" checked><label class="form-check-label fw-semibold" for="mode-quick">Teste rápido com a conta cadastrada</label></div>
                    <button id="quick-button" type="submit" class="btn btn-brand mt-2">Teste Rápido</button>
                </div>
                <div class="col-12">
                    <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="mode-custom" value="custom"><label class="form-check-label fw-semibold" for="mode-custom">Teste com usuário e senha alternativos</label></div>
                </div>
                <fieldset id="custom-fields" class="col-12" disabled>
                    <div class="row g-3">
                        <div class="col-md-6"><label for="username" class="form-label">Conta alternativa</label><div class="input-group"><input id="username" name="username" maxlength="236" autocomplete="off" class="form-control" required><span class="input-group-text">@envio.tjmt.jus.br</span></div><div class="form-text">Informe somente o nome da conta. Ela será usada para autenticação e como remetente.</div></div>
                        <div class="col-md-6"><label for="password" class="form-label">Senha da conta alternativa</label><input id="password" name="password" type="password" maxlength="4096" autocomplete="new-password" class="form-control" required></div>
                        <div class="col-12"><label for="custom_recipients" class="form-label">Destinatário personalizado (opcional)</label><textarea id="custom_recipients" name="custom_recipients" maxlength="6000" class="form-control"></textarea><div class="form-text">Deixe vazio para usar os destinatários padrão. Se preenchido, o envio será feito somente para os endereços informados. Até 20 endereços, separados por vírgula, ponto e vírgula ou linha.</div></div>
                    </div>
                </fieldset>
                <div class="col-12"><button id="custom-button" type="submit" class="btn btn-brand" disabled>Teste Personalizado</button></div>
            </form>
            <noscript><p class="text-secondary mt-3">Ative o JavaScript para selecionar o teste personalizado. O teste rápido continua disponível.</p></noscript>
            <p class="small text-secondary mt-3">Intervalo mínimo de 60 segundos entre testes. Somente a identificação do usuário e a data/hora da tentativa são registradas.</p>
            <details class="mt-3"><summary>Ver mensagem padrão</summary><p class="mt-2"><strong><?= esc($configuration['subject']) ?></strong></p><div style="white-space: pre-wrap"><?= esc($configuration['body']) ?></div></details>
            <?php if (\App\Libraries\UserAuthorization::canAdminister()): ?>
                <a class="btn btn-outline-secondary mt-3" href="<?= site_url('admin/email-test') ?>">Configurar teste de e-mail</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const form = document.getElementById('email-test-form');
    if (!form) return;
    const custom = document.getElementById('mode-custom');
    const fields = document.getElementById('custom-fields');
    const update = () => {
        fields.disabled = !custom.checked;
        document.getElementById('quick-button').disabled = custom.checked;
        document.getElementById('custom-button').disabled = !custom.checked;
        if (!custom.checked) {
            fields.querySelectorAll('input, textarea').forEach(field => { field.value = ''; });
        }
    };
    form.querySelectorAll('input[name="mode"]').forEach(radio => radio.addEventListener('change', update));
    window.addEventListener('pageshow', update);
    update();
})();
</script>
</body>
</html>
