<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/backup_manager.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// Estadísticas de la BD para mostrar en la pestaña
$stats = [
    'libros' => (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'prestamos' => (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE accion = 'Prestamo'")->fetchColumn(),
    'usuarios' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'autores' => (int)$pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn(),
    'categorias' => (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
];

// Disparador pasivo: verifica si ya existe el respaldo mensual automático del mes actual
try {
    $monthlyAuto = runMonthlyAutoBackup($pdo, false);
    if ($monthlyAuto['created']) {
        $_SESSION['success_msg'] = "Copia mensual automática generada: " . $monthlyAuto['filename'] . ($monthlyAuto['deleted'] > 0 ? " (se eliminó la copia más antigua para respetar el tope de 10)." : "");
    }
} catch (Exception $eAuto) {}

// Acción 1: Crear copia de seguridad manual inmediata
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup_now'])) {
    $res = runMonthlyAutoBackup($pdo, true);
    if ($res['created']) {
        $_SESSION['success_msg'] = "Copia de seguridad generada con éxito (" . $res['filename'] . ")." . ($res['deleted'] > 0 ? " Se eliminó la copia más antigua para conservar el límite de 10." : "");
    } else {
        $_SESSION['error_msg'] = $res['message'];
    }
    header('Location: db_tools.php');
    exit;
}

// Acción 2: Eliminar copia de seguridad almacenada
if (isset($_GET['delete_backup'])) {
    $delFile = basename($_GET['delete_backup']);
    $delPath = initBackupDirectory() . '/' . $delFile;
    if (file_exists($delPath) && str_ends_with(strtolower($delFile), '.sql')) {
        @unlink($delPath);
        $_SESSION['success_msg'] = "Copia de seguridad '$delFile' eliminada correctamente.";
    } else {
        $_SESSION['error_msg'] = "El archivo especificado no existe o es inválido.";
    }
    header('Location: db_tools.php');
    exit;
}

// Acción 3: Restaurar desde copia almacenada en el servidor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_stored'])) {
    $confirm = $_POST['confirm_restore_stored'] ?? '0';
    if ($confirm !== '1') {
        $_SESSION['error_msg'] = "Debe marcar la casilla de confirmación para proceder con la restauración.";
        header('Location: db_tools.php');
        exit;
    }
    $resFile = basename($_POST['backup_filename'] ?? '');
    $resPath = initBackupDirectory() . '/' . $resFile;
    if (!file_exists($resPath) || !str_ends_with(strtolower($resFile), '.sql')) {
        $_SESSION['error_msg'] = "El archivo de respaldo seleccionado no existe en el servidor.";
        header('Location: db_tools.php');
        exit;
    }
    try {
        $executed = executeSqlFileStream($pdo, $resPath);
        $_SESSION['success_msg'] = "Base de datos restaurada correctamente desde '$resFile'. Se procesaron " . number_format($executed) . " sentencias SQL con éxito.";
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error durante la restauración: " . $e->getMessage();
    }
    header('Location: db_tools.php');
    exit;
}

// Detección de carga que supera post_max_size del servidor (cuando $_POST llega vacío pero se enviaron datos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $maxPost = ini_get('post_max_size');
    $contentLenMB = round((int)$_SERVER['CONTENT_LENGTH'] / 1048576, 2);
    $_SESSION['error_msg'] = "El archivo enviado ({$contentLenMB} MB) excede el tamaño máximo permitido por el servidor web o PHP (post_max_size actual: {$maxPost}). Configure 'client_max_body_size 256M;' en Nginx y 'post_max_size = 256M' en php.ini.";
    header('Location: db_tools.php');
    exit;
}

