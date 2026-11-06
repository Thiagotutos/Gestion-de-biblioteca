 <?php
/**
 * includes/backup_manager.php
 * Gestor centralizado de Copias de Seguridad (Exportación, Restauración, Rotación y Automatización Mensual).
 */

if (!defined('BACKUP_DIR')) {
    define('BACKUP_DIR', __DIR__ . '/../backups');
}

/**
 * Inicializa el directorio de respaldos asegurando permisos y archivo .htaccess de protección
 */
function initBackupDirectory(): string {
    $dir = BACKUP_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0777);
    }
    // Proteger directorio en Apache
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n");
    }
    // Proteger contra listado de directorio en servidores que no soporten .htaccess
    $indexHtml = $dir . '/index.html';
    if (!file_exists($indexHtml)) {
        @file_put_contents($indexHtml, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1></body></html>");
    }
    return $dir;
}

/**
 * Genera un volcado SQL estructurado completo de la base de datos a un archivo en disco o flujo
 *
 * @param PDO $pdo Conexión PDO activa
 * @param string|resource $destination Ruta de archivo destino o flujo (ej: php://output)
 * @param string $userTag Identificador del usuario o sistema que ejecuta el volcado
 * @return array ['success' => bool, 'file' => string, 'size' => int, 'tables' => int, 'error' => string]
 */
function createDatabaseSqlDump(PDO $pdo, $destination, string $userTag = 'Sistema'): array {
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');

    $isResource = is_resource($destination);
    $filePath = $isResource ? '' : (string)$destination;

    if ($isResource) {
        $fp = $destination;
    } else {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0777);
        }

        $fp = @fopen($filePath, 'w');
        if (!$fp) {
            $msg = 'No se pudo abrir el archivo de destino para escritura.';
            if (!is_writable($dir)) {
                $msg = 'La carpeta de respaldos (backups/) no tiene permisos de escritura para el servidor web (PHP-FPM/Apache). Por favor ejecuta en la terminal de tu servidor: sudo chmod -R 777 /var/www/Gestion-de-biblioteca/backups';
            }
            return [
                'success' => false,
                'error' => $msg,
                'file' => $filePath,
                'size' => 0,
                'tables' => 0
            ];
        }
    }

    $writeSql = function($text) use ($fp) {
        fwrite($fp, $text . "\n");
    };

    $dbName = 'library_system';
    try {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn() ?: 'library_system';
    } catch (Exception $e) {}

    $writeSql("-- =============================================================");
    $writeSql("-- Respaldo Automatizado de Base de Datos: " . $dbName);
    $writeSql("-- Sistema de Gestión de Biblioteca");
    $writeSql("-- Fecha y Hora: " . date('Y-m-d H:i:s'));
    $writeSql("-- Generado por: " . $userTag);
    $writeSql("-- =============================================================\n");
    $writeSql("SET FOREIGN_KEY_CHECKS=0;");
    $writeSql("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";");
    $writeSql("SET NAMES utf8mb4;\n");

    $tableCount = 0;
    try {
        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as $tRow) {
            $table = $tRow[0];
            $tableCount++;

            $writeSql("-- -------------------------------------------------------------");
            $writeSql("-- Estructura de tabla: `" . $table . "`");
            $writeSql("-- -------------------------------------------------------------");
            $writeSql("DROP TABLE IF EXISTS `" . $table . "`;");

            $createStmt = $pdo->query("SHOW CREATE TABLE `" . $table . "`");
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            $writeSql($createRow['Create Table'] . ";\n");

            // Volcado de datos de la tabla
            $rowsStmt = $pdo->query("SELECT * FROM `" . $table . "`");
            $rowCount = 0;
            $batch = [];
            $cols = null;

            $writeSql("-- Volcado de datos para la tabla `" . $table . "`");
            while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                $rowCount++;
                if ($cols === null) {
                    $cols = array_map(function($col) { return "`" . $col . "`"; }, array_keys($row));
                }
                $escapedVals = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $escapedVals[] = 'NULL';
                    } else {
                        $escapedVals[] = $pdo->quote($val);
                    }
                }
                $batch[] = "(" . implode(', ', $escapedVals) . ")";

                // Lotes de 100 para optimización de rendimiento y memoria
                if (count($batch) >= 100) {
                    $writeSql("INSERT INTO `" . $table . "` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";");
                    $batch = [];
                }
            }

            if (!empty($batch) && !empty($cols)) {
                $writeSql("INSERT INTO `" . $table . "` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";");
            }

            $writeSql("-- Fin de datos para `" . $table . "` (" . $rowCount . " registros)\n");
        }

        $writeSql("SET FOREIGN_KEY_CHECKS=1;");
        $writeSql("-- Fin del respaldo");
    } catch (Exception $e) {
        $writeSql("\n-- ERROR AL GENERAR RESPALDO: " . $e->getMessage());
        if (!$isResource && is_resource($fp)) {
            fclose($fp);
        }
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $filePath,
            'size' => 0,
            'tables' => $tableCount
        ];
    }

    if (!$isResource) {
        fclose($fp);
        $size = file_exists($filePath) ? filesize($filePath) : 0;
        return [
            'success' => true,
            'file' => $filePath,
            'size' => $size,
            'tables' => $tableCount,
            'error' => ''
        ];
    }

    return [
        'success' => true,
        'file' => '',
        'size' => 0,
        'tables' => $tableCount,
        'error' => ''
    ];
}

