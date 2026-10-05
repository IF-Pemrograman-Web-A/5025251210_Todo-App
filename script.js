const themeToggleButton = document.getElementById("theme-toggle");
const todoForm = document.getElementById("todo-form");
const inputTitle = document.getElementById("nama");
const todoList = document.getElementById("todo-list-items");

//ganti dark mode light mode
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
    const isDark = !document.body.classList.contains("dark-mode");
    const newTheme = isDark ? "dark" : "light";
    applyTheme(newTheme);
    localStorage.setItem(THEME_KEY, newTheme);
  });
}

//input
let todos = [];

const inputDescription = document.getElementById("deskripsi");
const inputDate = document.getElementById("tanggal");
const inputNotifTime = document.getElementById("waktu-notifikasi");
const inputImage = document.getElementById("gambar");
const previewImage = document.getElementById("preview-gambar");
const btnCamera = document.getElementById("btn-kamera");
const cameraArea = document.getElementById("area-kamera");
const videoCamera = document.getElementById("video-kamera");
const btnShoot = document.getElementById("btn-jepret");
const btnCloseCamera = document.getElementById("btn-tutup-kamera");

let cameraStream = null;
let capturedImage = null; 


//indexdb
const DB_NAME = "simlist-db";
const STORE_NAME = "todos";
let db;

function openDatabase() {
  return new Promise(function (resolve, reject) {
    const request = indexedDB.open(DB_NAME, 1);

    //start
    request.onupgradeneeded = function () {
      request.result.createObjectStore(STORE_NAME, {
        keyPath: "id",
        autoIncrement: true
      });
    };

    request.onsuccess = function () {
      resolve(request.result);
    };
    request.onerror = function () {
      reject(request.error);
    };
  });
}
function runRequest(mode, action) {
  return new Promise(function (resolve, reject) {
    const tx = db.transaction(STORE_NAME, mode);
    const request = action(tx.objectStore(STORE_NAME));
    tx.oncomplete = function () {
      resolve(request.result);
    };
    tx.onerror = function () {
      reject(tx.error);
    };
  });
}

function dbGetAll() {
  return runRequest("readonly", function (store) {
    return store.getAll();
  });
}

function dbSave(todo) {
  // put kalau id sudah ada, belum ditambhkan
  return runRequest("readwrite", function (store) {
    return store.put(todo);
  });
}

function dbDelete(id) {
  return runRequest("readwrite", function (store) {
    return store.delete(id);
  });
}

//ambil dari db
async function loadTodos() {
  todos = await dbGetAll();
  renderTodos();
}

function renderTodos() {
  todoList.innerHTML = "";

  todos.forEach(function (todo) {
    const li = document.createElement("li");
    li.className = "todo-item" + (todo.completed ? " completed" : "");
    li.dataset.id = todo.id;

    li.innerHTML = `
      <input type="checkbox" class="todo-checkbox" ${todo.completed ? "checked" : ""}>
      <span class="todo-title"></span>
      <span class="todo-notif"></span>
      <span class="todo-status">${todo.completed ? "Selesai" : "Belum"}</span>
      <button type="button" class="btn-edit">Edit</button>
      <button type="button" class="btn-delete">Hapus</button>
    `;

    li.querySelector(".todo-title").textContent = todo.title;

    if (todo.notifTime) {
      li.querySelector(".todo-notif").textContent = "🔔 " + todo.notifTime;
    }

    if (todo.image) {
      const img = document.createElement("img");
      img.src = URL.createObjectURL(todo.image);
      img.alt = "Gambar untuk " + todo.title;
      img.width = 40;
      li.insertBefore(img, li.querySelector(".todo-title"));
    }

    todoList.appendChild(li);
  });
}

function findTodo(event) {
  const id = Number(event.target.closest(".todo-item").dataset.id);
  return todos.find(function (t) {
    return t.id === id;
  });
}

//tunjukkan gambar saat user meminta
function showPreview(blob) {
  previewImage.src = URL.createObjectURL(blob);
  previewImage.alt = "Pratinjau gambar todo";
  previewImage.hidden = false;
}

function clearPreview() {
  previewImage.hidden = true;
  previewImage.removeAttribute("src");
}

// Pilih dari file
inputImage.addEventListener("change", function () {
  const file = inputImage.files[0];
  capturedImage = null; 

  if (!file) {
    clearPreview();
    return;
  }
  showPreview(file);
});

//ambil media
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
    capturedImage = blob;
    inputImage.value = ""; 
    showPreview(blob);
    closeCamera();
  }, "image/jpeg", 0.85);
}

btnCamera.addEventListener("click", openCamera);
btnShoot.addEventListener("click", takePhoto);
btnCloseCamera.addEventListener("click", closeCamera);



//tambah task
todoForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const titleValue = inputTitle.value.trim();
  if (titleValue === "") {
    return;
  }

  await dbSave({
  title: titleValue,
  description: inputDescription.value.trim(),
  date: inputDate.value,
  notifTime: inputNotifTime.value,
  image: capturedImage || inputImage.files[0] || null,
  completed: false,
  notified: false   // baru
});

todoForm.reset();
capturedImage = null;                                     
closeCamera();                                            
clearPreview();                                           
await loadTodos();
});

// edit dan hapus event
todoList.addEventListener("click", async function (event) {
  if (event.target.classList.contains("btn-delete")) {
    const todo = findTodo(event);
    await dbDelete(todo.id);
    await loadTodos();
  }

  if (event.target.classList.contains("btn-edit")) {
    const todo = findTodo(event);
    const newTitle = prompt("Edit judul todo:", todo.title);
    if (newTitle !== null && newTitle.trim() !== "") {
      todo.title = newTitle.trim();
      await dbSave(todo);
      await loadTodos();
    }
  }
});

// notifikasi
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

// notif service worker
async function showReminder(todo) {
  const reg = await navigator.serviceWorker.ready;
  await reg.showNotification("Pengingat: " + todo.title, {
    body: todo.description || "Waktunya mengerjakan task ini.",
    tag: "todo-" + todo.id,
    data: { id: todo.id }
  });
}

function getDueTime(todo) {
  if (!todo.notifTime) return null;
  const today = new Date().toISOString().slice(0, 10);
  const date = todo.date || today;
  return new Date(date + "T" + todo.notifTime);
}

async function checkReminders() {
  if (!("Notification" in window) || Notification.permission !== "granted") return;

  const now = new Date();
  let changed = false;

  for (const todo of todos) {
    const due = getDueTime(todo);
    if (due && !todo.notified && !todo.completed && now >= due) {
      await showReminder(todo);
      todo.notified = true;
      await dbSave(todo);
      changed = true;
    }
  }

  if (changed) await loadTodos();
}

todoList.addEventListener("change", async function (event) {
  if (event.target.classList.contains("todo-checkbox")) {
    const todo = findTodo(event);
    todo.completed = event.target.checked;
    await dbSave(todo);
    await loadTodos();
  }
});

async function init() {
  db = await openDatabase();
  await loadTodos();
  updateNotifUI();
  checkReminders();
  setInterval(checkReminders, 30000);
}

init();

if ("serviceWorker" in navigator) {
  window.addEventListener("load", function () {
    navigator.serviceWorker
      .register("./sw.js")
      .then(function (reg) { console.log("SW terdaftar, scope:", reg.scope); })
      .catch(function (err) { console.error("SW gagal:", err); });
  });
} 