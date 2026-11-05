<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
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
    <link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
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
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" name="password" id="password" class="form-control" required placeholder="admin123" style="padding-right: 42px;">
                    <button type="button" class="btn-pwd-eye" style="position: absolute; right: 8px; z-index: 2;" onclick="toggleLoginPassword(this)" title="Mostrar / Ocultar contraseña">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 20px;">Iniciar Sesión</button>
        </form>
    </div>

<script>
function toggleLoginPassword(btn) {
    const inp = document.getElementById('password');
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.style.color = 'var(--color-primary)';
        btn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        `;
    } else {
        inp.type = 'password';
        btn.style.color = '#64748b';
        btn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        `;
    }
}
</script>

</body>
</html>