// Acción 4: Procesar importación de archivo .sql subido desde el navegador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_db'])) {
    $confirm = $_POST['confirm_restore'] ?? '0';

    if ($confirm !== '1') {
        $_SESSION['error_msg'] = "Debe marcar la casilla de confirmación para proceder con la restauración.";
        header('Location: db_tools.php');
        exit;
    }

    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                $maxUpload = ini_get('upload_max_filesize');
                $_SESSION['error_msg'] = "El archivo supera el tamaño máximo permitido por PHP (upload_max_filesize actual: {$maxUpload}). Aumente este valor en php.ini a 256M.";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $_SESSION['error_msg'] = "El archivo supera el tamaño máximo permitido por el formulario web.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $_SESSION['error_msg'] = "La carga del archivo se interrumpió y solo se subió parcialmente.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $_SESSION['error_msg'] = "Por favor, seleccione un archivo SQL (.sql) antes de hacer clic en Importar.";
                break;
            default:
                $_SESSION['error_msg'] = "Error al recibir el archivo en el servidor (Código de error PHP: $errorCode).";
                break;
        }
        header('Location: db_tools.php');
        exit;
    }

    $fileTmp = $_FILES['sql_file']['tmp_name'];
    $fileName = $_FILES['sql_file']['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'sql') {
        $_SESSION['error_msg'] = "Formato de archivo inválido. Solo se admiten archivos con extensión .sql.";
        header('Location: db_tools.php');
        exit;
    }

    try {
        $executed = executeSqlFileStream($pdo, $fileTmp);
        $_SESSION['success_msg'] = "Base de datos restaurada correctamente. Se procesaron y ejecutaron " . number_format($executed) . " sentencias SQL con éxito.";
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error durante la restauración: " . $e->getMessage();
    }

    header('Location: db_tools.php');
    exit;
}

$backupDir = initBackupDirectory();
$isBackupDirWritable = is_writable($backupDir);
$storedBackups = getStoredBackups();
$totalStored = count($storedBackups);
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Importar Datos y Copia de Seguridad</h2>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-warning">
        <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
    </div>
<?php endif; ?>

<!-- Resumen actual del sistema -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div style="background: white; padding: 18px; border-radius: 8px; border-left: 4px solid var(--color-primary); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="font-size: 1.8rem; font-weight: bold; color: var(--color-primary);"><?php echo number_format($stats['libros']); ?></div>
        <div style="font-size: 0.85rem; color: #666;">Libros en catálogo</div>
    </div>
    <div style="background: white; padding: 18px; border-radius: 8px; border-left: 4px solid var(--color-info); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="font-size: 1.8rem; font-weight: bold; color: var(--color-info);"><?php echo number_format($stats['prestamos']); ?></div>
        <div style="font-size: 0.85rem; color: #666;">Préstamos históricos</div>
    </div>
    <div style="background: white; padding: 18px; border-radius: 8px; border-left: 4px solid var(--color-success); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="font-size: 1.8rem; font-weight: bold; color: var(--color-success);"><?php echo number_format($stats['autores']); ?></div>
        <div style="font-size: 0.85rem; color: #666;">Autores registrados</div>
    </div>
    <div style="background: white; padding: 18px; border-radius: 8px; border-left: 4px solid var(--color-warning); box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="font-size: 1.8rem; font-weight: bold; color: var(--color-warning);"><?php echo number_format($stats['usuarios']); ?></div>
        <div style="font-size: 0.85rem; color: #666;">Usuarios del sistema</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
    
    <!-- TARJETA 1: EXPORTAR BASE DE DATOS -->
    <div class="form-container" style="max-width: 100%;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <div>
                <h3 style="margin: 0; color: var(--color-primary);">Exportar Base de Datos</h3>
                <small style="color: #666;">Descarga de volcado SQL completo</small>
            </div>
        </div>

        <p style="color: #555; line-height: 1.6; margin-bottom: 20px;">
            Esta herramienta genera una copia exacta de todas las tablas, esquemas y registros de la biblioteca (libros, autores, categorías, editoriales, préstamos y usuarios).
        </p>

        <div style="background: #f8f9fa; border: 1px solid var(--color-border); border-radius: 6px; padding: 15px; margin-bottom: 20px; font-size: 0.9rem;">
            <strong>Detalles del Respaldo:</strong>
            <ul style="margin-top: 8px; margin-left: 20px; color: #555; line-height: 1.5;">
                <li>Formato: Archivo SQL puro estándar (UTF-8)</li>
                <li>Incluye: Estructura <code>CREATE TABLE</code> y datos <code>INSERT</code></li>
                <li>Compatible con: MariaDB 10.4+, MySQL 8.0+, phpMyAdmin</li>
            </ul>
        </div>

        <a href="../api/export_db.php" class="btn btn-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 12px; font-size: 1.05rem; text-decoration: none;">
            Descargar Copia de Seguridad (.sql)
        </a>
    </div>

    <!-- TARJETA 2: IMPORTAR BASE DE DATOS -->
    <div class="form-container" style="max-width: 100%;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <div>
                <h3 style="margin: 0; color: var(--color-accent);">Importar Datos / Restaurar Base de Datos</h3>
                <small style="color: #666;">Cargar archivo SQL para poblar o actualizar la base de datos</small>
            </div>
        </div>

        <div style="background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px 15px; border-radius: 6px; font-size: 0.88rem; margin-bottom: 18px; line-height: 1.5;">
            <strong>ADVERTENCIA:</strong> Esta acción ejecutará las instrucciones del archivo SQL seleccionado. Podría sobreescribir o modificar tablas existentes. Se recomienda realizar una exportación previa antes de restaurar.
        </div>

        <form method="POST" enctype="multipart/form-data" id="formImportDb">
            <input type="hidden" name="import_db" value="1">
            <div class="form-group">
                <label for="sqlFile">Seleccionar Archivo SQL (.sql)</label>
                <input type="file" name="sql_file" id="sqlFile" class="form-control" accept=".sql" required style="padding: 8px;">
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: normal; cursor: pointer; color: #333;">
                    <input type="checkbox" name="confirm_restore" id="chkConfirmRestore" value="1" required style="width: 18px; height: 18px; cursor: pointer;">
                    <span>Entiendo los riesgos y confirmo que deseo restaurar la base de datos.</span>
                </label>
            </div>

            <button type="submit" name="import_db" id="btnSubmitImport" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.05rem; background-color: var(--color-accent); margin-top: 10px;">
                Importar Datos / Restaurar Base de Datos
            </button>
        </form>
    </div>

