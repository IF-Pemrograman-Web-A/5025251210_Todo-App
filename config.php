<?php
session_start();

$app_name = "SimList";

// Koneksi database (default XAMPP)
$db_host = "127.0.0.1";
$db_name = "simlist";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

// Fungsi bantu: cegah XSS saat menampilkan data
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Daftar user & user yang sedang aktif (disimpan di session)
$users = $pdo->query("SELECT id, name FROM users ORDER BY id")->fetchAll();
$current_user_id = (int)($_SESSION['user_id'] ?? 1);

// Menu aktif: personal / shared
$view = (($_GET['view'] ?? 'personal') === 'shared') ? 'shared' : 'personal';