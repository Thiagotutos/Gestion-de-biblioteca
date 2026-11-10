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
// 1. Verificación suave y auto-reparación de contraseñas visibles
// -------------------------------------------------------------
try {
    $pdo->query("SELECT plain_password FROM users LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NULL AFTER password");
    } catch (Exception $e2) {}
}

try {
    // 1.1 Asegurar que las cuentas por defecto tengan su clave visible conocida
    $pdo->exec("UPDATE users SET plain_password = 'admin123' WHERE (plain_password IS NULL OR plain_password = '') AND email IN ('admin@biblioteca.com', 'bibliotecario@biblioteca.com', 'lector@biblioteca.com')");

    // 1.2 Asegurar que ningún otro usuario quede con plain_password NULL
    $stmt_null_users = $pdo->query("SELECT id, email, password FROM users WHERE plain_password IS NULL OR plain_password = ''");
    $nullUsers = $stmt_null_users->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($nullUsers)) {
        $updPlain = $pdo->prepare("UPDATE users SET plain_password = ? WHERE id = ?");
        $updBoth = $pdo->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?");
        $candidates = ['admin123', '123456', 'biblioteca', 'biblioteca2026', 'admin'];
        foreach ($nullUsers as $nu) {
            $matched = null;
            foreach ($candidates as $cand) {
                if (password_verify($cand, $nu['password'])) {
                    $matched = $cand;
                    break;
                }
            }
            if ($matched !== null) {
                $updPlain->execute([$matched, $nu['id']]);
            } else {
                // Clave provisoria legible y sincronizada con el hash bcrypt
                $fallbackPass = ($nu['email'] === 'admin@biblioteca.com' || str_contains($nu['email'], 'admin')) ? 'admin123' : '123456';
                $fallbackHash = password_hash($fallbackPass, PASSWORD_DEFAULT);
                $updBoth->execute([$fallbackHash, $fallbackPass, $nu['id']]);
            }
        }
    }
} catch (Exception $eFix) {}

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

        // 1. Validación de nombre: solo letras, espacios, acentos (3 a 60 caracteres)
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 60) {
            $_SESSION['error_msg'] = "El nombre debe tener entre 3 y 60 caracteres.";
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }
        if (!preg_match('/^[\p{L}\s\.\'-]{3,60}$/u', $nombre)) {
            $_SESSION['error_msg'] = "El nombre solo puede contener letras y espacios (no se permiten caracteres especiales como @, #, $, números, etc.).";
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }

        // 2. Validación estricta de correo electrónico (entre 6 y 80 caracteres, dominio y TLD válido)
        if (strlen($email) < 6 || strlen($email) > 80 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,10}$/', $email)) {
            $_SESSION['error_msg'] = "El correo electrónico '$email' no es válido. Debe tener formato completo con dominio (ejemplo: usuario@gmail.com, máx. 80 caracteres).";
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }

        // 2.1 Validación de dominio y detección de errores tipográficos (Gmail, Hotmail, Outlook, Yahoo)
        $email_parts = explode('@', strtolower($email));
        $email_user = $email_parts[0] ?? '';
        $email_domain = $email_parts[1] ?? '';

        $typos_dominios = [
            // Typos de Gmail
            'gma.com'       => 'gmail.com',
            'gmai.com'      => 'gmail.com',
            'gamil.com'     => 'gmail.com',
            'gmial.com'     => 'gmail.com',
            'gmaill.com'    => 'gmail.com',
            'gmal.com'      => 'gmail.com',
            'gmeil.com'     => 'gmail.com',
            'gemail.com'    => 'gmail.com',
            'gmail.con'     => 'gmail.com',
            'gmail.co'      => 'gmail.com',
            'gmai.co'       => 'gmail.com',
            'gmil.com'      => 'gmail.com',
            'gma.co'        => 'gmail.com',
            'gimail.com'    => 'gmail.com',

            // Typos de Hotmail
            'hotm.com'      => 'hotmail.com',
            'hotmai.com'    => 'hotmail.com',
            'hotmal.com'    => 'hotmail.com',
            'hotmaill.com'  => 'hotmail.com',
            'hotmial.com'   => 'hotmail.com',
            'hotmeil.com'   => 'hotmail.com',
            'hotmail.con'   => 'hotmail.com',
            'hotmail.co'    => 'hotmail.com',
            'jotmail.com'   => 'hotmail.com',
            'jotm.com'      => 'hotmail.com',
            'hormail.com'   => 'hotmail.com',

            // Typos de Outlook
            'outlok.com'    => 'outlook.com',
            'outloo.com'    => 'outlook.com',
            'outlock.com'   => 'outlook.com',
            'outluk.com'    => 'outlook.com',
            'outlook.con'   => 'outlook.com',
            'ootlook.com'   => 'outlook.com',
            'outloock.com'  => 'outlook.com',

            // Typos de Yahoo
            'yaho.com'      => 'yahoo.com',
            'yahooo.com'    => 'yahoo.com',
            'yahu.com'      => 'yahoo.com',
            'yahoo.con'     => 'yahoo.com',
            'yaho.com.ar'   => 'yahoo.com.ar',
            'yahooo.com.ar' => 'yahoo.com.ar',
        ];

        if (isset($typos_dominios[$email_domain])) {
            $sugerido = $email_user . '@' . $typos_dominios[$email_domain];
            $_SESSION['error_msg'] = "El correo contiene un dominio erróneo ('@$email_domain'). ¿Quisiste escribir '$sugerido'? Por favor corrígelo para continuar.";
            header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
            exit;
        }

        // Dominios de proveedores de correo válidos y reconocidos
        $dominios_validos = [
            'gmail.com', 'googlemail.com',
            'hotmail.com', 'hotmail.es', 'hotmail.com.ar',
            'outlook.com', 'outlook.es', 'outlook.com.ar',
            'live.com', 'msn.com',
            'yahoo.com', 'yahoo.com.ar', 'yahoo.es',
            'icloud.com', 'me.com', 'mac.com',
            'proton.me', 'protonmail.com',
            'zoho.com', 'aol.com',
            'correo.com', 'biblioteca.com'
        ];

        $es_institucional = (bool) preg_match('/\.(edu|gob|gov|org)(\.[a-z]{2})?$/i', $email_domain) || (bool) preg_match('/\.edu\.ar$/i', $email_domain);

        if (!in_array($email_domain, $dominios_validos, true) && !$es_institucional) {
            // Verificar si el dominio posee servidores de correo reales en Internet (MX o A)
            if (function_exists('checkdnsrr')) {
                $has_mx = @checkdnsrr($email_domain, 'MX');
                $has_a = @checkdnsrr($email_domain, 'A');
                if (!$has_mx && !$has_a) {
                    $_SESSION['error_msg'] = "El dominio '@$email_domain' no es válido ni posee servidores de correo activos. Utiliza un correo real (ej: Gmail, Hotmail, Outlook o tu dominio escolar).";
                    header('Location: users.php' . ($user_id > 0 ? "?edit=$user_id" : ''));
                    exit;
                }
            }
        }

        // 3. Validación de contraseña (mínimo 6 caracteres, máximo 30)
        if ($user_id === 0) {
            if ($raw_pass === '') {
                $raw_pass = '123456'; // Contraseña provisional por defecto
            }
            if (strlen($raw_pass) < 6 || strlen($raw_pass) > 30) {
                $_SESSION['error_msg'] = "La contraseña debe tener entre 6 y 30 caracteres.";
                header('Location: users.php');
                exit;
            }
        } else {
            if ($raw_pass !== '' && (strlen($raw_pass) < 6 || strlen($raw_pass) > 30)) {
                $_SESSION['error_msg'] = "La nueva contraseña debe tener entre 6 y 30 caracteres.";
                header('Location: users.php?edit=' . $user_id);
                exit;
            }
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
        <form method="POST" id="formUser">
            <?php if ($edit_user): ?>
            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
            <?php endif; ?>

            <div class="form-grid-fields">
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="nombre" id="userNombre" class="form-control" value="<?php echo htmlspecialchars($edit_user['nombre'] ?? ''); ?>" required placeholder="Ej: Juan Pérez" minlength="3" maxlength="60" pattern="^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'-]{3,60}$" title="Solo letras y espacios (entre 3 y 60 caracteres, sin números ni símbolos)">
                    <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 3px;">Solo letras y espacios (entre 3 y 60 caracteres).</small>
                </div>
                <div class="form-group">
                    <label>Correo Electrónico *</label>
                    <input type="email" name="email" id="userEmail" class="form-control" value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" required placeholder="correo@ejemplo.com" maxlength="80" pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,10}$" title="Ingrese un correo electrónico válido con dominio completo (ej: usuario@gmail.com, máx. 80 caracteres)">
                    <div id="emailSuggestionBox" style="display: none; margin-top: 5px; padding: 6px 10px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 4px; font-size: 0.82rem; color: #b78103; align-items: center; justify-content: space-between;">
                        <span>¿Quisiste escribir <strong><span id="suggestedEmailTxt"></span></strong>?</span>
                        <button type="button" id="btnApplyEmailSuggestion" style="margin-left: 8px; padding: 3px 8px; font-size: 0.78rem; background: #0284c7; color: #fff; border: none; border-radius: 3px; cursor: pointer;">Corregir</button>
                    </div>
                    <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 3px;">Ej: Gmail, Hotmail, Outlook o correo institucional escolar.</small>
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
                        <input type="password" name="password" id="formUserPwd" class="form-control" style="padding-right: 42px;" value="<?php echo htmlspecialchars($edit_user['plain_password'] ?? (!$edit_user ? '123456' : '')); ?>" <?php echo !$edit_user ? 'required' : ''; ?> minlength="6" maxlength="30" placeholder="Ingresa la contraseña">
                        <button type="button" class="btn-pwd-eye" style="position: absolute; right: 8px; z-index: 2;" onclick="toggleInputType('formUserPwd', this)" title="Mostrar / Ocultar contraseña">
                            <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 3px;">Entre 6 y 30 caracteres.</small>
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
        <div class="table-responsive table-scroll-5">
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
                <?php $pass_show = !empty($user['plain_password']) ? $user['plain_password'] : '123456'; ?>
                <tr <?php if ($edit_user && $edit_user['id'] == $user['id']) echo 'style="background-color: #fff9db;"'; ?>>
                    <td><strong><?php echo htmlspecialchars($user['nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><span class="status-badge status-borrowed"><?php echo htmlspecialchars($user['rol']); ?></span></td>
                    <td style="white-space: nowrap;">
                        <span class="pwd-masked" id="pwd_val_<?php echo $user['id']; ?>" data-password="<?php echo htmlspecialchars($pass_show, ENT_QUOTES, 'UTF-8'); ?>">••••••••</span>
                        <button type="button" class="btn-pwd-eye" onclick="togglePasswordRow('pwd_val_<?php echo $user['id']; ?>', this)" title="Ver / Ocultar contraseña">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
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
function togglePasswordRow(spanId, btn) {
    var span = document.getElementById(spanId);
    if (!span) return;
    
    var clearPass = span.getAttribute('data-password') || '';
    var isShown = span.getAttribute('data-shown') === 'true';
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

// Diccionario de errores tipográficos en proveedores comunes de correo
const emailTypos = {
    // Typos de Gmail
    'gma.com': 'gmail.com',
    'gmai.com': 'gmail.com',
    'gamil.com': 'gmail.com',
    'gmial.com': 'gmail.com',
    'gmaill.com': 'gmail.com',
    'gmal.com': 'gmail.com',
    'gmeil.com': 'gmail.com',
    'gemail.com': 'gmail.com',
    'gmail.con': 'gmail.com',
    'gmail.co': 'gmail.com',
    'gmai.co': 'gmail.com',
    'gmil.com': 'gmail.com',
    'gma.co': 'gmail.com',
    'gimail.com': 'gmail.com',

    // Typos de Hotmail
    'hotm.com': 'hotmail.com',
    'hotmai.com': 'hotmail.com',
    'hotmal.com': 'hotmail.com',
    'hotmaill.com': 'hotmail.com',
    'hotmial.com': 'hotmail.com',
    'hotmeil.com': 'hotmail.com',
    'hotmail.con': 'hotmail.com',
    'hotmail.co': 'hotmail.com',
    'jotmail.com': 'hotmail.com',
    'jotm.com': 'hotmail.com',
    'hormail.com': 'hotmail.com',

    // Typos de Outlook
    'outlok.com': 'outlook.com',
    'outloo.com': 'outlook.com',
    'outlock.com': 'outlook.com',
    'outluk.com': 'outlook.com',
    'outlook.con': 'outlook.com',
    'ootlook.com': 'outlook.com',
    'outloock.com': 'outlook.com',

    // Typos de Yahoo
    'yaho.com': 'yahoo.com',
    'yahooo.com': 'yahoo.com',
    'yahu.com': 'yahoo.com',
    'yahoo.con': 'yahoo.com',
    'yaho.com.ar': 'yahoo.com.ar',
    'yahooo.com.ar': 'yahoo.com.ar'
};

// Validación interactiva y estricta en el formulario
document.addEventListener('DOMContentLoaded', function() {
    const formUser = document.getElementById('formUser');
    const emailInput = document.getElementById('userEmail');
    const suggestionBox = document.getElementById('emailSuggestionBox');
    const suggestedTxt = document.getElementById('suggestedEmailTxt');
    const btnApply = document.getElementById('btnApplyEmailSuggestion');

    function checkEmailTypo() {
        if (!emailInput || !suggestionBox) return;
        const val = emailInput.value.trim().toLowerCase();
        if (val.includes('@')) {
            const parts = val.split('@');
            const userPart = parts[0];
            const domainPart = parts[1] || '';
            if (emailTypos[domainPart]) {
                const suggested = userPart + '@' + emailTypos[domainPart];
                suggestedTxt.textContent = suggested;
                suggestionBox.style.display = 'flex';
                return { hasTypo: true, suggested: suggested, domain: domainPart };
            }
        }
        suggestionBox.style.display = 'none';
        return { hasTypo: false };
    }

    if (emailInput) {
        emailInput.addEventListener('input', checkEmailTypo);
        emailInput.addEventListener('blur', checkEmailTypo);
    }

    if (btnApply) {
        btnApply.addEventListener('click', function() {
            if (suggestedTxt && emailInput) {
                emailInput.value = suggestedTxt.textContent;
                suggestionBox.style.display = 'none';
                emailInput.focus();
            }
        });
    }

    if (formUser) {
        formUser.addEventListener('submit', function(e) {
            const nombreInput = document.getElementById('userNombre');
            const pwdInput = document.getElementById('formUserPwd');

            if (nombreInput) {
                const nomVal = nombreInput.value.trim();
                const nombreRegex = /^[\p{L}\s\.\'-]{3,60}$/u;
                if (!nombreRegex.test(nomVal)) {
                    e.preventDefault();
                    alert('El nombre solo debe contener letras y espacios (entre 3 y 60 caracteres). No se permiten números ni caracteres especiales como @, #, $, etc.');
                    nombreInput.focus();
                    return false;
                }
            }

            if (emailInput) {
                const emailVal = emailInput.value.trim().toLowerCase();
                const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,10}$/;
                
                if (emailVal.length < 6 || emailVal.length > 80 || !emailRegex.test(emailVal)) {
                    e.preventDefault();
                    alert('Ingrese un correo electrónico válido con dominio completo (ej: usuario@gmail.com, máx. 80 caracteres).');
                    emailInput.focus();
                    return false;
                }

                // Verificar si contiene un typo de dominio detectado
                const typoCheck = checkEmailTypo();
                if (typoCheck && typoCheck.hasTypo) {
                    e.preventDefault();
                    alert('El correo electrónico ingresado ("' + emailVal + '") contiene un dominio no válido ("@' + typoCheck.domain + '").\n\n¿Quisiste escribir "' + typoCheck.suggested + '"? Por favor corrígelo antes de continuar.');
                    emailInput.focus();
                    return false;
                }
            }

            if (pwdInput) {
                const pwdVal = pwdInput.value.trim();
                if (pwdInput.required || pwdVal !== '') {
                    if (pwdVal.length < 6 || pwdVal.length > 30) {
                        e.preventDefault();
                        alert('La contraseña debe tener entre 6 y 30 caracteres.');
                        pwdInput.focus();
                        return false;
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