/**
 * Obtiene la lista de respaldos almacenados en la carpeta backups/
 * Ordenados de más reciente a más antiguo
 */
function getStoredBackups(): array {
    $dir = initBackupDirectory();
    $files = glob($dir . '/*.sql');
    if (!$files) {
        return [];
    }

    $backups = [];
    foreach ($files as $filePath) {
        $filename = basename($filePath);
        $mtime = filemtime($filePath);
        $size = filesize($filePath);
        $isAuto = str_starts_with($filename, 'backup_auto_');

        $backups[] = [
            'filename' => $filename,
            'path' => $filePath,
            'timestamp' => $mtime,
            'date_formatted' => date('d/m/Y H:i:s', $mtime),
            'size_bytes' => $size,
            'size_formatted' => formatBytes($size),
            'is_auto' => $isAuto,
        ];
    }

    // Ordenar de más reciente a más antiguo
    usort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    return $backups;
}

/**
 * Aplica la política de rotación:
 * Mantiene como máximo $maxBackups (por defecto 10).
 * Si hay más de 10 copias, elimina las más antiguas.
 */
function rotateBackups(int $maxBackups = 10): int {
    $backups = getStoredBackups();
    $deletedCount = 0;

    if (count($backups) > $maxBackups) {
        // Al estar ordenados de más nuevo a más viejo, los excedentes son los últimos
        $toDelete = array_slice($backups, $maxBackups);
        foreach ($toDelete as $item) {
            if (file_exists($item['path'])) {
                if (@unlink($item['path'])) {
                    $deletedCount++;
                }
            }
        }
    }

    return $deletedCount;
}

/**
 * Ejecuta el respaldo automático mensual si aún no se ha generado uno para el mes actual (Año-Mes).
 * Aplica rotación a 10 copias como máximo.
 *
 * @param PDO $pdo
 * @param bool $force Si es true, genera una copia aunque ya exista una este mes
 * @return array ['created' => bool, 'message' => string, 'filename' => string, 'deleted' => int]
 */
