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

    <form method="post" action="actions.php" class="user-switch">
        <input type="hidden" name="action" value="switch_user">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <label for="user_id">Masuk sebagai</label>
        <select name="user_id" id="user_id" class="auto-submit">
            <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"
                    <?= (int)$u['id'] === $current_user_id ? 'selected' : '' ?>>
                    <?= e($u['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Ganti</button></noscript>
    </form>
</nav>