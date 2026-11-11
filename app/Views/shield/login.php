<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Inloggen<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="d-flex justify-content-center py-5">
    <div class="card col-12 col-md-5 col-lg-4">
        <div class="card-body p-4">
            <h4 class="mb-4 text-center" style="color: var(--kb-blue)"><i class="bi bi-buildings"></i> Klantbeheer</h4>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php elseif (session('errors') !== null) : ?>
                <div class="alert alert-danger">
                    <?php foreach ((array) session('errors') as $error) : ?><?= esc($error) ?><br><?php endforeach ?>
                </div>
            <?php endif ?>
            <?php if (session('message') !== null) : ?>
                <div class="alert alert-success"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('login') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <input type="email" class="form-control" name="email" autocomplete="email" placeholder="E-mailadres" value="<?= esc(old('email') ?? '') ?>" required autofocus>
                </div>
                <div class="mb-2">
                    <input type="password" class="form-control" name="password" autocomplete="current-password" placeholder="Wachtwoord" required>
                </div>
                <?php if (setting('Auth.sessionConfig')['allowRemembering']) : ?>
                <div class="form-check mb-3">
                    <label class="form-check-label"><input type="checkbox" name="remember" class="form-check-input" <?= old('remember') ? 'checked' : '' ?>> Ingelogd blijven</label>
                </div>
                <?php endif ?>
                <div class="d-grid"><button type="submit" class="btn btn-primary">Inloggen</button></div>
                <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                    <p class="text-center mt-3 small">Wachtwoord vergeten? <a href="<?= url_to('magic-link') ?>">Stuur een inloglink</a></p>
                <?php endif ?>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