function runMonthlyAutoBackup(PDO $pdo, bool $force = false): array {
    $dir = initBackupDirectory();
    $currentMonthTag = date('Y_m'); // ej: 2026_11

    // Verificar si ya existe un respaldo automático para este mes
    $existing = glob($dir . "/backup_auto_{$currentMonthTag}_*.sql");

    if (!empty($existing) && !$force) {
        $latest = basename($existing[0]);
        return [
            'created' => false,
            'message' => "El respaldo automático de este mes (" . date('m/Y') . ") ya existe.",
            'filename' => $latest,
            'deleted' => 0
        ];
    }

    $filename = "backup_auto_{$currentMonthTag}_" . date('d_His') . ".sql";
    $destPath = $dir . '/' . $filename;

    $result = createDatabaseSqlDump($pdo, $destPath, 'Tarea Automática Mensual');

    if (!$result['success']) {
        return [
            'created' => false,
            'message' => 'Error al generar respaldo: ' . $result['error'],
            'filename' => '',
            'deleted' => 0
        ];
    }

    // Rotación: Mantener estrictamente un máximo de 10 copias (eliminando las más antiguas)
    $deleted = rotateBackups(10);

    return [
        'created' => true,
        'message' => "Copia de seguridad mensual generada con éxito ($filename)." . ($deleted > 0 ? " Se eliminó la copia más antigua para conservar el límite de 10." : ""),
        'filename' => $filename,
        'deleted' => $deleted
    ];
}

/**
 * Da formato legible en B, KB, MB, GB a un tamaño en bytes
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Ejecuta un archivo SQL mediante lectura por flujo (streaming buffer).
 * Consume memoria constante (< 4MB) y soporta volcados de gran tamaño.
 *
 * @param PDO $pdo
 * @param string $filePath
 * @return int Cantidad de sentencias SQL ejecutadas con éxito
 * @throws Exception
 */
function executeSqlFileStream(PDO $pdo, string $filePath): int {
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');

    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception("No se pudo abrir el archivo SQL para su lectura en el servidor.");
    }

    $executed = 0;
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO,ALLOW_INVALID_DATES';");
        try {
            $pdo->exec("SET SESSION max_allowed_packet = 134217728;"); // 128 MB
        } catch (Exception $ePkt) {}

        $currentQuery = '';
        $inString = false;
        $stringChar = '';
        $inMultilineComment = false;

        while (($line = fgets($handle)) !== false) {
            $trimmedLine = trim($line);

            if (!$inString && !$inMultilineComment) {
                if ($trimmedLine === '' || str_starts_with($trimmedLine, '--') || str_starts_with($trimmedLine, '#')) {
                    continue;
                }
            }

            $len = strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $char = $line[$i];

                if (!$inString) {
                    if (!$inMultilineComment && $char === '/' && $i + 1 < $len && $line[$i + 1] === '*') {
                        if ($i + 2 < $len && $line[$i + 2] === '!') {
                            $i += 2;
                            while ($i < $len && ctype_digit($line[$i])) {
                                $i++;
                            }
                            continue;
                        }
                        $inMultilineComment = true;
                        $i++;
                        continue;
                    } elseif ($inMultilineComment && $char === '*' && $i + 1 < $len && $line[$i + 1] === '/') {
                        $inMultilineComment = false;
                        $i++;
                        continue;
                    }
                }

                if ($inMultilineComment) {
                    continue;
                }

                if (!$inString) {
                    if ($char === '#' || ($char === '-' && $i + 1 < $len && $line[$i + 1] === '-')) {
                        break;
                    }
                }

                if (($char === "'" || $char === '"' || $char === '`') && ($i === 0 || $line[$i - 1] !== '\\')) {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($stringChar === $char) {
                        if ($char === "'" && $i + 1 < $len && $line[$i + 1] === "'") {
                            $currentQuery .= $char;
                            $i++;
                            $currentQuery .= $line[$i];
                            continue;
                        }
                        $inString = false;
                    }
                }

                if ($char === ';' && !$inString) {
                    $trimmed = trim($currentQuery);
                    if ($trimmed !== '') {
                        $pdo->exec($trimmed);
                        $executed++;
                    }
                    $currentQuery = '';
                } else {
                    $currentQuery .= $char;
                }
            }
        }

        $trimmed = trim($currentQuery);
        if ($trimmed !== '') {
            $pdo->exec($trimmed);
            $executed++;
        }

        fclose($handle);
        $pdo->exec("SET SESSION sql_mode = '';");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        return $executed;
    } catch (Exception $e) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        $pdo->exec("SET SESSION sql_mode = '';");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        throw new Exception("Error en sentencia #" . ($executed + 1) . ": " . $e->getMessage());
    }
}
