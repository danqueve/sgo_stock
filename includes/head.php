<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover habilita safe-area para notch/barra de inicio -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f5f5f7">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SGO">

    <!-- AnimeJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.2/anime.min.js"></script>

    <script>window.APP_URL = '<?= APP_URL ?>';</script>

    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">

    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/icons/logo.jpg">

    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">

    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/icons/icon-192.png">
</head>
<body class="bg-body-tertiary">