</div>

<!-- SECCIÓN 3: RESPALDOS AUTOMÁTICOS EN EL SERVIDOR (RETENCIÓN MÁXIMA 10 COPIAS) -->
<div class="form-container" style="max-width: 100%; margin-top: 25px;">
    <?php if (!$isBackupDirWritable): ?>
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 6px; font-size: 0.88rem; margin-bottom: 20px; line-height: 1.5;">
        <strong>Atención (Permisos en Servidor):</strong> La carpeta de almacenamiento de respaldos (<code>backups/</code>) no cuenta con permisos de escritura para el servidor web.<br>
        Para habilitar la creación y almacenamiento de copias, ejecuta este comando en la terminal de tu servidor Linux:<br>
        <code style="display:inline-block; margin-top:6px; background:#450a0a; color:#fecaca; padding:4px 8px; border-radius:4px; font-weight:bold;">sudo chmod -R 777 /var/www/Gestion-de-biblioteca/backups</code>
    </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; border-bottom: 1px solid var(--color-border); padding-bottom: 15px;">
        <div>
            <h3 style="margin: 0; color: var(--color-primary); display: flex; align-items: center; gap: 8px;">
                Copias de Seguridad Automáticas en el Servidor
            </h3>
            <small style="color: #666;">
                Frecuencia: <strong>Cada mes</strong> &bull; Política de retención: <strong>Máximo 10 copias</strong> (al llegar a 11, se borra automáticamente la más antigua).
            </small>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="background: <?php echo $totalStored >= 10 ? '#dc3545' : 'var(--color-primary)'; ?>; color: white; padding: 5px 12px; border-radius: 12px; font-size: 0.82rem; font-weight: 600;">
                <?php echo $totalStored; ?> / 10 Copias Almacenadas
            </span>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Desea generar una copia de seguridad en el servidor ahora mismo?');">
                <input type="hidden" name="create_backup_now" value="1">
                <button type="submit" name="create_backup_now" class="btn btn-primary" style="padding: 8px 14px; font-size: 0.9rem;">
                     Crear Respaldo Ahora
                </button>
            </form>
        </div>
    </div>

    <div style="background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; padding: 12px 15px; border-radius: 6px; font-size: 0.88rem; margin-bottom: 20px; line-height: 1.5;">
        <strong>Automatización Mensual:</strong> El sistema genera automáticamente una copia de respaldo completa el 1º de cada mes. Cuando la carpeta alcanza las <strong>10 copias</strong>, el sistema elimina automáticamente la más antigua para mantener siempre disponible el historial de los últimos 10 meses sin saturar el espacio en disco.
    </div>

    <?php if (empty($storedBackups)): ?>
        <div style="text-align: center; padding: 35px 15px; color: #777; background: #fafafa; border-radius: 6px; border: 1px dashed var(--color-border);">
            <p style="margin: 0 0 5px 0; font-size: 1rem; font-weight: 500; color: #475569;">No hay copias de seguridad almacenadas todavía en el servidor.</p>
            <small style="color: #94a3b8;">Se creará una copia automáticamente al inicio de cada mes, o puedes hacer clic en <strong>"Crear Respaldo Ahora"</strong>.</small>
        </div>
    <?php else: ?>
        <div class="table-responsive table-scroll-5">
            <table class="table" style="width: 100%; font-size: 0.92rem;">
                <thead>
                    <tr>
                        <th style="width: 32%;">Archivo SQL</th>
                        <th style="width: 16%;">Tipo</th>
                        <th style="width: 20%;">Fecha de Creación</th>
                        <th style="width: 12%;">Tamaño</th>
                        <th style="width: 20%; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($storedBackups as $bk): ?>
                        <tr>
                            <td>
                                <strong style="color: #1e293b;"><?php echo htmlspecialchars($bk['filename']); ?></strong>
                            </td>
                            <td>
                                <?php if ($bk['is_auto']): ?>
                                    <span class="status-badge" style="background: #2563eb; color: white; font-size: 0.75rem; padding: 2px 8px;">Mensual Automática</span>
                                <?php else: ?>
                                    <span class="status-badge" style="background: #64748b; color: white; font-size: 0.75rem; padding: 2px 8px;">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #475569;"><?php echo $bk['date_formatted']; ?></td>
                            <td style="font-family: monospace; font-weight: 600; color: #0f172a;"><?php echo $bk['size_formatted']; ?></td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="../api/download_backup.php?file=<?php echo urlencode($bk['filename']); ?>" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem; text-decoration: none; background: #0288d1;" title="Descargar archivo a tu computadora">
                                         Bajar
                                    </a>

                                    <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('¿Está COMPLETAMENTE SEGURO de restaurar la base de datos a esta copia (<?php echo htmlspecialchars($bk['filename']); ?>)? Se sobreescribirán los datos actuales.');">
                                        <input type="hidden" name="restore_stored" value="1">
                                        <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($bk['filename']); ?>">
                                        <input type="hidden" name="confirm_restore_stored" value="1">
                                        <button type="submit" name="restore_stored" class="btn btn-edit" style="width: auto !important; min-width: 0 !important; padding: 4px 10px; font-size: 0.8rem;" title="Restaurar esta versión en la base de datos">
                                             Restaurar
                                        </button>
                                    </form>

                                    <a href="db_tools.php?delete_backup=<?php echo urlencode($bk['filename']); ?>" class="btn btn-delete" style="width: auto !important; min-width: 0 !important; padding: 4px 10px; font-size: 0.8rem; text-decoration: none;" onclick="return confirm('¿Confirma que desea eliminar esta copia de seguridad del servidor?');" title="Eliminar este archivo">
                                         Borrar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal / Overlay de Carga durante la importación o restauración -->
