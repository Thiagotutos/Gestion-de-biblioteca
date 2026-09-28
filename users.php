<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if ($nombre && $email) {
        $stmt = $pdo->prepare("INSERT INTO users (nombre, email, rol, password) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$nombre, $email, $rol, $password]);
            $_SESSION['success_msg'] = "Usuario agregado exitosamente.";
        } catch (Exception $e) {
            $error = "El correo ya está registrado.";
        }
        header('Location: users.php');
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['user_id']) { // No borrarse a si mismo
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $_SESSION['success_msg'] = "Usuario eliminado.";
    } else {
        $_SESSION['error_msg'] = "No puedes eliminar tu propia cuenta.";
    }
    header('Location: users.php');
    exit;
}

$stmt = $pdo->query("SELECT id, nombre, email, rol, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar Usuarios</h2>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display: flex; gap: 20px;">
    <!-- Formulario -->
    <div class="form-container" style="flex: 1; height: max-content;">
        <h3>Agregar Usuario</h3><br>
        <form method="POST">
            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Correo Electrónico</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Rol</label>
                <select name="rol" class="form-control" required>
                    <option value="Lector">Lector / Estudiante</option>
                    <option value="Bibliotecario">Bibliotecario</option>
                    <option value="Administrador">Administrador</option>
                </select>
            </div>
            <div class="form-group">
                <label>Contraseña Provisional</label>
                <input type="text" name="password" class="form-control" value="123456" required>
            </div>
            <button type="submit" name="add_user" class="btn btn-primary">Crear Usuario</button>
        </form>
    </div>

    <!-- Tabla -->
    <div style="flex: 2;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><span class="status-badge status-borrowed"><?php echo htmlspecialchars($user['rol']); ?></span></td>
                    <td>
                        <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-delete" onclick="return confirm('¿Borrar usuario?');">Borrar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
