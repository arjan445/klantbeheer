<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title><?= esc($title ?: 'Klantbeheer') ?> · Klantbeheer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link href="<?= base_url('assets/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/datatables/dataTables.bootstrap5.min.css') ?>" rel="stylesheet">
    <script src="<?= base_url('assets/jquery/jquery.min.js') ?>"></script>
    <script src="<?= base_url('assets/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/datatables/dataTables.min.js') ?>"></script>
    <script src="<?= base_url('assets/datatables/dataTables.bootstrap5.min.js') ?>"></script>
    <link href="<?= base_url('scripts/style.css?ver=1') ?>" rel="stylesheet">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-hash" content="<?= csrf_hash() ?>">
</head>
<body>
<?php if (auth()->loggedIn()) : ?>
<nav class="navbar navbar-expand-lg navbar-dark kb-nav">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= site_url('/') ?>"><i class="bi bi-buildings"></i> Klantbeheer</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= url_is('/') ? 'active' : '' ?>" href="<?= site_url('/') ?>">Dashboard</a></li>
                <?php if (user_can('customers.view')) : ?>
                <li class="nav-item"><a class="nav-link <?= url_is('klanten*') ? 'active' : '' ?>" href="<?= site_url('klanten') ?>">Klanten</a></li>
                <?php endif ?>
                <?php if (user_can('billing.view')) : ?>
                <li class="nav-item"><a class="nav-link <?= url_is('abonnementen*') ? 'active' : '' ?>" href="<?= site_url('abonnementen') ?>">Abonnementen</a></li>
                <li class="nav-item"><a class="nav-link <?= url_is('facturatie*') ? 'active' : '' ?>" href="<?= site_url('facturatie') ?>">Facturatie</a></li>
                <li class="nav-item"><a class="nav-link <?= url_is('diensten*') ? 'active' : '' ?>" href="<?= site_url('diensten') ?>">Diensten</a></li>
                <li class="nav-item"><a class="nav-link <?= url_is('kostprijzen*') ? 'active' : '' ?>" href="<?= site_url('kostprijzen') ?>">Kostprijzen</a></li>
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
