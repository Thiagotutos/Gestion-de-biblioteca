<?php
/**
 * search_entities_json.php
 * Endpoint para búsqueda asíncrona de autores, editoriales y categorías en formato JSON.
 */
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Validar tipo de entidad permitido
$allowed_tables = [
    'authors' => 'authors',
    'publishers' => 'publishers',
    'categories' => 'categories'
];

if (!isset($allowed_tables[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de entidad no válido']);
    exit;
}

$table = $allowed_tables[$type];

header('Content-Type: application/json; charset=utf-8');

if (mb_strlen($q) < 1) {
    // Si no hay término, devolver los primeros 20 ordenados alfabéticamente
    $stmt = $pdo->prepare("SELECT id, name FROM {$table} ORDER BY name ASC LIMIT 20");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM {$table} WHERE name LIKE ? ORDER BY name ASC LIMIT 20");
    $stmt->execute(["%$q%"]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
