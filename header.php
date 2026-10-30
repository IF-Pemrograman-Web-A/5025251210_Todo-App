<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo List Bagus Mantap</title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#7fffd4">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <link rel="icon" href="icons/icon-192.png">
</head>
<body>

<header class="app-header">
    <h1><?= e($app_name) ?></h1>
    <button id="theme-toggle" type="button" aria-pressed="false">🌙 Dark Mode</button>
    <button id="btn-notifikasi" type="button">🔔 Aktifkan Notifikasi</button>
    <button id="btn-install" type="button" hidden>⬇️ Install Aplikasi</button>
    <p id="status-notifikasi" role="status"></p>
</header>