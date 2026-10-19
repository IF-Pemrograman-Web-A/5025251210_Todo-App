<?php

if ($view === 'shared') {
    $stmt = $pdo->query(
        "SELECT t.*, u.name AS owner
         FROM todos t JOIN users u ON u.id = t.user_id
         WHERE t.is_shared = 1
         ORDER BY t.is_completed ASC, t.id DESC"
    );
} else {
    $stmt = $pdo->prepare(
        "SELECT t.*, u.name AS owner
         FROM todos t JOIN users u ON u.id = t.user_id
         WHERE t.is_shared = 0 AND t.user_id = ?
         ORDER BY t.is_completed ASC, t.id DESC"
    );
    $stmt->execute([$current_user_id]);
}
$todos = $stmt->fetchAll();

$edit_todo = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM todos WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_GET['edit'], $current_user_id]);
    $edit_todo = $stmt->fetch() ?: null;
}

$detail = null;
if (isset($_GET['detail'])) {
    $stmt = $pdo->prepare(
        "SELECT t.*, u.name AS owner
         FROM todos t JOIN users u ON u.id = t.user_id
         WHERE t.id = ? AND (t.user_id = ? OR t.is_shared = 1)"
    );
    $stmt->execute([(int)$_GET['detail'], $current_user_id]);
    $detail = $stmt->fetch() ?: null;
}

$f_title = $edit_todo['title'] ?? '';
$f_desc  = $edit_todo['description'] ?? '';
$f_date  = $edit_todo['due_date'] ?? '';
$f_time  = isset($edit_todo['notif_time']) ? substr($edit_todo['notif_time'], 0, 5) : '';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<main class="panel-list">

  <?php if ($flash): ?>
    <p class="flash" role="alert"><?= e($flash) ?></p>
  <?php endif; ?>

  <section class="todo-list">
    <h2>Daftar Todo <?= $view === 'shared' ? '(Shared)' : '(Personal)' ?></h2>

    <?php if (!$todos): ?>
      <p>Belum ada todo.</p>
    <?php endif; ?>

    <ul id="todo-list-items" aria-live="polite">
      <?php foreach ($todos as $t):
          $is_owner = ((int)$t['user_id'] === $current_user_id);
      ?>
        <li class="todo-item<?= $t['is_completed'] ? ' completed' : '' ?>"
            data-id="<?= (int)$t['id'] ?>"
            data-title="<?= e($t['title']) ?>"
            data-desc="<?= e($t['description']) ?>"
            data-date="<?= e($t['due_date']) ?>"
            data-notif-time="<?= e($t['notif_time']) ?>"
            data-completed="<?= (int)$t['is_completed'] ?>">

          <form method="post" action="actions.php" class="inline-form">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input type="checkbox" class="todo-checkbox"
                   aria-label="Tandai selesai"
                   <?= $t['is_completed'] ? 'checked' : '' ?>>
          </form>

          <?php if ($t['image']): ?>
            <img src="uploads/<?= e($t['image']) ?>" width="40" alt="Gambar <?= e($t['title']) ?>">
          <?php endif; ?>

          <a class="todo-title"
             href="index.php?view=<?= e($view) ?>&detail=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>

          <?php if ($view === 'shared'): ?>
            <span class="todo-owner">oleh <?= e($t['owner']) ?></span>
          <?php endif; ?>

          <span class="todo-notif">
            <?= $t['notif_time'] ? '🔔 ' . e(substr($t['notif_time'], 0, 5)) : '' ?>
          </span>
          <span class="todo-status"><?= $t['is_completed'] ? 'Selesai' : 'Belum' ?></span>

          <?php if ($is_owner): ?>
            <a class="btn-link" href="index.php?view=<?= e($view) ?>&edit=<?= (int)$t['id'] ?>">Edit</a>

            <form method="post" action="actions.php" class="inline-form form-delete">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="hidden" name="view" value="<?= e($view) ?>">
              <button type="submit" class="btn-delete">Hapus</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="todo-form">
    <h2><?= $edit_todo ? 'Edit Task' : 'Tambah Task' ?>
        <?= $view === 'shared' ? '(Shared)' : '(Personal)' ?></h2>

    <form id="todo-form" method="post" action="actions.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="<?= $edit_todo ? 'update' : 'create' ?>">
      <input type="hidden" name="view" value="<?= e($view) ?>">
      <?php if ($edit_todo): ?>
        <input type="hidden" name="id" value="<?= (int)$edit_todo['id'] ?>">
      <?php endif; ?>

      <label for="nama">Nama</label>
      <input type="text" id="nama" name="nama" placeholder="Nama task"
             value="<?= e($f_title) ?>" required>

      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="deskripsi" placeholder="Deskripsi task"><?= e($f_desc) ?></textarea>

      <label for="tanggal">Tanggal</label>
      <input type="date" id="tanggal" name="tanggal" value="<?= e($f_date) ?>">

      <label for="waktu-notifikasi">Waktu Notifikasi</label>
      <input type="time" id="waktu-notifikasi" name="waktu-notifikasi" value="<?= e($f_time) ?>">

      <label for="gambar">Gambar</label>
      <input type="file" id="gambar" name="gambar" accept="image/*">
      <img id="preview-gambar" alt="Pratinjau gambar todo"
           <?php if ($edit_todo && $edit_todo['image']): ?>
             src="uploads/<?= e($edit_todo['image']) ?>"
           <?php else: ?>
             hidden
           <?php endif; ?>>

      <button type="button" id="btn-kamera">Buka Kamera</button>

      <div id="area-kamera" hidden>
        <video id="video-kamera" autoplay playsinline muted></video>
        <button type="button" id="btn-jepret">Ambil Foto</button>
        <button type="button" id="btn-tutup-kamera">Tutup Kamera</button>
      </div>

      <button type="submit"><?= $edit_todo ? 'Simpan Perubahan' : 'Tambah Task' ?></button>
      <?php if ($edit_todo): ?>
        <a class="btn-link" href="index.php?view=<?= e($view) ?>">Batal</a>
      <?php endif; ?>
    </form>
  </section>

</main>

<aside class="panel-detail">
  <h2>Detail Todo</h2>
  <div class="todo-detail">
    <?php if ($detail): ?>
      <span class="todo-title"><?= e($detail['title']) ?></span>
      <span class="todo-deskripsi"><?= e($detail['description'] ?: '(tanpa deskripsi)') ?></span>
      <span class="todo-status"><?= $detail['is_completed'] ? 'Selesai' : 'Belum selesai' ?></span>
      <span>Tipe: <?= $detail['is_shared'] ? 'Shared' : 'Personal' ?></span>
      <span>Pemilik: <?= e($detail['owner']) ?></span>
      <span>Tanggal: <?= e($detail['due_date'] ?: '-') ?></span>
      <span>Notifikasi: <?= $detail['notif_time'] ? e(substr($detail['notif_time'], 0, 5)) : '-' ?></span>
      <?php if ($detail['image']): ?>
        <img src="uploads/<?= e($detail['image']) ?>" width="150" alt="Gambar <?= e($detail['title']) ?>">
      <?php endif; ?>
    <?php else: ?>
      <p>Klik judul todo untuk melihat detailnya.</p>
    <?php endif; ?>
  </div>
</aside>