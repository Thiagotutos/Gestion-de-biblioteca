<?php
/**
 * search_books_json.php
 * Endpoint para búsqueda de libros disponibles en formato JSON (autocompletado)
 */
require_once __DIR__ . '/../config.php';
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

// Búsqueda de libros disponibles por título, inventario o autor
$stmt = $pdo->prepare("
    SELECT b.id, b.titulo, b.isbn, a.name as autor 
    FROM books b 
    LEFT JOIN authors a ON b.author_id = a.id 
    WHERE b.estado = 'Disponible' AND (b.titulo LIKE ? OR b.isbn LIKE ? OR a.name LIKE ?) 
    ORDER BY b.titulo ASC LIMIT 20
");
$stmt->execute(["%$q%", "%$q%", "%$q%"]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($books);
