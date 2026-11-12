<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->renderSection('title') ?> · Klantbeheer</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= base_url('favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('img/apple-touch-icon.png') ?>">
    <link href="<?= base_url('assets/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('scripts/style.css?ver=7') ?>" rel="stylesheet">
    <?= $this->renderSection('pageStyles') ?>
</head>
<body>
    <main role="main" class="container">
        <?= $this->renderSection('main') ?>
    </main>
    <?= $this->renderSection('pageScripts') ?>
</body>
</html>
