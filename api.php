<?php
define('API_MODE', true);
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2 MB
const ALLOWED_IMAGES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/* =========================================================
   Helper response
   ========================================================= */
function json_out(int $code, $data = null): void {
    http_response_code($code);
    if ($code !== 204 && $data !== null) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function ok(int $code, $data): void {
    json_out($code, ['data' => $data]);
}

function fail(int $code, string $message, array $details = []): void {
    $error = ['code' => $code, 'message' => $message];
    if ($details) $error['details'] = $details;
    json_out($code, ['error' => $error]);
}

function method_not_allowed(array $allowed): void {
    header('Allow: ' . implode(', ', $allowed));
    fail(405, 'Method ' . $_SERVER['REQUEST_METHOD'] . ' tidak diizinkan di endpoint ini.');
}

/* =========================================================
   Helper request
   ========================================================= */
function read_body(): array {
    $method = $_SERVER['REQUEST_METHOD'];
    $type   = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

    // Form/multipart hanya diproses PHP untuk POST
    if ($method === 'POST' &&
        (str_contains($type, 'multipart/form-data') || str_contains($type, 'application/x-www-form-urlencoded'))) {
        // Melebihi post_max_size: PHP mengosongkan $_POST dan $_FILES
        if ($length > 0 && empty($_POST) && empty($_FILES)) {
            fail(413, 'Ukuran request terlalu besar.');
        }
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];

    if (!str_contains($type, 'application/json')) {
        fail(415, $method === 'POST'
            ? 'Content-Type harus application/json atau multipart/form-data.'
            : 'Content-Type harus application/json.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) fail(400, 'Body bukan JSON yang valid.');
    return $data;
}

function to_bool($value): ?bool {
    if (is_bool($value)) return $value;
    if (!is_scalar($value)) return null;
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
}

// Ambil teks dari input; null = tipe datanya salah (misal array)
function text_field(array $in, string $key): ?string {
    $v = $in[$key] ?? '';
    if ($v === null) return '';
    return is_scalar($v) ? trim((string)$v) : null;
}

/* =========================================================
   User & todo
   ========================================================= */
function current_user(PDO $pdo): array {
    $raw = $_SERVER['HTTP_X_USER_ID'] ?? '';
    if ($raw === '') fail(401, 'Header X-User-Id wajib dikirim.');
    if (!ctype_digit($raw)) fail(400, 'X-User-Id harus berupa angka.');

    $st = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $st->execute([(int)$raw]);
    $user = $st->fetch();
    if (!$user) fail(404, "User dengan id $raw tidak ditemukan.");
    return $user;
}

function find_todo(PDO $pdo, int $id): array {
    $st = $pdo->prepare(
        "SELECT t.*, u.name AS owner
         FROM todos t JOIN users u ON u.id = t.user_id
         WHERE t.id = ?"
    );
    $st->execute([$id]);
    $todo = $st->fetch();
    if (!$todo) fail(404, "Todo dengan id $id tidak ditemukan.");
    return $todo;
}

function format_todo(array $t): array {
    return [
        'id'           => (int)$t['id'],
        'user_id'      => (int)$t['user_id'],
        'owner'        => $t['owner'] ?? null,
        'title'        => $t['title'],
        'description'  => $t['description'],
        'due_date'     => $t['due_date'],
        'notif_time'   => $t['notif_time'] ? substr($t['notif_time'], 0, 5) : null,
        'image'        => $t['image'],
        'image_url'    => $t['image'] ? 'uploads/' . $t['image'] : null,
        'is_shared'    => (bool)$t['is_shared'],
        'is_completed' => (bool)$t['is_completed'],
        'created_at'   => $t['created_at'],
    ];
}

function is_owner(array $todo, array $user): bool {
    return (int)$todo['user_id'] === (int)$user['id'];
}

function require_can_view(array $todo, array $user): void {
    if (!is_owner($todo, $user) && !$todo['is_shared']) {
        fail(403, 'Todo ini milik user lain dan tidak dibagikan.');
    }
}

function require_owner(array $todo, array $user, string $verb): void {
    if (!is_owner($todo, $user)) {
        fail(403, "Hanya pemilik yang boleh $verb todo ini.");
    }
}

/* =========================================================
   Validasi
   $partial = true  -> PATCH (hanya field yang dikirim)
   $partial = false -> POST/PUT (field yang tidak dikirim = kosong)
   ========================================================= */
function validate_todo(array $in, bool $partial): array {
    $out = [];
    $errors = [];

    if (!$partial || array_key_exists('title', $in)) {
        $title = text_field($in, 'title');
        if ($title === null)              $errors['title'] = 'Judul harus berupa teks.';
        elseif ($title === '')            $errors['title'] = 'Judul wajib diisi.';
        elseif (mb_strlen($title) > 150)  $errors['title'] = 'Judul maksimal 150 karakter.';
        else                              $out['title'] = $title;
    }

    if (!$partial || array_key_exists('description', $in)) {
        $desc = text_field($in, 'description');
        if ($desc === null) $errors['description'] = 'Deskripsi harus berupa teks.';
        else                $out['description'] = $desc === '' ? null : $desc;
    }

    if (!$partial || array_key_exists('due_date', $in)) {
        $date = text_field($in, 'due_date');
        if ($date === '') {
            $out['due_date'] = null;
        } else {
            $dt = $date === null ? false : DateTime::createFromFormat('!Y-m-d', $date);
            if (!$dt || $dt->format('Y-m-d') !== $date) $errors['due_date'] = 'Format tanggal harus YYYY-MM-DD.';
            else $out['due_date'] = $date;
        }
    }

    if (!$partial || array_key_exists('notif_time', $in)) {
        $time = text_field($in, 'notif_time');
        if ($time === '') {
            $out['notif_time'] = null;
        } elseif ($time === null || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
            $errors['notif_time'] = 'Format waktu harus HH:MM.';
        } else {
            $out['notif_time'] = strlen($time) === 5 ? $time . ':00' : $time;
        }
    }

    if (array_key_exists('is_completed', $in)) {
        $done = to_bool($in['is_completed']);
        if ($done === null) $errors['is_completed'] = 'is_completed harus true atau false.';
        else $out['is_completed'] = $done ? 1 : 0;
    }

    return [$out, $errors];
}

/* =========================================================
   Upload gambar
   ========================================================= */
function upload_image(): ?string {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // tidak ada file
    }
    $file = $_FILES['image'];

    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || $file['size'] > MAX_IMAGE_SIZE) {
        fail(413, 'Ukuran gambar maksimal 2 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        fail(400, 'Upload gambar gagal (kode ' . $file['error'] . ').');
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset(ALLOWED_IMAGES[$info['mime']])) {
        fail(415, 'Gambar harus JPG, PNG, GIF, atau WEBP.');
    }

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        fail(500, 'Folder uploads tidak bisa dibuat.');
    }

    $name = uniqid('img_', true) . '.' . ALLOWED_IMAGES[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        fail(500, 'Gambar gagal disimpan.');
    }
    return $name;
}

function delete_image_file(?string $name): void {
    if ($name) {
        $path = __DIR__ . '/uploads/' . basename($name);
        if (is_file($path)) unlink($path);
    }
}

/* =========================================================
   Handler /users
   ========================================================= */
function route_users(PDO $pdo, string $method, ?int $id, ?string $sub): void {
    if ($sub !== null) fail(404, 'Endpoint tidak ditemukan.');
    if ($method !== 'GET') method_not_allowed(['GET']);

    if ($id === null) {
        $rows = $pdo->query("SELECT id, name FROM users ORDER BY id")->fetchAll();
        ok(200, array_map(fn($u) => ['id' => (int)$u['id'], 'name' => $u['name']], $rows));
    }

    $st = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $st->execute([$id]);
    $user = $st->fetch();
    if (!$user) fail(404, "User dengan id $id tidak ditemukan.");
    ok(200, ['id' => (int)$user['id'], 'name' => $user['name']]);
}

/* =========================================================
   Handler /todos
   ========================================================= */
function list_todos(PDO $pdo, array $user): void {
    $view = $_GET['view'] ?? 'personal';
    if (!in_array($view, ['personal', 'shared'], true)) {
        fail(400, "Parameter view harus 'personal' atau 'shared'.");
    }

    if ($view === 'shared') {
        $st = $pdo->query(
            "SELECT t.*, u.name AS owner
             FROM todos t JOIN users u ON u.id = t.user_id
             WHERE t.is_shared = 1
             ORDER BY t.is_completed ASC, t.id DESC"
        );
    } else {
        $st = $pdo->prepare(
            "SELECT t.*, u.name AS owner
             FROM todos t JOIN users u ON u.id = t.user_id
             WHERE t.is_shared = 0 AND t.user_id = ?
             ORDER BY t.is_completed ASC, t.id DESC"
        );
        $st->execute([$user['id']]);
    }
    ok(200, array_map('format_todo', $st->fetchAll()));
}

function create_todo(PDO $pdo, array $user): void {
    $in = read_body();
    [$data, $errors] = validate_todo($in, false);

    $shared = array_key_exists('is_shared', $in) ? to_bool($in['is_shared']) : false;
    if ($shared === null) $errors['is_shared'] = 'is_shared harus true atau false.';

    if ($errors) fail(422, 'Data todo tidak valid.', $errors);

    $image = upload_image();

    $st = $pdo->prepare(
        "INSERT INTO todos (user_id, title, description, due_date, notif_time, image, is_shared, is_completed)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $st->execute([
        $user['id'], $data['title'], $data['description'], $data['due_date'],
        $data['notif_time'], $image, $shared ? 1 : 0, $data['is_completed'] ?? 0,
    ]);

    $newId = (int)$pdo->lastInsertId();
    http_response_code(201);
    header('Location: api.php/todos/' . $newId);
    ok(201, format_todo(find_todo($pdo, $newId)));
}