<div id="importLoadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; border-radius: 8px; padding: 32px 40px; max-width: 480px; width: 90%; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);">
        <div class="db-spinner" style="width: 50px; height: 50px; border: 5px solid #e2e8f0; border-top-color: #0288d1; border-radius: 50%; margin: 0 auto 20px auto; animation: dbToolsSpin 0.85s linear infinite;"></div>
        <h3 id="importLoadingTitle" style="margin: 0 0 10px 0; color: #0f172a; font-size: 1.25rem;">Procesando Base de Datos...</h3>
        <p id="importLoadingMsg" style="margin: 0 0 16px 0; color: #475569; font-size: 0.92rem; line-height: 1.5;">
            Por favor, espere unos momentos mientras se leen y ejecutan las sentencias SQL.
        </p>
        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 10px 14px; border-radius: 6px; font-size: 0.82rem; color: #64748b;">
            No cierre, refresque ni navegue fuera de esta página hasta que la operación termine.
        </div>
    </div>
</div>

<style>
@keyframes dbToolsSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.getElementById('importLoadingOverlay');
    var titleEl = document.getElementById('importLoadingTitle');
    var msgEl = document.getElementById('importLoadingMsg');
    var formImport = document.getElementById('formImportDb');
    var btnSubmitImport = document.getElementById('btnSubmitImport');

    function showLoading(title, message) {
        if (titleEl && title) titleEl.textContent = title;
        if (msgEl && message) msgEl.textContent = message;
        if (overlay) overlay.style.display = 'flex';
    }

    if (formImport) {
        formImport.onsubmit = function(e) {
            var fileInput = document.getElementById('sqlFile');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Por favor, selecciona un archivo SQL (.sql) antes de importar.');
                return false;
            }

            var confirmed = confirm('¿Está COMPLETAMENTE SEGURO de restaurar la base de datos?\n\nEsta acción reemplazará las tablas actuales con las del archivo seleccionado.');
            if (!confirmed) {
                return false;
            }

            showLoading('Importando y Restaurando Base de Datos...', 'El sistema está cargando el archivo SQL y ejecutando las sentencias en la base de datos. Por favor, espere...');

            if (btnSubmitImport) {
                btnSubmitImport.textContent = 'Importando... Por favor espere';
                btnSubmitImport.style.opacity = '0.7';
                setTimeout(function() {
                    try { btnSubmitImport.disabled = true; } catch (err) {}
                }, 50);
            }
            return true;
        };
    }

    // Formularios de restauración de copias almacenadas y creación manual
    var forms = document.getElementsByTagName('form');
    for (var i = 0; i < forms.length; i++) {
        (function(f) {
            var btnRestore = f.querySelector('button[name="restore_stored"]');
            if (btnRestore) {
                var prevOnsubmit = f.onsubmit;
                f.onsubmit = function(e) {
                    var ok = true;
                    if (prevOnsubmit) {
                        ok = prevOnsubmit.call(f, e);
                    }
                    if (ok !== false) {
                        showLoading('Restaurando Copia de Seguridad...', 'Se está restaurando la base de datos al punto seleccionado. Por favor, espere...');
                        btnRestore.textContent = 'Restaurando...';
                        setTimeout(function() {
                            try { btnRestore.disabled = true; } catch (err) {}
                        }, 50);
                    }
                    return ok;
                };
            }

            var btnCreateNow = f.querySelector('button[name="create_backup_now"]');
            if (btnCreateNow) {
                var prevOnsubmitNow = f.onsubmit;
                f.onsubmit = function(e) {
                    var ok = true;
                    if (prevOnsubmitNow) {
                        ok = prevOnsubmitNow.call(f, e);
                    }
                    if (ok !== false) {
                        showLoading('Generando Copia de Seguridad...', 'El servidor está generando el volcado SQL completo de la base de datos. Por favor, espere...');
                        btnCreateNow.textContent = 'Generando...';
                        setTimeout(function() {
                            try { btnCreateNow.disabled = true; } catch (err) {}
                        }, 50);
                    }
                    return ok;
                };
            }
        })(forms[i]);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
