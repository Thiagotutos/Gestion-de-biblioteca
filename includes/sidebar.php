<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
        <!-- Barra Lateral (Sidebar) -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li>
                    <a href="books.php" class="<?php echo (in_array($current_page, ['books.php', 'add_book.php', 'edit_book.php'])) ? 'active' : ''; ?>">Libros ▾</a>
                    <ul class="sub-menu">
                        <li><a href="books.php" class="<?php echo ($current_page == 'books.php') ? 'active' : ''; ?>">Gestionar Libros</a></li>
                        <li><a href="categories.php" class="<?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">Categorías</a></li>
                        <li><a href="authors.php" class="<?php echo ($current_page == 'authors.php') ? 'active' : ''; ?>">Autores</a></li>
                        <li><a href="publishers.php" class="<?php echo ($current_page == 'publishers.php') ? 'active' : ''; ?>">Editoriales</a></li>
                        <li><a href="racks.php" class="<?php echo ($current_page == 'racks.php') ? 'active' : ''; ?>">Estantes</a></li>
                    </ul>
                </li>
                <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
                <li><a href="issue_books.php" class="<?php echo ($current_page == 'issue_books.php') ? 'active' : ''; ?>">Préstamos</a></li>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] === 'Administrador'): ?>
                <li><a href="users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">Usuarios</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <!-- Área de Contenido -->
        <main class="content">
