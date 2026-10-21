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

// Estadísticas de la BD para mostrar en la pestaña
$stats = [
    'libros' => (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'prestamos' => (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE accion = 'Prestamo'")->fetchColumn(),
    'usuarios' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'autores' => (int)$pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn(),
    'categorias' => (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
];

// Procesar importación de archivo .sql
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_db'])) {
    $confirm = $_POST['confirm_restore'] ?? '0';

    if ($confirm !== '1') {
        $_SESSION['error_msg'] = "Debe marcar la casilla de confirmación para proceder con la restauración.";
        header('Location: db_tools.php');
        exit;
    }

    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_msg'] = "Por favor, seleccione un archivo SQL válido para importar.";
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

    // Configurar tiempo y memoria
    set_time_limit(300);
    ini_set('memory_limit', '256M');

    $sqlContent = file_get_contents($fileTmp);
    if ($sqlContent === false || strlen(trim($sqlContent)) === 0) {
        $_SESSION['error_msg'] = "El archivo SQL seleccionado está vacío o no se pudo leer.";
        header('Location: db_tools.php');
        exit;
    }

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        // Parser seguro de sentencias SQL respetando comillas y comentarios
        $queries = [];
        $currentQuery = '';
        $inString = false;
        $stringChar = '';
        $length = strlen($sqlContent);

        for ($i = 0; $i < $length; $i++) {
            $char = $sqlContent[$i];

            // Detectar comentarios de una línea (-- o #) fuera de cadenas de texto
            if (!$inString && ($char === '#' || ($char === '-' && $i + 1 < $length && $sqlContent[$i + 1] === '-'))) {
                // Saltar hasta el fin de línea
                while ($i < $length && $sqlContent[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Detectar inicio/fin de cadenas de texto
            if (($char === "'" || $char === '"' || $char === '`') && ($i === 0 || $sqlContent[$i - 1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($stringChar === $char) {
                    $inString = false;
                }
            }

            if ($char === ';' && !$inString) {
                $trimmed = trim($currentQuery);
                if (!empty($trimmed)) {
                    $queries[] = $trimmed;
                }
                $currentQuery = '';
            } else {
                $currentQuery .= $char;
            }
        }

        $trimmed = trim($currentQuery);
        if (!empty($trimmed)) {
            $queries[] = $trimmed;
        }

        $executed = 0;
        foreach ($queries as $query) {
            $pdo->exec($query);
            $executed++;
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $_SESSION['success_msg'] = "Base de datos restaurada correctamente. Se ejecutaron " . number_format($executed) . " sentencias SQL con éxito.";
    } catch (Exception $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $_SESSION['error_msg'] = "Error durante la restauración: " . $e->getMessage();
    }

    header('Location: db_tools.php');
    exit;
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Copia de Seguridad y Restauración de Base de Datos</h2>
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

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 25px;">
    
    <!-- TARJETA 1: EXPORTAR BASE DE DATOS -->
    <div class="form-container" style="max-width: 100%;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <div style="font-size: 1.8rem;">📥</div>
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
            <span>📥</span> Descargar Copia de Seguridad (.sql)
        </a>
    </div>

    <!-- TARJETA 2: IMPORTAR BASE DE DATOS -->
    <div class="form-container" style="max-width: 100%;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <div style="font-size: 1.8rem;">📤</div>
            <div>
                <h3 style="margin: 0; color: var(--color-accent);">Restaurar Base de Datos</h3>
                <small style="color: #666;">Importar archivo SQL existente</small>
            </div>
        </div>

        <div style="background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px 15px; border-radius: 6px; font-size: 0.88rem; margin-bottom: 18px; line-height: 1.5;">
            ⚠️ <strong>ADVERTENCIA:</strong> Esta acción ejecutará las instrucciones del archivo SQL seleccionado. Podría sobreescribir o modificar tablas existentes. Se recomienda realizar una exportación previa antes de restaurar.
        </div>

        <form method="POST" enctype="multipart/form-data" id="formImportDb" onsubmit="return confirm('¿Está COMPLETAMENTE SEGURO de restaurar la base de datos? Se reemplazarán los datos actuales con los del archivo seleccionado.');">
            <div class="form-group">
                <label for="sqlFile">Seleccionar Archivo SQL (.sql)</label>
                <input type="file" name="sql_file" id="sqlFile" class="form-control" accept=".sql" required style="padding: 8px;">
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: normal; cursor: pointer; color: #333;">
                    <input type="checkbox" name="confirm_restore" value="1" required style="width: 18px; height: 18px; cursor: pointer;">
                    <span>Entiendo los riesgos y confirmo que deseo restaurar la base de datos.</span>
                </label>
            </div>

            <button type="submit" name="import_db" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.05rem; background-color: var(--color-accent); margin-top: 10px;">
                📤 Restaurar Base de Datos
            </button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
