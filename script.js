//dark light mode
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

//form subtmiy
document.querySelectorAll(".todo-checkbox").forEach(function (cb) {
  cb.addEventListener("change", function () {
    cb.form.submit();
  });
});

document.querySelectorAll(".auto-submit").forEach(function (el) {
  el.addEventListener("change", function () {
    el.form.submit();
  });
});

document.querySelectorAll(".form-delete").forEach(function (form) {
  form.addEventListener("submit", function (event) {
    if (!confirm("Yakin hapus task ini?")) event.preventDefault();
  });
});

//preview gambar
const inputImage = document.getElementById("gambar");
const previewImage = document.getElementById("preview-gambar");
const btnCamera = document.getElementById("btn-kamera");
const cameraArea = document.getElementById("area-kamera");
const videoCamera = document.getElementById("video-kamera");
const btnShoot = document.getElementById("btn-jepret");
const btnCloseCamera = document.getElementById("btn-tutup-kamera");

let cameraStream = null;

function showPreview(blob) {
  previewImage.src = URL.createObjectURL(blob);
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
    cameraStream.getTracks().forEach(function (track) {
      track.stop();
    });
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

  document.querySelectorAll(".todo-item").forEach(function (li) {
    const id = li.dataset.id;
    const time = li.dataset.notifTime;
    if (!time || li.dataset.completed === "1" || notified.includes(id)) return;

    const due = new Date((li.dataset.date || todayLocal()) + "T" + time);
    if (now >= due) {
      showReminder(id, li.dataset.title, li.dataset.desc);
      notified.push(id);
    }
  });

  localStorage.setItem("notified", JSON.stringify(notified));
}

updateNotifUI();
checkReminders();
setInterval(checkReminders, 30000);

if ("serviceWorker" in navigator) {
  window.addEventListener("load", function () {
    navigator.serviceWorker
      .register("./sw.js")
      .then(function (reg) { console.log("SW terdaftar, scope:", reg.scope); })
      .catch(function (err) { console.error("SW gagal:", err); });
  });
}