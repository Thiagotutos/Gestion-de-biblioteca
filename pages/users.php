<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// -------------------------------------------------------------
// 1. Verificación suave de la columna plain_password
// -------------------------------------------------------------
try {
    $pdo->query("SELECT plain_password FROM users LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NULL AFTER password");
        $pdo->exec("UPDATE users SET plain_password = 'admin123' WHERE plain_password IS NULL AND email IN ('admin@biblioteca.com', 'bibliotecario@biblioteca.com', 'lector@biblioteca.com')");
    } catch (Exception $e2) {
        // En caso de que no tenga permisos de alteración
    }
}

// -------------------------------------------------------------
// 2. Procesar Alta o Modificación de Usuario
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'Lector';
        $raw_pass = trim($_POST['password'] ?? '');

        if ($nombre === '' || $email === '') {
            $_SESSION['error_msg'] = "El nombre y el correo electrónico son obligatorios.";
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }

        // Validación estricta de correo electrónico (debe incluir dominio y TLD válido, ej: @gmail.com)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
            $_SESSION['error_msg'] = "El correo electrónico '$email' no es válido. Debe incluir un dominio completo (ejemplo: usuario@gmail.com).";
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }

        try {
            if ($user_id > 0) {
                // Modo Edición
                if ($raw_pass !== '') {
                    $hash_pass = password_hash($raw_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET nombre = ?, email = ?, rol = ?, password = ?, plain_password = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $rol, $hash_pass, $raw_pass, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET nombre = ?, email = ?, rol = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $rol, $user_id]);
                }
                $_SESSION['success_msg'] = "Usuario actualizado exitosamente.";
            } else {
                // Modo Alta
                if ($raw_pass === '') {
                    $raw_pass = '123456'; // Contraseña provisional por defecto
                }
                $hash_pass = password_hash($raw_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (nombre, email, rol, password, plain_password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $rol, $hash_pass, $raw_pass]);
                $_SESSION['success_msg'] = "Usuario creado exitosamente.";
            }
            header('Location: users.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "El correo '$email' ya está registrado o hubo un error: " . $e->getMessage();
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }
    }
}

// -------------------------------------------------------------
// 3. Procesar Eliminación
// -------------------------------------------------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) { // No borrarse a sí mismo
        try {
            // Verificar si el usuario tiene transacciones de préstamos registradas
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE librarian_id = ? OR user_id = ?");
            $stmt_chk->execute([$id, $id]);
            if ($stmt_chk->fetchColumn() > 0) {
                $_SESSION['error_msg'] = "No se puede eliminar este usuario porque tiene préstamos o devoluciones registradas en el historial.";
            } else {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                $_SESSION['success_msg'] = "Usuario eliminado exitosamente.";
            }
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Error al eliminar usuario: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "No puedes eliminar tu propia cuenta de sesión actual.";
    }
    header('Location: users.php');
    exit;
}

// -------------------------------------------------------------
// 4. Cargar Usuario en Edición (si aplica)
// -------------------------------------------------------------
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt_ed = $pdo->prepare("SELECT id, nombre, email, rol, plain_password FROM users WHERE id = ?");
    $stmt_ed->execute([$edit_id]);
    $edit_user = $stmt_ed->fetch();
}

