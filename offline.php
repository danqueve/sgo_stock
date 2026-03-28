<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sin conexión — Imperio Comercial</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { min-height: 100dvh; display: flex; align-items: center; justify-content: center;
               background: linear-gradient(160deg, #1a1f36 0%, #2d3561 100%); }
    </style>
</head>
<body>
<div class="text-center text-white px-4">
    <i class="bi bi-wifi-off mb-3 d-block" style="font-size:4rem;opacity:.6"></i>
    <h1 class="fs-4 fw-bold mb-2">Sin conexión</h1>
    <p class="text-white-50 mb-4">
        No hay internet disponible. Las ventas realizadas offline<br>
        se sincronizarán automáticamente cuando vuelvas a conectarte.
    </p>
    <button onclick="location.reload()" class="btn btn-warning fw-medium px-4 py-2 rounded-3">
        <i class="bi bi-arrow-clockwise me-2"></i>Reintentar
    </button>
</div>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
