<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Biblioteca</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-container">

    <div class="login-box">
        <h2>Ingreso al Sistema</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-warning" style="margin-bottom: 15px; padding: 10px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="auth.php" method="POST">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="admin@biblioteca.com">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="admin123">
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 20px;">Iniciar Sesión</button>
        </form>
        <p style="margin-top: 20px; font-size: 0.9rem; color: #555;">
            Datos de prueba:<br> admin@biblioteca.com / admin123
        </p>
    </div>

</body>
</html>
