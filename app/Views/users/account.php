<h3 class="mb-3">Wachtwoord wijzigen</h3>
<form method="post" class="card col-lg-6"><div class="card-body">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Huidig wachtwoord</label><input type="password" name="current" class="form-control" required autocomplete="current-password"></div>
    <div class="mb-3"><label class="form-label">Nieuw wachtwoord</label><input type="password" name="password" class="form-control" required minlength="10" autocomplete="new-password"></div>
    <div class="mb-3"><label class="form-label">Herhaal nieuw wachtwoord</label><input type="password" name="password_confirm" class="form-control" required autocomplete="new-password"></div>
    <button class="btn btn-primary">Opslaan</button>
</div></form>
