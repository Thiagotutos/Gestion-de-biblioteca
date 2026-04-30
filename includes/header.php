<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Verificar si está logueado para mostrar la info del usuario
$usuario_logueado = $_SESSION['user_email'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Biblioteca</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Navbar Superior -->
    <nav class="navbar">
        <h1>Sistema de Gestión de Biblioteca</h1>
        <?php if ($usuario_logueado): ?>
        <div class="user-info">
            <?php echo htmlspecialchars($usuario_logueado); ?> | <a href="logout.php">Cerrar Sesión</a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Contenedor Principal -->
    <div class="main-container">
