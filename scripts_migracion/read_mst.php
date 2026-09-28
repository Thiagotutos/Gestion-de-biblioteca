<?php
/**
 * read_mst.php
 * Lee el archivo MST de Bibliotecla y extrae los números de inventario.
 * El formato MST de ISIS/CDS tiene una estructura binaria específica.
 */
require_once 'config.php';

echo "<pre>";
echo "=== LECTURA DE ARCHIVO MST - BÚSQUEDA DE INVENTARIOS ===\n\n";

$mst_file = __DIR__ . '/bibliotecla/libros.mst';

if (!file_exists($mst_file)) {
    die("Archivo no encontrado: $mst_file");
}

$data = file_get_contents($mst_file);
$size = strlen($data);
echo "Tamaño del archivo: " . number_format($size) . " bytes\n\n";

// Buscar patrones de inventario en el archivo
// Los números de inventario suelen ser 5-6 dígitos precedidos por algún marcador

// Buscar cualquier secuencia que parezca un número de inventario
// Patrón típico en MARC/ISIS: campo con ^a seguido de número
$inventory_numbers = [];

// Buscar el patrón "inventario" en el contenido binario
$pos = 0;
$search_terms = ['inventario', 'INVENTARIO', 'Inventario'];
foreach ($search_terms as $term) {
    $offset = 0;
    while (($pos = strpos($data, $term, $offset)) !== false) {
        $context = substr($data, max(0, $pos - 50), 200);
        // Limpiar caracteres no imprimibles para mostrar
        $clean_context = preg_replace('/[\x00-\x1F\x80-\xFF]/', '.', $context);
        echo "Encontrado '$term' en posición $pos:\n  $clean_context\n\n";
        $offset = $pos + strlen($term);
    }
}

echo "\n--- Buscando patrones de subcampo ^a con números ---\n\n";

// En ISIS/MARC, los campos usan ^ como delimitador de subcampo
// Buscar ^a seguido de 4-6 dígitos (típico de inventario)
preg_match_all('/\^a(\d{4,6})\b/', $data, $matches, PREG_OFFSET_CAPTURE);

if (count($matches[1]) > 0) {
    echo "Encontrados " . count($matches[1]) . " posibles números de inventario (^a + 4-6 dígitos):\n\n";
    $sample_count = min(30, count($matches[1]));
    for ($i = 0; $i < $sample_count; $i++) {
        $num = $matches[1][$i][0];
        $offset = $matches[1][$i][1];
        $context = substr($data, max(0, $offset - 80), 200);
        $clean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '.', $context);
        echo "  [$i] Inventario: $num (offset: $offset)\n";
        echo "     Contexto: $clean\n\n";
    }
    
    // Mostrar todos los números únicos encontrados
    $all_nums = array_map(function($m) { return $m[0]; }, $matches[1]);
    $unique = array_unique($all_nums);
    sort($unique);
    echo "\nNúmeros únicos encontrados: " . count($unique) . "\n";
    echo "Rango: " . min($unique) . " - " . max($unique) . "\n\n";
    echo "Primeros 50 números: " . implode(', ', array_slice($unique, 0, 50)) . "\n";
} else {
    echo "No se encontraron patrones ^a con números de 4-6 dígitos.\n";
}

echo "\n--- Buscando campo 901 (típico para inventario en BNM/CEPAL) ---\n\n";

// En el formato CEPAL, el inventario está en v02^* o v901
// Buscar #901 o patrones similares
$patterns_to_check = ['#901#', '#02#', '#859#'];
foreach ($patterns_to_check as $pat) {
    $count = substr_count($data, $pat);
    echo "Patrón '$pat': $count ocurrencias\n";
}

echo "\n--- Estructura de un registro típico ---\n\n";

// Buscar delimitadores de registro ISIS (típicamente registros empiezan con un header binario)
// Mostrar los primeros bytes del archivo para entender la estructura
echo "Primeros 500 bytes (hex + ascii):\n";
$chunk = substr($data, 0, 500);
for ($i = 0; $i < strlen($chunk); $i += 16) {
    $hex = '';
    $ascii = '';
    for ($j = 0; $j < 16 && ($i + $j) < strlen($chunk); $j++) {
        $byte = ord($chunk[$i + $j]);
        $hex .= sprintf('%02X ', $byte);
        $ascii .= ($byte >= 32 && $byte < 127) ? $chunk[$i + $j] : '.';
    }
    printf("%04X: %-48s %s\n", $i, $hex, $ascii);
}

// Buscar también por el ISBN que ya tenemos en la BD para cruzar datos
echo "\n\n--- Buscando ISBNs conocidos de la BD ---\n\n";
$stmt = $pdo->query("SELECT isbn, titulo FROM books WHERE isbn IS NOT NULL AND isbn != '' LIMIT 10");
$known_isbns = $stmt->fetchAll();

foreach ($known_isbns as $book) {
    // Limpiar el ISBN
    $isbn_clean = preg_replace('/[^0-9X]/', '', $book['isbn']);
    if (strlen($isbn_clean) >= 10) {
        $found = strpos($data, $isbn_clean);
        echo "ISBN '{$book['isbn']}' ({$book['titulo']}): " . ($found !== false ? "ENCONTRADO en pos $found" : "no encontrado") . "\n";
        
        if ($found !== false) {
            $context = substr($data, max(0, $found - 100), 300);
            $clean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '.', $context);
            echo "  Contexto: $clean\n\n";
        }
    }
}

echo "\n=== FIN ===\n</pre>";
?>
