<?php
/**
 * export_db.php
 * Endpoint para exportar la base de datos completa a un archivo .sql descargable.
 * Requiere rol de Administrador.
 */
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit("Acceso denegado. Se requiere rol de Administrador.");
}

// Configurar límites para volcados grandes
set_time_limit(300);
ini_set('memory_limit', '256M');

$dbName = 'library_system';
$filename = 'backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';

// Enviar encabezados HTTP para descarga directa de archivo .sql
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

function writeSql($fp, $text) {
    fwrite($fp, $text . "\n");
}

writeSql($output, "-- =============================================================");
writeSql($output, "-- Respaldo de Base de Datos: " . $dbName);
writeSql($output, "-- Generado por el Sistema de Gestión de Biblioteca");
writeSql($output, "-- Fecha y Hora: " . date('Y-m-d H:i:s'));
writeSql($output, "-- Usuario: " . ($_SESSION['user_email'] ?? 'Admin'));
writeSql($output, "-- =============================================================\n");
writeSql($output, "SET FOREIGN_KEY_CHECKS=0;");
writeSql($output, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";");
writeSql($output, "SET NAMES utf8mb4;\n");

try {
    $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_NUM);

    foreach ($tables as $tRow) {
        $table = $tRow[0];

        writeSql($output, "-- -------------------------------------------------------------");
        writeSql($output, "-- Estructura de tabla: `" . $table . "`");
        writeSql($output, "-- -------------------------------------------------------------");
        writeSql($output, "DROP TABLE IF EXISTS `" . $table . "`;");

        $createStmt = $pdo->query("SHOW CREATE TABLE `" . $table . "`");
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        writeSql($output, $createRow['Create Table'] . ";\n");

        // Datos de la tabla
        $rowsStmt = $pdo->query("SELECT * FROM `" . $table . "`");
        $rowCount = 0;
        $batch = [];
        $cols = null;

        writeSql($output, "-- Volcado de datos para la tabla `" . $table . "`");
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

            // Escribir en lotes de 100 para optimizar rendimiento y memoria
            if (count($batch) >= 100) {
                writeSql($output, "INSERT INTO `" . $table . "` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";");
                $batch = [];
            }
        }

        if (!empty($batch) && !empty($cols)) {
            writeSql($output, "INSERT INTO `" . $table . "` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $batch) . ";");
        }

        writeSql($output, "-- Fin de datos para `" . $table . "` (" . $rowCount . " registros)\n");
    }

    writeSql($output, "SET FOREIGN_KEY_CHECKS=1;");
    writeSql($output, "-- Fin del respaldo");
} catch (Exception $e) {
    writeSql($output, "\n-- ERROR AL GENERAR RESPALDO: " . $e->getMessage());
}

fclose($output);
exit;
