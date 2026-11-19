<?php
/** @var int|null $customerId vaste klant (klantpagina) of null @var array|null $customers keuzelijst */
$terug = '/' . ltrim((string) (service('uri')->getPath()), '/');
?>
<form method="post" action="<?= site_url('taken/nieuw') ?>" class="todo-add"><?= csrf_field() ?>
    <input type="hidden" name="terug" value="<?= esc($terug, 'attr') ?>">
    <?php if (! empty($customerId)) : ?><input type="hidden" name="customer_id" value="<?= (int) $customerId ?>"><?php endif ?>
    <div class="input-group">
        <input name="title" class="form-control" placeholder="Nieuwe taak…" maxlength="200" required autocomplete="off">
        <button class="btn btn-primary" title="Toevoegen"><i class="bi bi-plus-lg"></i></button>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mt-2 small">
        <input type="date" name="due_date" class="form-control form-control-sm todo-date" style="width: auto">
        <input type="time" name="due_time" class="form-control form-control-sm" style="width: auto" title="Tijd (optioneel)">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-day="0">Vandaag</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-day="1">Morgen</button>
        <?php if (empty($customerId) && ! empty($customers)) : ?>
            <select name="customer_id" class="form-select form-select-sm" style="width: auto; max-width: 14rem">
                <option value="">Klant (optioneel)</option>
                <?php foreach ($customers as $c) : ?><option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?>
            </select>
        <?php endif ?>
    </div>
</form>
