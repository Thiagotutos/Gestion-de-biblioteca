<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Verificar si está logueado para mostrar la info del usuario
$usuario_logueado = $_SESSION['user_email'] ?? null;
$usuario_rol = $_SESSION['user_role'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Biblioteca</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=<?php echo file_exists(__DIR__ . '/../style.css') ? filemtime(__DIR__ . '/../style.css') : time(); ?>">
</head>
<body>

    <!-- Navbar Superior -->
    <nav class="navbar">
        <div class="navbar-brand">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Alternar menú lateral" title="Ocultar/Mostrar menú">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <h1>Sistema de Gestión de Biblioteca</h1>
        </div>
        <?php if ($usuario_logueado): ?>
        <div class="user-info">
            <span class="user-email"><?php echo htmlspecialchars($usuario_logueado); ?></span>
            <?php if ($usuario_rol): ?>
                <span class="user-role-badge"><?php echo htmlspecialchars($usuario_rol); ?></span>
            <?php endif; ?>
            <a href="../logout.php" class="btn-logout" title="Cerrar sesión">Salir</a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Contenedor Principal -->
    <div class="main-container" id="mainContainer">
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
