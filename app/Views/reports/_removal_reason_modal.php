<?php $canEditRemovalReason = \App\Libraries\UserAuthorization::canEditHosts(); ?>
<div class="modal fade" id="removalReasonModal" tabindex="-1" aria-labelledby="removalReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title" id="removalReasonModalLabel">Motivo da remoção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="save_removal_reason" value="1">
                <input type="hidden" name="date" value="<?= esc((string) ($date ?? ''), 'attr') ?>">
                <input type="hidden" id="removal_reason_vm" name="vm">

                <label for="removal_reason_vm_display" class="form-label">Host</label>
                <input id="removal_reason_vm_display" class="form-control" readonly>

                <label for="removal_reason" class="form-label mt-3">Motivo</label>
                <textarea
                    id="removal_reason"
                    name="removal_reason"
                    class="form-control"
                    rows="5"
                    maxlength="2000"
                    placeholder="Informe por que este host foi removido da listagem."
                    <?= $canEditRemovalReason ? 'required' : 'disabled' ?>
                ></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Fechar</button>
                <?php if ($canEditRemovalReason): ?>
                    <button class="btn btn-brand" type="submit">Salvar motivo</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('removalReasonModal')?.addEventListener('show.bs.modal', (event) => {
  const button = event.relatedTarget;
  if (!button) {
    return;
  }

  const vm = button.getAttribute('data-vm') || '';
  document.getElementById('removal_reason_vm').value = vm;
  document.getElementById('removal_reason_vm_display').value = vm;
  document.getElementById('removal_reason').value = button.getAttribute('data-removal-reason') || '';
});
</script>
