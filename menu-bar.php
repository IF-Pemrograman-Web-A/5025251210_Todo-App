<nav class="menu-bar">
    <ul>
        <li>
            <a href="index.php?view=personal"
               class="<?= $view === 'personal' ? 'active' : '' ?>">Personal</a>
        </li>
        <li>
            <a href="index.php?view=shared"
               class="<?= $view === 'shared' ? 'active' : '' ?>">Shared</a>
        </li>
    </ul>

    <div class="user-switch">
        <label for="user_id">Masuk sebagai</label>
        <select id="user_id">
            <option>Memuat...</option>
        </select>
    </div>
</nav>