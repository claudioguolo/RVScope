<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RVScope | Detalhe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>">
    <meta name="application-name" content="RVScope">
    <meta name="description" content="RVScope - Detalhe diario por VM e anotacoes.">
    <?= view('reports/_theme') ?>
</head>
<body>
<div class="container py-4">
    <div class="app-header mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="app-title h4 mb-1">RVScope</div>
                <div class="small text-white-50">Detalhe diario por VM e anotacoes.</div>
            </div>
            <a class="btn btn-soft btn-sm" href="<?= site_url('/') ?>">Voltar</a>
        </div>
    </div>

    <?php if (!empty($alert)): ?>
        <div class="alert alert-<?= esc($alert['type'] ?? 'info') ?>">
            <?= esc($alert['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-warning"><?= esc($error) ?></div>
    <?php else: ?>
        <div class="app-card p-3">
            <h5 class="mb-3">
                Data: <?= esc($date) ?> - SO: <?= $osName !== '' ? esc($osName) : 'Todos' ?>
            </h5>

            <form method="post" class="mb-3">
                <input type="hidden" name="date" value="<?= esc($date, 'attr') ?>">
                <input type="hidden" name="os" value="<?= esc($osName, 'attr') ?>">
                <button type="submit" name="export" value="1" class="btn btn-brand">Exportar Excel (CSV)</button>
            </form>

            <?php if (empty($rows)): ?>
                <div class="alert alert-info">Nenhum registro encontrado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Name VMWare</th>
                            <th>DNS</th>
                            <th>OS</th>
                            <th>Creation</th>
                            <th>Info</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $index => $row): ?>
                            <?php $info = $row['info'] ?? []; ?>
                            <?php $isNew = !empty($newVmMap[$row['vm'] ?? '']); ?>
                            <tr>
                                <td><?= esc((string) ($index + 1)) ?></td>
                                <td>
                                    <?= esc($row['vm'] ?? '') ?>
                                    <?php if ($isNew): ?>
                                        <span class="text-danger small ms-2" title="VM nova">&#9679;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($row['dns'] ?? '') ?></td>
                                <td><?= esc($row['os'] ?? '') ?></td>
                                <td><?= esc($row['creation'] ?? '') ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-brand btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#infoModal"
                                        data-vm="<?= esc($row['vm'] ?? '', 'attr') ?>"
                                        data-desc="<?= esc($info['desc'] ?? 'Sem registro', 'attr') ?>"
                                        data-owner="<?= esc($info['owner'] ?? 'Sem registro', 'attr') ?>"
                                        data-conv="<?= esc($info['conv'] ?? 'Nao informado', 'attr') ?>"
                                        data-leg="<?= esc($info['leg'] ?? '0', 'attr') ?>"
                                        data-mig="<?= esc($info['mig'] ?? '0', 'attr') ?>"
                                        data-app="<?= esc($info['app'] ?? '0', 'attr') ?>"
                                        data-creation="<?= esc($row['creation'] ?? '', 'attr') ?>"
                                        data-annotation="<?= esc($row['annotation'] ?? '', 'attr') ?>"
                                    >Detalhes / Editar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalLabel">Informacoes da VM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="save_info" value="1">
                <input type="hidden" name="date" value="<?= esc($date, 'attr') ?>">
                <input type="hidden" name="os" value="<?= esc($osName, 'attr') ?>">

                <label class="form-label">Name VMWare</label>
                <input id="vm" name="vm" class="form-control" readonly>

                <label class="form-label mt-2">Descricao</label>
                <textarea id="desc" name="desc" class="form-control" rows="3"></textarea>

                <label class="form-label mt-2">Responsavel</label>
                <input id="owner" name="owner" class="form-control">

                <label class="form-label mt-2">Conversando</label>
                <textarea id="conv" name="conv" class="form-control" rows="3"></textarea>

                <div class="row mt-3">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="legacy" name="legacy" value="1">
                            <label class="form-check-label" for="legacy">Legado</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="migrable" name="migrable" value="1">
                            <label class="form-check-label" for="migrable">Migravel</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="appliance" name="appliance" value="1">
                            <label class="form-check-label" for="appliance">Appliance</label>
                        </div>
                    </div>
                </div>

                <label class="form-label mt-3">Criacao (dd/mm/aaaa)</label>
                <input id="creation_date" name="creation_date" class="form-control" maxlength="10" placeholder="dd/mm/aaaa">

                <label class="form-label mt-3">VCenter Notes</label>
                <textarea id="annotation" class="form-control" rows="3" readonly></textarea>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Fechar</button>
                <button class="btn btn-brand" type="submit">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const infoModal = document.getElementById('infoModal');
if (infoModal) {
  infoModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) {
      return;
    }
    document.getElementById('vm').value = button.getAttribute('data-vm') || '';
    document.getElementById('desc').value = button.getAttribute('data-desc') || '';
    document.getElementById('owner').value = button.getAttribute('data-owner') || '';
    document.getElementById('conv').value = button.getAttribute('data-conv') || '';
    document.getElementById('legacy').checked = (button.getAttribute('data-leg') === '1');
    document.getElementById('migrable').checked = (button.getAttribute('data-mig') === '1');
    document.getElementById('appliance').checked = (button.getAttribute('data-app') === '1');
    document.getElementById('creation_date').value = button.getAttribute('data-creation') || '';
    document.getElementById('annotation').value = button.getAttribute('data-annotation') || '';
  });
}
</script>
</body>
</html>
