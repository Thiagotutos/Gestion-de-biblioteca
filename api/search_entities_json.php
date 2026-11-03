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

header('Content-Type: application/json; charset=utf-8');

// Verificación de disponibilidad de número de inventario
if ($type === 'check_isbn') {
    $isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';
    if ($isbn === '') {
        echo json_encode(['exists' => false]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, titulo, isbn FROM books WHERE isbn = ? LIMIT 1");
    $stmt->execute([$isbn]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($book) {
        echo json_encode(['exists' => true, 'book' => $book]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}

// Obtener último número de inventario y siguiente sugerido
if ($type === 'get_next_isbn') {
    $stmt_max = $pdo->query("SELECT isbn FROM books WHERE isbn REGEXP '^[0-9]+$' ORDER BY CAST(isbn AS UNSIGNED) DESC LIMIT 1");
    $last_isbn = $stmt_max->fetchColumn();
    $next_isbn = '000001';
    if ($last_isbn !== false && $last_isbn !== null && $last_isbn !== '') {
        $digits = strlen($last_isbn);
        $next_val = (int)$last_isbn + 1;
        $next_isbn = str_pad((string)$next_val, max(6, $digits), '0', STR_PAD_LEFT);
    }
    echo json_encode(['last_isbn' => $last_isbn, 'next_isbn' => $next_isbn]);
    exit;
}

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
