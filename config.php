<?php
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
    // Kalau dipanggil dari api.php, balas dalam bentuk JSON
    if (defined('API_MODE')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['code' => 500, 'message' => 'Koneksi database gagal.']]);
        exit;
    }
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

// Fungsi bantu: cegah XSS saat menampilkan data
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Menu aktif: personal / shared
$view = (($_GET['view'] ?? 'personal') === 'shared') ? 'shared' : 'personal';