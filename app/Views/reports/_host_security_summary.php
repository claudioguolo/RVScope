<?php
$colors = [
    'enforcing' => 'bg-success', 'active' => 'bg-success',
    'permissive' => 'bg-warning', 'disabled' => 'bg-danger',
    'inactive' => 'bg-danger', 'failed' => 'bg-danger', 'unknown' => 'bg-secondary',
];
?>
<section class="border rounded p-3 h-100" aria-label="<?= esc('Distribuição por status de ' . $title, 'attr') ?>">
    <h3 class="h5 mb-3"><?= esc($title) ?></h3>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <caption class="visually-hidden">Quantidade, porcentagem e gráfico de hosts por status de <?= esc($title) ?></caption>
            <thead><tr><th scope="col">Status</th><th scope="col" class="text-end">Hosts</th><th scope="col" class="text-end">%</th><th scope="col" class="w-50">Distribuição</th></tr></thead>
            <tbody>
            <?php foreach ($statuses as $status => $values):
                $percentage = number_format($values['percentage'], 1, ',', '.');
                $width = number_format($values['percentage'], 4, '.', '');
            ?>
                <tr>
                    <th scope="row" class="fw-normal"><?= esc($status) ?></th>
                    <td class="text-end"><?= (int) $values['count'] ?></td>
                    <td class="text-end text-nowrap"><?= esc($percentage) ?>%</td>
                    <td>
                        <div class="progress" role="img" aria-label="<?= esc($status . ': ' . $values['count'] . ' host(s), ' . $percentage . '%', 'attr') ?>">
                            <div class="progress-bar <?= esc($colors[$status] ?? 'bg-info', 'attr') ?>" style="width: <?= esc($width, 'attr') ?>%"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
