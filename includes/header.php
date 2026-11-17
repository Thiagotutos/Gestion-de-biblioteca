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
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1">
    <link rel="alternate icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%231e3a8a'/%3E%3Cpath d='M16 11c-2.2-1.6-5.2-2-8-2v13c2.8 0 5.8.4 8 2 2.2-1.6 5.2-2 8-2V9c-2.8 0-5.8.4-8 2z' fill='%23ffffff'/%3E%3Cpath d='M16 11v13' stroke='%23cbd5e1' stroke-width='1.2'/%3E%3Cpath d='M15 7h2v6l-1-0.8-1 0.8V7z' fill='%23f59e0b'/%3E%3C/svg%3E">
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
        <?php else: ?>
        <div class="user-info" style="display: flex; align-items: center; gap: 10px;">
            <span class="user-role-badge" style="background: rgba(255,255,255,0.22); color: white; border: 1px solid rgba(255,255,255,0.35);">Modo Lector</span>
            <a href="../login.php" class="btn-logout" style="background-color: var(--color-primary); color: white; border: 1px solid rgba(255,255,255,0.45); text-decoration: none; padding: 6px 14px; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;" title="Iniciar sesión como Bibliotecario o Administrador">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Iniciar Sesión
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Contenedor Principal -->
    <div class="main-container" id="mainContainer">
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
