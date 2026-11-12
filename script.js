// ================= Dark / light mode =================
const themeToggleButton = document.getElementById("theme-toggle");
const THEME_KEY = "theme";

function applyTheme(theme) {
  const isDark = theme === "dark";
  document.body.classList.toggle("dark-mode", isDark);
  if (themeToggleButton) {
    themeToggleButton.textContent = isDark ? "☀️ Light Mode" : "🌙 Dark Mode";
    themeToggleButton.setAttribute("aria-pressed", String(isDark));
  }
}

applyTheme(localStorage.getItem(THEME_KEY) || "light");

if (themeToggleButton) {
  themeToggleButton.addEventListener("click", function () {
    const newTheme = document.body.classList.contains("dark-mode") ? "light" : "dark";
    applyTheme(newTheme);
    localStorage.setItem(THEME_KEY, newTheme);
  });
}

// ================= Klien REST API =================
const API_BASE = "api.php";
const USER_KEY = "simlist-user-id";
const panelList = document.querySelector(".panel-list");
const VIEW = panelList && panelList.dataset.view === "shared" ? "shared" : "personal";
const VIEW_LABEL = VIEW === "shared" ? "(Shared)" : "(Personal)";

let currentUserId = Number(localStorage.getItem(USER_KEY)) || 1;
let todos = [];
let editingId = null;
let detailId = null;

class ApiError extends Error {
  constructor(status, message, details) {
    super(message);
    this.status = status;
    this.details = details || null;
  }
}

