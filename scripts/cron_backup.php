<?php
/**
 * scripts/cron_backup.php
 * Script CLI para ejecutar la copia de seguridad mensual automática mediante Cron en Linux.
 * 
 * Uso manual o en Crontab:
 * php /var/www/Gestion-de-biblioteca/scripts/cron_backup.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'secret_cron_token_2026')) {
    http_response_code(403);
    die("Acceso denegado. Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/backup_manager.php';

echo "========================================================\n";
echo "📦 Respaldos Automáticos - Sistema de Gestión de Biblioteca\n";
echo "Fecha y Hora: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n";

try {
    $force = isset($argv[1]) && $argv[1] === '--force';
    $res = runMonthlyAutoBackup($pdo, $force);
    echo "Resultado: " . $res['message'] . "\n";
    if ($res['deleted'] > 0) {
        echo "Rotación: Se eliminaron {$res['deleted']} copias antiguas para mantener el límite de 10.\n";
    }
    echo "Estado: COMPLETADO CON ÉXITO\n";
    exit(0);
} catch (Exception $e) {
    echo "ERROR FATAL: " . $e->getMessage() . "\n";
    exit(1);
}

