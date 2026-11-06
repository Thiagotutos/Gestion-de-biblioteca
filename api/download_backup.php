<?php
/**
 * api/download_backup.php
 * Descarga segura de respaldos almacenados en la carpeta backups/.
 * Requiere rol de Administrador.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/backup_manager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit("Acceso denegado. Se requiere rol de Administrador.");
}

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (empty($file) || !str_ends_with(strtolower($file), '.sql')) {
    http_response_code(400);
    exit("Archivo no especificado o formato inválido.");
}

$backupDir = initBackupDirectory();
$fullPath = $backupDir . '/' . $file;

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit("El archivo de respaldo solicitado no existe en el servidor.");
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($fullPath));
header('Pragma: no-cache');
header('Expires: 0');

readfile($fullPath);
exit;