// -------------------------------------------------------------
// 5. Listar Usuarios
// -------------------------------------------------------------
$stmt = $pdo->query("SELECT id, nombre, email, rol, password, plain_password, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar Usuarios</h2>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
<?php endif; ?>

<div class="split-layout">
    <!-- Formulario (Alta / Edición) -->
    <div class="form-container form-pane">
        <h3><?php echo $edit_user ? 'Editar Usuario' : 'Agregar Usuario'; ?></h3><br>
        <form method="POST">
            <?php if ($edit_user): ?>
            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
            <?php endif; ?>

            <div class="form-grid-fields">
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($edit_user['nombre'] ?? ''); ?>" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="form-group">
                    <label>Correo Electrónico *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" required placeholder="correo@ejemplo.com" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" title="Ingrese un correo electrónico válido con dominio completo (ej: usuario@gmail.com)">
                </div>
                <div class="form-group">
                    <label>Rol de Usuario *</label>
                    <select name="rol" class="form-control" required>
                        <option value="Lector" <?php echo (($edit_user['rol'] ?? '') === 'Lector') ? 'selected' : ''; ?>>Lector / Estudiante</option>
                        <option value="Bibliotecario" <?php echo (($edit_user['rol'] ?? '') === 'Bibliotecario') ? 'selected' : ''; ?>>Bibliotecario</option>
                        <option value="Administrador" <?php echo (($edit_user['rol'] ?? '') === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <?php echo $edit_user ? 'Nueva Contraseña' : 'Contraseña'; ?>
                        <?php if ($edit_user): ?>
                            <small style="color: #64748b; font-weight: normal;">(dejar en blanco para no cambiar)</small>
                        <?php endif; ?>
                    </label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="password" id="formUserPwd" class="form-control" style="padding-right: 42px;" value="<?php echo htmlspecialchars($edit_user['plain_password'] ?? (!$edit_user ? '123456' : '')); ?>" <?php echo !$edit_user ? 'required' : ''; ?> placeholder="Ingresa la contraseña">
                        <button type="button" class="btn-pwd-eye" style="position: absolute; right: 8px; z-index: 2;" onclick="toggleInputType('formUserPwd', this)" title="Mostrar / Ocultar contraseña">
                            <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; max-width: 340px;">
                <button type="submit" name="save_user" class="btn btn-primary" style="flex: 1;">
                    <?php echo $edit_user ? 'Guardar Cambios' : 'Crear Usuario'; ?>
                </button>
                <?php if ($edit_user): ?>
                <a href="users.php" class="btn" style="background-color: #94a3b8; text-decoration: none; padding: 9px 16px;">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="data-pane">
        <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Contraseña</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr <?php if ($edit_user && $edit_user['id'] == $user['id']) echo 'style="background-color: #fff9db;"'; ?>>
                    <td><strong><?php echo htmlspecialchars($user['nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><span class="status-badge status-borrowed"><?php echo htmlspecialchars($user['rol']); ?></span></td>
                    <td style="white-space: nowrap;">
                        <?php if (!empty($user['plain_password'])): ?>
                            <span class="pwd-masked" id="pwd_val_<?php echo $user['id']; ?>">••••••••</span>
                            <button type="button" class="btn-pwd-eye" onclick="togglePasswordRow('pwd_val_<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['plain_password'], ENT_QUOTES); ?>', this)" title="Ver / Ocultar contraseña">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.82rem; font-style: italic;">[Cifrada en BD]</span>
                            <a href="users.php?edit=<?php echo $user['id']; ?>" style="font-size: 0.78rem; margin-left: 5px; color: var(--color-info);" title="Asignar una nueva contraseña visible">Asignar</a>
                        <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-edit">Editar</a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar a este usuario?');">Borrar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
// Función para mostrar / ocultar contraseña en las filas de la tabla
function togglePasswordRow(spanId, clearPass, btn) {
    const span = document.getElementById(spanId);
    if (!span) return;
    
    const isShown = span.getAttribute('data-shown') === 'true';
    if (isShown) {
        span.textContent = '••••••••';
        span.removeAttribute('data-shown');
        btn.style.color = '#64748b';
        btn.innerHTML = `
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        `;
    } else {
        span.textContent = clearPass;
        span.setAttribute('data-shown', 'true');
        btn.style.color = 'var(--color-primary)';
        btn.innerHTML = `
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        `;
    }
}

// Función para alternar tipo password/text en el formulario
function toggleInputType(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        btn.style.color = 'var(--color-primary)';
        btn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        `;
    } else {
        input.type = 'password';
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
