CREATE DATABASE IF NOT EXISTS simlist
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simlist;

DROP TABLE IF EXISTS todos;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

CREATE TABLE todos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  due_date     DATE NULL,
  notif_time   TIME NULL,
  image        VARCHAR(255) NULL,
  is_shared    TINYINT(1) NOT NULL DEFAULT 0,
  is_completed TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_todos_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (name) VALUES ('Haikal'), ('Najwan'), ('Rendro');

INSERT INTO todos (user_id, title, description, due_date, notif_time, is_shared, is_completed) VALUES
(1, 'Belajar Jarkom',        'Belajar PPT 1 Pak Roy',          '2026-10-20', '08:00:00', 0, 1),
(1, 'Kerjakan tugas PHP',    'Selesaikan E04 The Iron Citadel', '2026-10-19', '20:00:00', 0, 0),
(1, 'Beli buku catatan',     NULL,                              NULL,         NULL,       0, 0),
(2, 'Rapat organisasi',      'Bahas proker bulan depan',        '2026-10-22', '09:00:00', 1, 0),
(3, 'Siapkan proposal',      'Draft proposal kegiatan',         '2026-10-25', NULL,       1, 0),