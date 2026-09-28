<?php
/**
 * extract_inventory.php
 * Extrae números de inventario del archivo MST de Bibliotecla
 * y los asocia con los libros en la base de datos.
 * 
 * Estructura del MST:
 * - Los registros están separados por delimitadores binarios
 * - Dentro de cada registro, los campos usan ### como separador  
 * - El título está en campos con ^a seguido del texto
 * - El inventario está en un subcampo ^a de 6 dígitos, seguido de ^h, ^m, etc.
 * - Los ISBN originales son de 10 dígitos (ej: 84-08-01645-8)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

echo "<pre>";
echo "=== EXTRACCIÓN DE INVENTARIOS DEL MST ===\n\n";

$mst_file = __DIR__ . '/bibliotecla/libros.mst';
$data = file_get_contents($mst_file);
echo "Archivo cargado: " . number_format(strlen($data)) . " bytes\n\n";

// Paso 1: Separar el archivo en registros
// Los registros ISIS/MST están separados por secuencias binarias
// Vamos a buscar todos los bloques que contengan datos de libros

// Estrategia: buscar cada bloque que contenga un título (campo con texto legible)
// y extraer el inventario asociado

// Primero, encontrar todos los números de inventario REALES
// (6 dígitos, seguidos de ^h o ^c o ^m - no confundir con páginas)
$inventory_pattern = '/\^a(\d{6})\^[hcm]/';
preg_match_all($inventory_pattern, $data, $inv_matches, PREG_OFFSET_CAPTURE);

echo "Inventarios encontrados (^a + 6 dígitos + ^h/^c/^m): " . count($inv_matches[1]) . "\n\n";

// Paso 2: Para cada inventario, buscar el título del libro más cercano
// En el formato CEPAL/ISIS, el título suele estar en un campo anterior
// separado por ### o #1# o #10

$records = [];

foreach ($inv_matches[1] as $match) {
    $inv_num = $match[0];
    $inv_offset = $match[1];
    
    // Buscar hacia atrás para encontrar el título
    // El título típicamente está en el campo 245 o 200 de MARC
    // En el MST, aparece como texto precedido por ^a después de un separador #
    
    // Tomar un chunk grande hacia atrás
    $start = max(0, $inv_offset - 2000);
    $chunk = substr($data, $start, $inv_offset - $start);
    
    // Buscar el ISBN (10 dígitos) en este bloque
    $isbn10 = '';
    if (preg_match('/(\d[\d-]{8,12}[\dX])/', $chunk, $isbn_m)) {
        $isbn10 = $isbn_m[1];
    }
    
    // Buscar el título - usualmente después de #10^a o #1#^a
    $title = '';
    // Buscar patrón: #10^a o #1#^a seguido de texto hasta el próximo #
    if (preg_match_all('/#(?:10|1#|0#)\^a([^#\^]{5,200})/', $chunk, $title_matches)) {
        // El título suele ser el texto más largo encontrado
        $best = '';
        foreach ($title_matches[1] as $t) {
            $t = trim($t);
            if (strlen($t) > strlen($best) && !preg_match('/^\d+\s*p\./', $t)) {
                $best = $t;
            }
        }
        $title = $best;
    }
    
    // Buscar autor - usualmente antes del título con formato "Apellido, Nombre"
    $author = '';
    if (preg_match('/#\d+\^a([A-Z][a-záéíóúñ]+,\s*[A-Za-záéíóúñ\s]+?)(?:\^|#)/', $chunk, $auth_m)) {
        $author = trim($auth_m[1]);
    }
    
    $records[] = [
        'inventario' => $inv_num,
        'titulo' => $title,
        'isbn10' => $isbn10,
        'autor' => $author
    ];
}

// Eliminar duplicados (un libro puede tener varios ejemplares)
echo "Registros extraídos: " . count($records) . "\n\n";

// Mostrar primeros 30 para verificar
echo "--- PRIMEROS 30 REGISTROS ---\n\n";
for ($i = 0; $i < min(30, count($records)); $i++) {
    $r = $records[$i];
    echo sprintf("  INV: %s | ISBN10: %-15s | Título: %s | Autor: %s\n",
        $r['inventario'],
        $r['isbn10'] ?: 'N/A',
        mb_substr($r['titulo'], 0, 60) ?: 'N/A',
        mb_substr($r['autor'], 0, 30) ?: 'N/A'
    );
}

// Paso 3: Intentar asociar con la BD actual
echo "\n\n--- ASOCIACIÓN CON LA BD ---\n\n";

// Obtener todos los libros de la BD
$stmt = $pdo->query("SELECT b.id, b.titulo, b.isbn, a.name as autor FROM books b LEFT JOIN authors a ON b.author_id = a.id");
$db_books = $stmt->fetchAll();

echo "Libros en BD: " . count($db_books) . "\n";

$matched = 0;
$unmatched = 0;
$match_log = [];

foreach ($db_books as $db_book) {
    $best_inv = null;
    $best_score = 0;
    
    foreach ($records as $rec) {
        $score = 0;
        
        // Comparar por título (normalizado)
        $db_title = mb_strtolower(trim($db_book['titulo']));
        $rec_title = mb_strtolower(trim($rec['titulo']));
        
        if ($db_title !== '' && $rec_title !== '') {
            // Comparación exacta
            if ($db_title === $rec_title) {
                $score += 100;
            }
            // Comparación parcial - el título de la BD contiene o es contenido por el del MST
            elseif (strpos($db_title, $rec_title) !== false || strpos($rec_title, $db_title) !== false) {
                $score += 80;
            }
            // Primeras 20 letras coinciden
            elseif (mb_substr($db_title, 0, 20) === mb_substr($rec_title, 0, 20)) {
                $score += 60;
            }
        }
        
        // Comparar por autor
        if (!empty($db_book['autor']) && !empty($rec['autor'])) {
            $db_author = mb_strtolower($db_book['autor']);
            $rec_author = mb_strtolower($rec['autor']);
            if (strpos($db_author, $rec_author) !== false || strpos($rec_author, $db_author) !== false) {
                $score += 20;
            }
        }
        
        if ($score > $best_score) {
            $best_score = $score;
            $best_inv = $rec['inventario'];
        }
    }
    
    if ($best_score >= 60) {
        $matched++;
        if ($matched <= 20) {
            $match_log[] = sprintf("  [OK] BD#%d '%s' -> INV: %s (score: %d)",
                $db_book['id'],
                mb_substr($db_book['titulo'], 0, 40),
                $best_inv,
                $best_score
            );
        }
    } else {
        $unmatched++;
        if ($unmatched <= 10) {
            $match_log[] = sprintf("  [??] BD#%d '%s' -> Sin match (mejor: %d)",
                $db_book['id'],
                mb_substr($db_book['titulo'], 0, 40),
                $best_score
            );
        }
    }
}

foreach ($match_log as $log) {
    echo $log . "\n";
}

echo "\nResultados: $matched coincidencias, $unmatched sin match\n";
echo "\n=== FIN ===\n</pre>";
?>
