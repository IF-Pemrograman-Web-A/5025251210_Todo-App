const themeToggleButton = document.getElementById("theme-toggle");
const todoForm = document.getElementById("todo-form");
const inputTitle = document.getElementById("input-title");
const todoList = document.getElementById("todo-list");

//ganti dark mode light mode
if (themeToggleButton) {
  themeToggleButton.addEventListener("click", function () {
    document.body.classList.toggle("dark-mode");
    const isDarkMode = document.body.classList.contains("dark-mode");
    themeToggleButton.textContent = isDarkMode ? "☀️" : "🌙";
  });
}

//input
todoForm.addEventListener("submit", function(event) {
  event.preventDefault(); 
  const titleValue = inputTitle.value; 
  if (titleValue.trim() === "") {
    return;
  }
  const newItem = document.createElement("li");
  newItem.className = "todo-item";

  newItem.innerHTML = `
  <input type="checkbox" class="todo-checkbox">
  <span class="todo-title">${titleValue}</span>
  <span class="todo-status">Belum</span>
  <button type="button" class="btn-edit">Edit</button>
  <button type="button" class="btn-delete">Hapus</button>
`;  


  todoList.appendChild(newItem);


  inputTitle.value = "";
});
//delete
todoList.addEventListener("click", function(event) {
  if (event.target.classList.contains("btn-delete")) {
    const itemToDelete = event.target.closest(".todo-item");
    itemToDelete.remove();
  }
});

todoList.addEventListener("change", function(event) {
  if (event.target.classList.contains("todo-checkbox")) {
    const item = event.target.closest(".todo-item");
    const statusSpan = item.querySelector(".todo-status");

    if (event.target.checked) {
      statusSpan.textContent = "Selesai";
      item.classList.add("completed");
    } else {
      statusSpan.textContent = "Belum";
      item.classList.remove("completed");
    }
  }
});

todoList.addEventListener("click", function(event) {
  if (event.target.classList.contains("btn-edit")) {
    const item = event.target.closest(".todo-item");
    const titleSpan = item.querySelector(".todo-title");

    const newTitle = prompt("Edit judul todo:", titleSpan.textContent);

    if (newTitle !== null && newTitle.trim() !== "") {
      titleSpan.textContent = newTitle.trim();
    }
  }
});