async function api(path, options) {
  options = options || {};
  const headers = { "Accept": "application/json", "X-User-Id": String(currentUserId) };
  let body = options.body;

  // Objek biasa dikirim sebagai JSON; FormData dikirim apa adanya (multipart)
  if (body !== undefined && !(body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(API_BASE + path, { method: options.method || "GET", headers: headers, body: body });
  } catch (e) {
    throw new ApiError(0, "Tidak bisa terhubung ke server. Cek koneksi internet.");
  }

  if (res.status === 204) return null;

  let json = null;
  try { json = await res.json(); } catch (e) { /* respons bukan JSON */ }

  if (!res.ok) {
    const err = (json && json.error) || {};
    throw new ApiError(res.status, err.message || "Terjadi kesalahan (HTTP " + res.status + ").", err.details);
  }
  return json ? json.data : null;
}

// ================= Pesan flash =================
const flashBox = document.getElementById("flash");
let flashTimer = null;

function showFlash(message, isError) {
  flashBox.textContent = message;
  flashBox.classList.toggle("error", Boolean(isError));
  flashBox.hidden = false;
  clearTimeout(flashTimer);
  flashTimer = setTimeout(function () { flashBox.hidden = true; }, 5000);
}

function showError(err) {
  let msg = err.message;
  if (err.details) msg += " " + Object.values(err.details).join(" ");
  showFlash((err.status ? "[" + err.status + "] " : "") + msg, true);
}

function ensureOnline() {
  if (navigator.onLine) return true;
  showFlash("Kamu sedang offline. Perubahan baru bisa disimpan saat online.", true);
  return false;
}

// Membuat elemen DOM dengan aman (textContent, bukan innerHTML -> aman dari XSS)
function el(tag, attrs, children) {
  const node = document.createElement(tag);
  Object.entries(attrs || {}).forEach(function ([key, value]) {
    if (value === null || value === undefined || value === false) return;
    if (key === "text") node.textContent = value;
    else if (key === "className") node.className = value;
    else node.setAttribute(key, value === true ? "" : value);
  });
  (children || []).forEach(function (child) { if (child) node.append(child); });
  return node;
}

// ================= Daftar todo =================
const listEl = document.getElementById("todo-list-items");
const emptyEl = document.getElementById("todo-kosong");

function findTodo(id) {
  return todos.find(function (t) { return t.id === id; });
}

function renderTodos() {
  listEl.replaceChildren();
  emptyEl.hidden = todos.length > 0;

  todos.forEach(function (t) {
    const isOwner = t.user_id === currentUserId;
    listEl.append(
      el("li", { className: "todo-item" + (t.is_completed ? " completed" : ""), "data-id": t.id }, [
        el("input", { type: "checkbox", className: "todo-checkbox", "aria-label": "Tandai selesai", checked: t.is_completed }),
        t.image_url ? el("img", { src: t.image_url, width: 40, alt: "Gambar " + t.title }) : null,
        el("a", { className: "todo-title", href: "#", text: t.title }),
        VIEW === "shared" ? el("span", { className: "todo-owner", text: "oleh " + t.owner }) : null,
        el("span", { className: "todo-notif", text: t.notif_time ? "🔔 " + t.notif_time : "" }),
        el("span", { className: "todo-status", text: t.is_completed ? "Selesai" : "Belum" }),
        isOwner ? el("button", { type: "button", className: "btn-link btn-edit", text: "Edit" }) : null,
        isOwner ? el("button", { type: "button", className: "btn-delete", text: "Hapus" }) : null
      ])
    );
  });
}

async function loadTodos() {
  try {
    todos = await api("/todos?view=" + VIEW);
  } catch (err) {
    todos = [];
    showError(err);
  }
  renderTodos();
  checkReminders();
}

listEl.addEventListener("click", function (event) {
  const li = event.target.closest(".todo-item");
  if (!li) return;
  const id = Number(li.dataset.id);

  if (event.target.closest(".todo-title")) {
    event.preventDefault();
    showDetail(id);
  } else if (event.target.closest(".btn-edit")) {
    startEdit(id);
  } else if (event.target.closest(".btn-delete")) {
    deleteTodo(id);
  }
});

listEl.addEventListener("change", function (event) {
  if (event.target.classList.contains("todo-checkbox")) {
    const id = Number(event.target.closest(".todo-item").dataset.id);
    toggleTodo(id, event.target);
  }
});

async function toggleTodo(id, checkbox) {
  if (!ensureOnline()) {
    checkbox.checked = !checkbox.checked;
    return;
  }
  try {
    await api("/todos/" + id, { method: "PATCH", body: { is_completed: checkbox.checked } });
    await loadTodos();
    if (detailId === id) showDetail(id);
  } catch (err) {
    checkbox.checked = !checkbox.checked;
    showError(err);
  }
}

async function deleteTodo(id) {
  if (!confirm("Yakin hapus task ini?")) return;
  if (!ensureOnline()) return;
  try {
    await api("/todos/" + id, { method: "DELETE" });
    if (editingId === id) resetForm();
    if (detailId === id) clearDetail();
    showFlash("Task dihapus.");
    await loadTodos();
  } catch (err) {
    showError(err);
  }
}

// ================= Detail =================
const detailEl = document.getElementById("todo-detail");

function clearDetail() {
  detailId = null;
  detailEl.replaceChildren(el("p", { text: "Klik judul todo untuk melihat detailnya." }));
}

async function showDetail(id) {
  try {
    const t = await api("/todos/" + id);
    detailId = id;
    detailEl.replaceChildren(
      el("span", { className: "todo-title", text: t.title }),
      el("span", { className: "todo-deskripsi", text: t.description || "(tanpa deskripsi)" }),
      el("span", { className: "todo-status", text: t.is_completed ? "Selesai" : "Belum selesai" }),
      el("span", { text: "Tipe: " + (t.is_shared ? "Shared" : "Personal") }),
      el("span", { text: "Pemilik: " + t.owner }),
      el("span", { text: "Tanggal: " + (t.due_date || "-") }),
      el("span", { text: "Notifikasi: " + (t.notif_time || "-") }),
      t.image_url ? el("img", { src: t.image_url, width: 150, alt: "Gambar " + t.title }) : ""
    );
  } catch (err) {
    showError(err);
  }
}

// ================= Gambar & kamera =================
const inputImage = document.getElementById("gambar");
const previewImage = document.getElementById("preview-gambar");
const btnCamera = document.getElementById("btn-kamera");
const cameraArea = document.getElementById("area-kamera");
const videoCamera = document.getElementById("video-kamera");
const btnShoot = document.getElementById("btn-jepret");
const btnCloseCamera = document.getElementById("btn-tutup-kamera");

let cameraStream = null;

function showPreview(src) {
  previewImage.src = src instanceof Blob ? URL.createObjectURL(src) : src;
  previewImage.hidden = false;
}

function clearPreview() {
  previewImage.hidden = true;
  previewImage.removeAttribute("src");
}

inputImage.addEventListener("change", function () {
  const file = inputImage.files[0];
  if (!file) {
    clearPreview();
    return;
  }
  showPreview(file);
});

async function openCamera() {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
    videoCamera.srcObject = cameraStream;
    cameraArea.hidden = false;
  } catch (error) {
    alert("Kamera tidak bisa dibuka: " + error.message);
  }
}