function show_todo(PDO $pdo, array $user, int $id): void {
    $todo = find_todo($pdo, $id);
    require_can_view($todo, $user);
    ok(200, format_todo($todo));
}

function update_todo(PDO $pdo, array $user, int $id, bool $partial): void {
    $todo = find_todo($pdo, $id);
    require_can_view($todo, $user);
    $in = read_body();

    // User lain di todo shared hanya boleh mencentang selesai lewat PATCH
    if (!is_owner($todo, $user)) {
        if (!$partial || array_diff(array_keys($in), ['is_completed'])) {
            fail(403, 'Di todo shared milik user lain, kamu hanya boleh mengubah status selesai (PATCH is_completed).');
        }
    }

    [$data, $errors] = validate_todo($in, $partial);
    if ($errors) fail(422, 'Data todo tidak valid.', $errors);
    if (!$data) {
        fail(400, 'Tidak ada field yang diubah. Field yang didukung: title, description, due_date, notif_time, is_completed.');
    }

    // Nama kolom berasal dari validate_todo (sudah pasti aman)
    $sets = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));
    $st = $pdo->prepare("UPDATE todos SET $sets WHERE id = ?");
    $st->execute([...array_values($data), $id]);

    ok(200, format_todo(find_todo($pdo, $id)));
}

