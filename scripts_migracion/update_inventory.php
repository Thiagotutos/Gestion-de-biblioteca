<?php
require_once 'config.php';

echo "=== ACTUALIZANDO INVENTARIOS EN BD ===\n\n";

$mst_file = __DIR__ . '/bibliotecla/libros.mst';
$data = file_get_contents($mst_file);
echo "Archivo cargado: " . number_format(strlen($data)) . " bytes\n";

// Extraer inventarios y títulos
$inventory_pattern = '/\^a(\d{6})\^[hcm]/';
preg_match_all($inventory_pattern, $data, $inv_matches, PREG_OFFSET_CAPTURE);
echo "Inventarios encontrados en MST: " . count($inv_matches[1]) . "\n";

$records = [];
foreach ($inv_matches[1] as $match) {
    $inv_num = $match[0];
    $offset = $match[1];
    $start = max(0, $offset - 2000);
    $chunk = substr($data, $start, $offset - $start);
    
    $title = '';
    if (preg_match_all('/#(?:10|1#|0#)\^a([^#\^]{5,200})/', $chunk, $title_matches)) {
        $best = '';
        foreach ($title_matches[1] as $t) {
            $t = trim($t);
            if (strlen($t) > strlen($best) && !preg_match('/^\d+\s*p\./', $t)) {
                $best = $t;
            }
        }
        $title = $best;
    }
    
    $author = '';
    if (preg_match('/#\d+\^a([A-Z][a-záéíóúñ]+,\s*[A-Za-záéíóúñ\s]+?)(?:\^|#)/', $chunk, $auth_m)) {
        $author = trim($auth_m[1]);
    }
    
    if($title != '') {
        $records[] = [
            'inv' => $inv_num,
            'titulo' => mb_strtolower(trim($title)),
            'autor' => mb_strtolower(trim($author))
        ];
    }
}

// Libros de BD
$stmt = $pdo->query("SELECT b.id, b.titulo, b.isbn, a.name as autor FROM books b LEFT JOIN authors a ON b.author_id = a.id WHERE b.id > 12");
$db_books = $stmt->fetchAll();

echo "Libros a actualizar: " . count($db_books) . "\n";

$matched = 0;
$pdo->beginTransaction();

foreach ($db_books as $db_book) {
    $best_inv = null;
    $best_score = 0;
    
    $db_title = mb_strtolower(trim($db_book['titulo']));
    $db_author = mb_strtolower(trim($db_book['autor'] ?? ''));
    
    foreach ($records as $rec) {
        $score = 0;
        if ($db_title === $rec['titulo']) {
            $score += 100;
        } elseif (strpos($db_title, $rec['titulo']) !== false || strpos($rec['titulo'], $db_title) !== false) {
            $score += 80;
        } elseif (mb_substr($db_title, 0, 20) === mb_substr($rec['titulo'], 0, 20)) {
            $score += 60;
        }
        
        if ($db_author !== '' && $rec['autor'] !== '') {
            if (strpos($db_author, $rec['autor']) !== false || strpos($rec['autor'], $db_author) !== false) {
                $score += 20;
            }
        }
        
        if ($score > $best_score) {
            $best_score = $score;
            $best_inv = $rec['inv'];
            if ($score >= 100) break; // Perfect match
        }
    }
    
    if ($best_score >= 60 && $best_inv) {
        // Actualizar en BD
        $stmt_upd = $pdo->prepare("UPDATE books SET isbn = ? WHERE id = ?");
        $stmt_upd->execute([$best_inv, $db_book['id']]);
        $matched++;
    }
}

$pdo->commit();
echo "Total actualizados con inventario: $matched\n";
echo "=== COMPLETADO ===\n";
