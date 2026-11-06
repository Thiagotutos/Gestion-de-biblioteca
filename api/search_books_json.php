<?php
/**
 * search_books_json.php
 * Endpoint para búsqueda predictiva de libros en formato JSON para el módulo de préstamos.
 * Permite buscar por Título, Autor, Categoría y Número de Inventario / ID.
 * Devuelve ejemplares disponibles y no disponibles para retroalimentación transparente.
 */
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'No autorizado']));
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search_param = "%$q%";

// Búsqueda en libros: Título, Nº Inventario, Signatura, ISBN, Observación, Autor y Materia
$stmt = $pdo->prepare("
    SELECT b.id, b.titulo, b.isbn, b.signatura, b.isbn_editorial, b.estado, a.name as autor, c.name as categoria,
           (SELECT COUNT(*) FROM books b2 WHERE b2.titulo = b.titulo AND b2.estado = 'Disponible') as copias_disponibles
    FROM books b 
    LEFT JOIN authors a ON b.author_id = a.id 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE (
        b.titulo LIKE ? 
        OR b.isbn LIKE ? 
        OR b.signatura LIKE ? 
        OR b.isbn_editorial LIKE ? 
        OR b.observacion LIKE ? 
        OR a.name LIKE ? 
        OR c.name LIKE ? 
        OR CAST(b.id AS CHAR) = ?
    )
    ORDER BY 
        CASE WHEN b.estado = 'Disponible' THEN 0 ELSE 1 END ASC,
        b.titulo ASC,
        b.id ASC 
    LIMIT 40
");
$stmt->execute([$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $q]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($books);
