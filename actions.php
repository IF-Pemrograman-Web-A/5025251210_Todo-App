<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? '';
$view   = (($_POST['view'] ?? 'personal') === 'shared') ? 'shared' : 'personal';
$id     = (int)($_POST['id'] ?? 0);

function back($view, $msg = null) {
    if ($msg) $_SESSION['flash'] = $msg;
    header("Location: index.php?view=" . $view);
    exit;
}

function find_todo($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM todos WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function upload_image() {
    if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
        return null; // tidak ada file baru
    }
    $file = $_FILES['gambar'];

    if ($file['size'] > 2 * 1024 * 1024) {           //maks 2 MB
        return false;
    }
    $info = @getimagesize($file['tmp_name']);          //cek gambar
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!$info || !isset($allowed[$info['mime']])) {
        return false;
    }

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $name = uniqid('img_', true) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return false;
    }
    return $name;
}

function delete_image_file($name) {
    if ($name) {
        $path = __DIR__ . '/uploads/' . basename($name);
        if (is_file($path)) unlink($path);
    }
}

function form_data() {
    $title = trim($_POST['nama'] ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $date  = ($_POST['tanggal'] ?? '') !== '' ? $_POST['tanggal'] : null;
    $time  = ($_POST['waktu-notifikasi'] ?? '') !== '' ? $_POST['waktu-notifikasi'] : null;
    return [$title, $desc, $date, $time];
}

switch ($action) {

    case 'switch_user':
        $uid = (int)($_POST['user_id'] ?? 1);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        if ($stmt->fetch()) $_SESSION['user_id'] = $uid;
        back($view);

    case 'create':
        [$title, $desc, $date, $time] = form_data();
        if ($title === '') back($view, 'Nama task tidak boleh kosong.');

        $image = upload_image();
        if ($image === false) back($view, 'Gambar ditolak (harus JPG/PNG/GIF/WEBP, maks 2 MB).');

        $stmt = $pdo->prepare(
            "INSERT INTO todos (user_id, title, description, due_date, notif_time, image, is_shared)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $current_user_id, $title, $desc, $date, $time, $image,
            $view === 'shared' ? 1 : 0
        ]);
        back($view, 'Task berhasil ditambahkan.');

    case 'update':
        $todo = find_todo($pdo, $id);
        if (!$todo || (int)$todo['user_id'] !== $current_user_id) {
            back($view, 'Kamu tidak berhak mengubah task ini.');
        }
        [$title, $desc, $date, $time] = form_data();
        if ($title === '') back($view, 'Nama task tidak boleh kosong.');

        $image = upload_image();
        if ($image === false) back($view, 'Gambar ditolak (harus JPG/PNG/GIF/WEBP, maks 2 MB).');

        if ($image !== null) {               //
            delete_image_file($todo['image']);
        } else {
            $image = $todo['image'];         //
        }

        $stmt = $pdo->prepare(
            "UPDATE todos SET title = ?, description = ?, due_date = ?, notif_time = ?, image = ?
             WHERE id = ?"
        );
        $stmt->execute([$title, $desc, $date, $time, $image, $id]);
        back($view, 'Task berhasil diperbarui.');

    case 'toggle':
        $todo = find_todo($pdo, $id);
        if ($todo && ((int)$todo['user_id'] === $current_user_id || $todo['is_shared'])) {
            $stmt = $pdo->prepare("UPDATE todos SET is_completed = 1 - is_completed WHERE id = ?");
            $stmt->execute([$id]);
        }
        back($view);

    case 'delete':
        $todo = find_todo($pdo, $id);
        if (!$todo || (int)$todo['user_id'] !== $current_user_id) {
            back($view, 'Kamu tidak berhak menghapus task ini.');
        }
        delete_image_file($todo['image']);
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ?");
        $stmt->execute([$id]);
        back($view, 'Task dihapus.');

    default:
        back($view);
}