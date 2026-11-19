<?php $v = static fn ($k) => old($k, $todo[$k] ?? ''); ?>
<a href="<?= esc($returnTo, 'attr') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Terug</a>
<h3 class="mb-3">Taak</h3>
<form method="post" id="todo-form"><?= csrf_field() ?>
<input type="hidden" name="terug" value="<?= esc((string) (parse_url($returnTo, PHP_URL_PATH) ?: '/taken'), 'attr') ?>">
<div class="card mb-3"><div class="card-body">
    <div class="row g-2">
        <div class="col-12"><label class="form-label small mb-1">Omschrijving</label>
            <input name="title" class="form-control" maxlength="200" required value="<?= esc($v('title'), 'attr') ?>"></div>
        <div class="col-12 col-md-6"><label class="form-label small mb-1">Klant</label>
            <select name="customer_id" class="form-select"><option value="">— geen —</option>
                <?php foreach ($customers as $c) : ?><option value="<?= (int) $c['id'] ?>" <?= (int) $v('customer_id') === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Datum</label>
            <input type="date" name="due_date" class="form-control" value="<?= esc((string) $v('due_date'), 'attr') ?>"></div>
        <div class="col-3 col-md-2"><label class="form-label small mb-1">Tijd</label>
            <input type="time" name="due_time" class="form-control" value="<?= esc(substr((string) $v('due_time'), 0, 5), 'attr') ?>"></div>
        <div class="col-3 col-md-2"><label class="form-label small mb-1">Duur (min)</label>
            <input type="number" name="duration" min="5" step="5" inputmode="numeric" class="form-control" value="<?= (int) ($v('duration') ?: 30) ?>"></div>
        <div class="col-12"><label class="form-label small mb-1">Notitie</label>
            <textarea name="notes" class="form-control" rows="4"><?= esc((string) $v('notes')) ?></textarea></div>
        <div class="col-12 small text-muted">Zonder datum komt de taak niet in je agenda. Zonder tijd staat hij als hele-dag-item in je agenda.<?= $todo['done'] ? ' Afgerond op ' . date('d-m-Y H:i', strtotime($todo['done_at'])) . '.' : '' ?></div>
    </div>
</div></div>
<div class="device-actions d-flex gap-2">
    <button type="button" class="btn btn-outline-danger" onclick="if (confirm('Taak verwijderen?')) document.getElementById('del').submit()"><i class="bi bi-trash"></i></button>
    <button type="button" class="btn btn-outline-success flex-grow-1" onclick="document.getElementById('tgl').submit()"><i class="bi <?= $todo['done'] ? 'bi-arrow-counterclockwise' : 'bi-check2-square' ?>"></i> <?= $todo['done'] ? 'Weer open' : 'Afgerond' ?></button>
    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg"></i> Opslaan</button>
</div>
</form>
<form method="post" action="<?= site_url('taken/' . $todo['id'] . '/verwijderen') ?>" id="del"><?= csrf_field() ?><input type="hidden" name="terug" value="<?= esc((string) (parse_url($returnTo, PHP_URL_PATH) ?: '/taken'), 'attr') ?>"></form>
<form method="post" action="<?= site_url('taken/' . $todo['id'] . '/afvinken') ?>" id="tgl"><?= csrf_field() ?><input type="hidden" name="done" value="<?= $todo['done'] ? 0 : 1 ?>"></form>
