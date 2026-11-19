<?php

use App\Controllers\Todos;

/** @var list<array> $todos @var bool $showCustomer */
$terug = '/' . ltrim((string) (service('uri')->getPath()), '/');
?>
<?php if ($todos === []) : ?>
    <div class="text-muted small py-2"><?= esc($empty ?? 'Geen open taken. 🎉') ?></div>
<?php else : ?>
<ul class="list-group list-group-flush todo-list">
    <?php foreach ($todos as $t) : [$label, $color] = Todos::dueLabel($t['due_date'], $t['due_time']); ?>
    <li class="list-group-item px-0 d-flex gap-2 align-items-start <?= $t['done'] ? 'todo-done' : '' ?>">
        <input class="form-check-input mt-1 todo-check" type="checkbox" <?= $t['done'] ? 'checked' : '' ?>
               data-url="<?= site_url('taken/' . $t['id'] . '/afvinken') ?>" title="Afvinken" style="width:1.25em;height:1.25em;flex:none">
        <a href="<?= site_url('taken/' . $t['id'] . '?terug=' . urlencode($terug)) ?>" class="flex-grow-1 text-reset text-decoration-none">
            <span class="todo-title"><?= esc($t['title']) ?></span>
            <?php if (($showCustomer ?? true) && $t['customer_name']) : ?><span class="small text-muted"> · <?= esc($t['customer_name']) ?></span><?php endif ?>
            <?php if ($t['notes']) : ?><i class="bi bi-card-text small text-muted" title="<?= esc($t['notes'], 'attr') ?>"></i><?php endif ?>
        </a>
        <?php if ($label !== '') : ?><span class="badge text-bg-<?= $color ?> text-nowrap"><?= esc($label) ?></span><?php endif ?>
    </li>
    <?php endforeach ?>
</ul>
<?php endif ?>
