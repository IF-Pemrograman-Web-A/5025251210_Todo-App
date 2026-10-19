<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo List Bagus Mantap</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="app-header">
    <h1><?= e($app_name) ?></h1>
    <button id="theme-toggle" type="button" aria-pressed="false">🌙 Dark Mode</button>
    <button id="btn-notifikasi" type="button">🔔 Aktifkan Notifikasi</button>
    <p id="status-notifikasi" role="status"></p>
</header>