function closeCamera() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(function (track) { track.stop(); });
    cameraStream = null;
  }
  videoCamera.srcObject = null;
  cameraArea.hidden = true;
}

function takePhoto() {
  const canvas = document.createElement("canvas");
  canvas.width = videoCamera.videoWidth;
  canvas.height = videoCamera.videoHeight;
  canvas.getContext("2d").drawImage(videoCamera, 0, 0);

  canvas.toBlob(function (blob) {
    const file = new File([blob], "kamera.jpg", { type: "image/jpeg" });
    const dt = new DataTransfer();
    dt.items.add(file);
    inputImage.files = dt.files;

    showPreview(blob);
    closeCamera();
  }, "image/jpeg", 0.85);
}

btnCamera.addEventListener("click", openCamera);
btnShoot.addEventListener("click", takePhoto);
btnCloseCamera.addEventListener("click", closeCamera);

// ================= Form tambah / edit =================
const form = document.getElementById("todo-form");
const formTitle = document.getElementById("judul-form");
const btnSubmit = document.getElementById("btn-simpan");
const btnCancel = document.getElementById("btn-batal");

function startEdit(id) {
  const t = findTodo(id);
  if (!t) return;
  editingId = id;

  form.elements["title"].value = t.title;
  form.elements["description"].value = t.description || "";
  form.elements["due_date"].value = t.due_date || "";
  form.elements["notif_time"].value = t.notif_time || "";
  inputImage.value = "";
  if (t.image_url) showPreview(t.image_url); else clearPreview();

  formTitle.textContent = "Edit Task " + VIEW_LABEL;
  btnSubmit.textContent = "Simpan Perubahan";
  btnCancel.hidden = false;
  form.scrollIntoView({ behavior: "smooth" });
}

function resetForm() {
  editingId = null;
  form.reset();
  clearPreview();
  closeCamera();
  formTitle.textContent = "Tambah Task " + VIEW_LABEL;
  btnSubmit.textContent = "Tambah Task";
  btnCancel.hidden = true;
}

btnCancel.addEventListener("click", resetForm);

form.addEventListener("submit", async function (event) {
  event.preventDefault();
  if (!ensureOnline()) return;

  const fields = {
    title: form.elements["title"].value,
    description: form.elements["description"].value,
    due_date: form.elements["due_date"].value,
    notif_time: form.elements["notif_time"].value
  };
  const file = inputImage.files[0];

  btnSubmit.disabled = true;
  try {
    if (editingId === null) {
      // CREATE -> POST multipart (supaya gambar bisa ikut)
      const fd = new FormData();
      Object.entries(fields).forEach(function ([k, v]) { fd.append(k, v); });
      fd.append("is_shared", VIEW === "shared" ? "1" : "0");
      if (file) fd.append("image", file);

      await api("/todos", { method: "POST", body: fd });
      showFlash("Task berhasil ditambahkan.");
    } else {
      // UPDATE -> PUT JSON, lalu gambar lewat endpoint terpisah
      const id = editingId;
      await api("/todos/" + id, { method: "PUT", body: fields });

      if (file) {
        const fd = new FormData();
        fd.append("image", file);
        await api("/todos/" + id + "/image", { method: "POST", body: fd });
      }
      showFlash("Task berhasil diperbarui.");
      if (detailId === id) showDetail(id);
    }
    resetForm();
    await loadTodos();
  } catch (err) {
    showError(err);
  } finally {
    btnSubmit.disabled = false;
  }
});