function delete_todo(PDO $pdo, array $user, int $id): void {
    $todo = find_todo($pdo, $id);
    require_owner($todo, $user, 'menghapus');

    delete_image_file($todo['image']);
    $st = $pdo->prepare("DELETE FROM todos WHERE id = ?");
    $st->execute([$id]);
    json_out(204);
}

function replace_image(PDO $pdo, array $user, int $id): void {
    $todo = find_todo($pdo, $id);
    require_owner($todo, $user, 'mengubah gambar');
    read_body(); // cek ukuran request / format body

    $image = upload_image();
    if ($image === null) fail(422, 'Data tidak valid.', ['image' => 'File gambar wajib dikirim (field "image").']);

    delete_image_file($todo['image']);
    $st = $pdo->prepare("UPDATE todos SET image = ? WHERE id = ?");
    $st->execute([$image, $id]);
    ok(200, format_todo(find_todo($pdo, $id)));
}

function remove_image(PDO $pdo, array $user, int $id): void {
    $todo = find_todo($pdo, $id);
    require_owner($todo, $user, 'menghapus gambar');
    if (!$todo['image']) fail(404, 'Todo ini tidak punya gambar.');

    delete_image_file($todo['image']);
    $st = $pdo->prepare("UPDATE todos SET image = NULL WHERE id = ?");
    $st->execute([$id]);
    json_out(204);
}

function route_todos(PDO $pdo, string $method, ?int $id, ?string $sub): void {
    $user = current_user($pdo);

    // /todos
    if ($id === null) {
        if ($method === 'GET')  list_todos($pdo, $user);
        if ($method === 'POST') create_todo($pdo, $user);
        method_not_allowed(['GET', 'POST']);
    }

    // /todos/{id}
    if ($sub === null) {
        switch ($method) {
            case 'GET':    show_todo($pdo, $user, $id);
            case 'PUT':    update_todo($pdo, $user, $id, false);
            case 'PATCH':  update_todo($pdo, $user, $id, true);
            case 'DELETE': delete_todo($pdo, $user, $id);
            default:       method_not_allowed(['GET', 'PUT', 'PATCH', 'DELETE']);
        }
    }

    // /todos/{id}/image
    if ($sub === 'image') {
        if ($method === 'POST')   replace_image($pdo, $user, $id);
        if ($method === 'DELETE') remove_image($pdo, $user, $id);
        method_not_allowed(['POST', 'DELETE']);
    }

    fail(404, 'Endpoint tidak ditemukan.');
}

/* =========================================================
   Router
   Contoh URL: api.php/todos, api.php/todos/5, api.php/todos/5/image
   ========================================================= */
$method   = $_SERVER['REQUEST_METHOD'];
$path     = trim($_SERVER['PATH_INFO'] ?? '', '/');
$segments = $path === '' ? [] : explode('/', $path);

if (count($segments) > 3) fail(404, 'Endpoint tidak ditemukan.');

$resource = $segments[0] ?? '';
$id = null;
if (isset($segments[1])) {
    if (!ctype_digit($segments[1]) || (int)$segments[1] === 0) fail(400, 'ID harus berupa angka positif.');
    $id = (int)$segments[1];
}
$sub = $segments[2] ?? null;

try {
    switch ($resource) {
        case 'users': route_users($pdo, $method, $id, $sub);
        case 'todos': route_todos($pdo, $method, $id, $sub);
        default:      fail(404, 'Endpoint tidak ditemukan. Gunakan api.php/todos atau api.php/users.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    fail(500, 'Terjadi kesalahan pada database.');
}