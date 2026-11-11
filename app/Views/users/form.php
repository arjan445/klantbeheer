<?php $isNew = $user === null; $current = $isNew ? ($old['group'] ?? 'monteur') : ($user->getGroups()[0] ?? 'monteur'); ?>
<a href="<?= site_url('beheer/gebruikers') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Gebruikers</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe gebruiker' : esc($user->email) ?></h3>
<form method="post" class="card" autocomplete="off"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php if ($isNew) : ?>
        <div class="col-md-6"><label class="form-label">E-mail *</label><input type="email" name="email" class="form-control" required value="<?= esc($old['email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Gebruikersnaam</label><input name="username" class="form-control" value="<?= esc($old['username'] ?? '') ?>"></div>
        <?php endif ?>
        <div class="col-md-6"><label class="form-label">Rol *</label>
            <select name="group" class="form-select">
                <?php foreach ($groups as $key => $g) : ?><option value="<?= esc($key) ?>" <?= $current === $key ? 'selected' : '' ?>><?= esc($g['title']) ?> — <?= esc($g['description']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label"><?= $isNew ? 'Wachtwoord *' : 'Nieuw wachtwoord' ?></label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" <?= $isNew ? 'required' : 'placeholder="Leeg laten = ongewijzigd"' ?>>
                <button class="btn btn-outline-secondary" type="button" data-generate="#password" title="Genereer"><i class="bi bi-magic"></i></button>
            </div>
            <div class="form-text">Minimaal 10 tekens.</div>
        </div>
        <?php if (! $isNew) : ?>
        <div class="col-12"><input type="hidden" name="active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= $user->active ? 'checked' : '' ?>><label class="form-check-label" for="active">Actief (mag inloggen)</label></div></div>
        <?php endif ?>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Opslaan</button></div>
</div></form>