// ================= Ganti user =================
const userSelect = document.getElementById("user_id");

function setUser(id) {
  currentUserId = id;
  localStorage.setItem(USER_KEY, String(id));
}

async function loadUsers() {
  try {
    const users = await api("/users");
    userSelect.replaceChildren.apply(
      userSelect,
      users.map(function (u) { return el("option", { value: u.id, text: u.name }); })
    );
    const exists = users.some(function (u) { return u.id === currentUserId; });
    if (!exists && users.length) setUser(users[0].id);
    userSelect.value = String(currentUserId);
  } catch (err) {
    showError(err);
  }
}

userSelect.addEventListener("change", function () {
  setUser(Number(userSelect.value));
  resetForm();
  clearDetail();
  loadTodos();
});

// ================= Notifikasi =================
const btnNotif = document.getElementById("btn-notifikasi");
const notifStatus = document.getElementById("status-notifikasi");

function updateNotifUI() {
  if (!("Notification" in window)) {
    btnNotif.hidden = true;
    notifStatus.textContent = "Browser ini tidak mendukung notifikasi.";
    return;
  }
  if (Notification.permission === "granted") {
    btnNotif.hidden = true;
    notifStatus.textContent = "Notifikasi aktif.";
  } else if (Notification.permission === "denied") {
    btnNotif.hidden = true;
    notifStatus.textContent = "Notifikasi diblokir. Ubah lewat pengaturan situs di browser.";
  } else {
    btnNotif.hidden = false;
    notifStatus.textContent = "";
  }
}

btnNotif.addEventListener("click", async function () {
  await Notification.requestPermission();
  updateNotifUI();
});

async function showReminder(id, title, desc) {
  if (!("serviceWorker" in navigator)) return;
  const reg = await navigator.serviceWorker.ready;
  await reg.showNotification("Pengingat: " + title, {
    body: desc || "Waktunya mengerjakan task ini.",
    tag: "todo-" + id,
    data: { id: id }
  });
}

function todayLocal() {
  const d = new Date();
  const pad = function (n) { return String(n).padStart(2, "0"); };
  return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
}

function checkReminders() {
  if (!("Notification" in window) || Notification.permission !== "granted") return;

  const notified = JSON.parse(localStorage.getItem("notified") || "[]");
  const now = new Date();

  todos.forEach(function (t) {
    const key = String(t.id);
    if (!t.notif_time || t.is_completed || notified.includes(key)) return;

    const due = new Date((t.due_date || todayLocal()) + "T" + t.notif_time);
    if (now >= due) {
      showReminder(t.id, t.title, t.description);
      notified.push(key);
    }
  });

  localStorage.setItem("notified", JSON.stringify(notified));
}

setInterval(checkReminders, 30000);

// ================= Service worker & PWA =================
if ("serviceWorker" in navigator) {
  window.addEventListener("load", function () {
    navigator.serviceWorker
      .register("./sw.js")
      .then(function (reg) { console.log("SW terdaftar, scope:", reg.scope); })
      .catch(function (err) { console.error("SW gagal:", err); });
  });
}

const btnInstall = document.getElementById("btn-install");
let deferredInstallPrompt = null;

window.addEventListener("beforeinstallprompt", function (event) {
  event.preventDefault();
  deferredInstallPrompt = event;
  btnInstall.hidden = false;
});

btnInstall.addEventListener("click", async function () {
  if (!deferredInstallPrompt) return;
  deferredInstallPrompt.prompt();
  const choice = await deferredInstallPrompt.userChoice;
  console.log("Pilihan user:", choice.outcome);
  deferredInstallPrompt = null;
  btnInstall.hidden = true;
});

window.addEventListener("appinstalled", function () {
  btnInstall.hidden = true;
  deferredInstallPrompt = null;
});

// ================= Mulai =================
updateNotifUI();
clearDetail();
loadUsers().then(loadTodos);