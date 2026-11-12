<?php $label = $view === 'shared' ? '(Shared)' : '(Personal)'; ?>

<main class="panel-list" data-view="<?= e($view) ?>">

  <p class="flash" id="flash" role="alert" hidden></p>

  <section class="todo-list">
    <h2>Daftar Todo <?= $label ?></h2>
    <p id="todo-kosong" hidden>Belum ada todo.</p>
    <ul id="todo-list-items" aria-live="polite"></ul>
  </section>

  <section class="todo-form">
    <h2 id="judul-form">Tambah Task <?= $label ?></h2>

    <form id="todo-form">
      <label for="nama">Nama</label>
      <input type="text" id="nama" name="title" placeholder="Nama task" maxlength="150" required>

      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="description" placeholder="Deskripsi task"></textarea>

      <label for="tanggal">Tanggal</label>
      <input type="date" id="tanggal" name="due_date">

      <label for="waktu-notifikasi">Waktu Notifikasi</label>
      <input type="time" id="waktu-notifikasi" name="notif_time">

      <label for="gambar">Gambar</label>
      <input type="file" id="gambar" name="image" accept="image/*">
      <img id="preview-gambar" alt="Pratinjau gambar todo" hidden>

      <button type="button" id="btn-kamera">Buka Kamera</button>

      <div id="area-kamera" hidden>
        <video id="video-kamera" autoplay playsinline muted></video>
        <button type="button" id="btn-jepret">Ambil Foto</button>
        <button type="button" id="btn-tutup-kamera">Tutup Kamera</button>
      </div>

      <button type="submit" id="btn-simpan">Tambah Task</button>
      <button type="button" id="btn-batal" hidden>Batal</button>
    </form>
  </section>

</main>

<aside class="panel-detail">
  <h2>Detail Todo</h2>
  <div class="todo-detail" id="todo-detail"></div>
</aside>