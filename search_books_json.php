<?php
/**
 * search_books_json.php
 * Endpoint para búsqueda de libros disponibles en formato JSON (autocompletado)
 */
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'No autorizado']));
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Búsqueda de libros que estén disponibles, coincidiendo por título o inventario
$stmt = $pdo->prepare("SELECT id, titulo, isbn FROM books WHERE estado = 'Disponible' AND (titulo LIKE ? OR isbn LIKE ?) ORDER BY titulo ASC LIMIT 20");
$stmt->execute(["%$q%", "%$q%"]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($books);
