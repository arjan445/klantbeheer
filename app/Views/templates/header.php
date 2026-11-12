<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title><?= esc($title ?: 'Klantbeheer') ?> · Klantbeheer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= base_url('favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('img/apple-touch-icon.png') ?>">
    <link href="<?= base_url('assets/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/datatables/dataTables.bootstrap5.min.css') ?>" rel="stylesheet">
    <script src="<?= base_url('assets/jquery/jquery.min.js') ?>"></script>
    <script src="<?= base_url('assets/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/datatables/dataTables.min.js') ?>"></script>
    <script src="<?= base_url('assets/datatables/dataTables.bootstrap5.min.js') ?>"></script>
    <link href="<?= base_url('scripts/style.css?ver=7') ?>" rel="stylesheet">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-hash" content="<?= csrf_hash() ?>">
</head>
<body class="<?= klantmodus() ? 'klantmodus' : '' ?>">
<?php if (auth()->loggedIn()) : ?>
<nav class="navbar navbar-expand-lg navbar-dark kb-nav">
<script>
document.addEventListener('DOMContentLoaded', () => {
    const b = document.getElementById('km-toggle');
    if (!b) return;
    b.addEventListener('click', () => {
        const on = !document.body.classList.contains('klantmodus');
        document.body.classList.toggle('klantmodus', on);
        document.cookie = 'klantmodus=' + (on ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
        b.classList.toggle('active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.querySelector('i').className = 'bi ' + (on ? 'bi-eye-slash' : 'bi-eye');
        document.dispatchEvent(new CustomEvent('klantmodus', { detail: on }));
    });
});
</script>
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= site_url('/') ?>"><img src="<?= base_url('img/logo-wit.png') ?>" alt="bongersonline" class="kb-logo"><span class="kb-app d-none d-xl-inline">Klantbeheer</span></a>
        <button type="button" id="km-toggle" class="btn btn-sm km-btn ms-auto ms-lg-2 me-2 me-lg-0 order-lg-last<?= klantmodus() ? ' active' : '' ?>"
                title="Klantmodus: inkoopprijzen en marges verbergen" aria-pressed="<?= klantmodus() ? 'true' : 'false' ?>">
            <i class="bi <?= klantmodus() ? 'bi-eye-slash' : 'bi-eye' ?>"></i><span class="km-label"> Klantmodus</span>
        </button>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= url_is('/') ? 'active' : '' ?>" href="<?= site_url('/') ?>">Dashboard</a></li>
                <?php if (user_can('customers.view')) : ?>
                <li class="nav-item"><a class="nav-link <?= url_is('klanten*') ? 'active' : '' ?>" href="<?= site_url('klanten') ?>">Klanten</a></li>
                <?php endif ?>
                <?php if (user_can('billing.view')) : ?>
                <li class="nav-item"><a class="nav-link <?= url_is('offertes*') || url_is('producten*') ? 'active' : '' ?>" href="<?= site_url('offertes') ?>">Offertes</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= url_is('abonnementen*') || url_is('domeinen*') || url_is('dsd*') || url_is('facturatie*') || url_is('diensten*') || url_is('kostprijzen*') ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">Abonnementen</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= site_url('abonnementen') ?>">Abonnementen</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('domeinen') ?>">Domeinen</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('dsd') ?>">Licenties (DSD)</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('facturatie') ?>">Facturatie</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= site_url('diensten') ?>">Diensten</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('kostprijzen') ?>">Kostprijzen</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link <?= url_is('producten*') ? 'active' : '' ?>" href="<?= site_url('producten') ?>">Producten</a></li>
                <?php endif ?>
                <?php if (user_can('vault.view')) : ?>
                <li class="nav-item"><a class="nav-link <?= url_is('apparaten*') ? 'active' : '' ?>" href="<?= site_url('apparaten') ?>">Apparaten</a></li>
                <?php endif ?>
                <?php if (user_can('users.manage') || user_can('settings.manage')) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= url_is('beheer*') ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">Beheer</a>
                    <ul class="dropdown-menu">
                        <?php if (user_can('users.manage')) : ?>
                        <li><a class="dropdown-item" href="<?= site_url('beheer/gebruikers') ?>">Gebruikers</a></li>
                        <?php endif ?>
                        <?php if (user_can('settings.manage')) : ?>
                        <li><a class="dropdown-item" href="<?= site_url('beheer/moneybird') ?>">Moneybird</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('beheer/kluislog') ?>">Kluislogboek</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('beheer/diagnose') ?>">Diagnose</a></li>
                        <?php endif ?>
                    </ul>
                </li>
                <?php endif ?>
            </ul>
            <?php if (user_can('customers.view')) : ?>
            <form class="d-flex me-lg-3 my-2 my-lg-0" action="<?= site_url('zoeken') ?>" method="get" role="search">
                <input class="form-control form-control-sm" type="search" name="q" placeholder="Zoek klant, IP, apparaat…" value="<?= esc(service('request')->getGet('q') ?? '') ?>">
            </form>
            <?php endif ?>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?= esc(auth()->user()->username ?: auth()->user()->email) ?></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= site_url('account') ?>">Wachtwoord wijzigen</a></li>
                        <li><a class="dropdown-item" href="<?= site_url('logout') ?>">Uitloggen</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif ?>
<main class="container-fluid kb-main py-4">
    <?php if ($msg = session()->getFlashdata('message')) : ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= esc($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif ?>
    <?php if ($err = session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> <?= esc($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif ?>
    <?php if (isset($validation) || session('errors')) : ?>
        <?php $errs = session('errors') ?? (function_exists('validation_errors') ? validation_errors() : []); ?>
        <?php if (! empty($errs)) : ?>
        <div class="alert alert-danger"><?php foreach ((array) $errs as $e) : ?><div><?= esc($e) ?></div><?php endforeach ?></div>
        <?php endif ?>
    <?php endif ?>
