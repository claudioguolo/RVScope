<?php if ($source['invalid_lines'] !== []): ?>
    <div class="alert alert-warning" role="alert">
        <?= count($source['invalid_lines']) ?> registro(s) inválido(s) foram desconsiderados. O administrador deve conferir o arquivo de origem.
    </div>
<?php endif; ?>
