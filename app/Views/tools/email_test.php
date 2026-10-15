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
            <p>O teste usa o servidor cadastrado em Usuários e configurações. Somente administradores podem editar esta mensagem e os destinatários padrão.</p>
            <form method="post" action="<?= site_url('admin/email-test') ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-12"><label for="recipients" class="form-label">Destinatários padrão</label><textarea id="recipients" name="recipients" class="form-control" maxlength="6000" required><?= esc($configuration['recipients']) ?></textarea><div class="form-text">Até 20 endereços, separados por vírgula, ponto e vírgula ou linha.</div></div>
                <div class="col-12"><label for="subject" class="form-label">Assunto padrão</label><input id="subject" name="subject" class="form-control" maxlength="200" required value="<?= esc($configuration['subject'], 'attr') ?>"></div>
                <div class="col-12"><label for="body" class="form-label">Mensagem padrão (texto simples)</label><textarea id="body" name="body" class="form-control" rows="10" maxlength="10000" required><?= esc($configuration['body']) ?></textarea></div>
                <div class="col-12"><button class="btn btn-brand" type="submit">Salvar configuração</button> <a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary">Voltar à administração</a></div>
            </form>
        <?php else: ?>
            <p>Envia a mensagem padrão usando o servidor configurado no RVScope. Os campos abaixo são opcionais e usados somente neste envio.</p>
            <p><strong>Destinatários padrão:</strong> <?= esc($configuration['recipients'] ?: 'Ainda não configurados pelo administrador.') ?></p>
            <form method="post" action="<?= site_url('tools/email-test') ?>" autocomplete="off" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-6"><label for="username" class="form-label">E-mail da conta alternativa</label><input id="username" name="username" type="email" maxlength="254" autocomplete="off" class="form-control"><div class="form-text">Também será o remetente do teste.</div></div>
                <div class="col-md-6"><label for="password" class="form-label">Senha da conta alternativa</label><input id="password" name="password" type="password" maxlength="4096" autocomplete="new-password" class="form-control"></div>
                <div class="col-12"><label for="extra_recipients" class="form-label">Destinatários extras</label><textarea id="extra_recipients" name="extra_recipients" maxlength="6000" class="form-control"></textarea><div class="form-text">Separe por vírgula, ponto e vírgula ou linha. Máximo de 20 destinatários, incluindo os padrão.</div></div>
                <div class="col-12"><button type="submit" class="btn btn-brand">Enviar e-mail de teste</button></div>
            </form>
            <p class="small text-secondary mt-3">Intervalo mínimo de 60 segundos entre testes. Somente a identificação do usuário e a data/hora da tentativa são registradas.</p>
            <details class="mt-3"><summary>Ver mensagem padrão</summary><p class="mt-2"><strong><?= esc($configuration['subject']) ?></strong></p><div style="white-space: pre-wrap"><?= esc($configuration['body']) ?></div></details>
            <?php if (\App\Libraries\UserAuthorization::canAdminister()): ?>
                <a class="btn btn-outline-secondary mt-3" href="<?= site_url('admin/email-test') ?>">Configurar teste de e-mail</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
