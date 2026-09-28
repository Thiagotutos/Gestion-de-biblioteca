<?php
/**
 * migrate_aguapay.php
 * 
 * Lee el archivo ISO 2709 de Aguapay (20260512/basedato.iso),
 * resetea la base de datos y la repuebla con todos los libros,
 * autores, editoriales y categorías extraídos del backup.
 * 
 * Elimina la funcionalidad de "estantes" ya que el sistema Aguapay no los tiene.
 * Extrae números de inventario del campo 859.
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once 'config.php';

echo "<pre>";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     MIGRACIÓN COMPLETA DESDE AGUAPAY (basedato.iso)        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// =====================================================
// PASO 1: PARSEAR EL ISO
// =====================================================

echo "=== PASO 1: Parseando archivo ISO ===\n\n";

$iso_file = __DIR__ . '/20260512/basedato.iso';
if (!file_exists($iso_file)) {
    die("ERROR: No se encontró el archivo $iso_file\n");
}

$data = file_get_contents($iso_file);
$size = strlen($data);
echo "Archivo: basedato.iso ($size bytes)\n";

function parse_iso_records($data) {
    $size = strlen($data);
    $records = [];
    $pos = 0;
    
    while ($pos < $size) {
        // Skip whitespace/newlines
        while ($pos < $size && (ord($data[$pos]) < 32 || $data[$pos] == ' ')) {
            $pos++;
        }
        if ($pos >= $size) break;
        
        // Read leader (24 chars)
        if ($pos + 24 > $size) break;
        $leader = substr($data, $pos, 24);
        $rec_len_str = substr($leader, 0, 5);
        
        if (!ctype_digit($rec_len_str)) {
            $pos++;
            continue;
        }
        
        $rec_len = intval($rec_len_str);
        if ($rec_len < 25 || $rec_len > 99999) {
            $pos++;
            continue;
        }
        
        // Read record (skipping newlines)
        $record = '';
        $rpos = $pos;
        while (strlen($record) < $rec_len && $rpos < $size) {
            $ch = $data[$rpos];
            if ($ch != "\r" && $ch != "\n") {
                $record .= $ch;
            }
            $rpos++;
        }
        
        if (strlen($record) < 24) {
            $pos = $rpos;
            continue;
        }
        
        $base_address_str = substr($record, 12, 5);
        if (!ctype_digit($base_address_str)) {
            $pos = $rpos;
            continue;
        }
        $base_address = intval($base_address_str);
        
        // Directory
        $dir_data = substr($record, 24, $base_address - 25);
        $data_section = substr($record, $base_address);
        
        $fields = [];
        for ($di = 0; $di + 12 <= strlen($dir_data); $di += 12) {
            $entry = substr($dir_data, $di, 12);
            $tag = substr($entry, 0, 3);
            $flen = intval(substr($entry, 3, 4));
            $fpos = intval(substr($entry, 7, 5));
            
            $fdata = substr($data_section, $fpos, $flen);
            $fdata = rtrim($fdata, chr(30));
            
            // Parse subfields (ISIS uses ^)
            $subfields = [];
            if (strpos($fdata, '^') !== false) {
                $parts = preg_split('/\^/', $fdata);
                foreach ($parts as $part) {
                    if (strlen($part) > 0) {
                        $code = $part[0];
                        $value = substr($part, 1);
                        if (!isset($subfields[$code])) {
                            $subfields[$code] = $value;
                        }
                    }
                }
            } else {
                $subfields['_'] = $fdata;
            }
            
            $fields[$tag][] = [
                'data' => $fdata,
                'subfields' => $subfields
            ];
        }
        
        if (!empty($fields)) {
            $records[] = $fields;
        }
        
        $pos = $rpos;
    }
    
    return $records;
}

$records = parse_iso_records($data);
echo "Total de registros parseados: " . count($records) . "\n\n";

// =====================================================
// PASO 2: EXTRAER DATOS DE CADA REGISTRO
// =====================================================

echo "=== PASO 2: Extrayendo datos de los registros ===\n\n";

function clean_text($text) {
    // Remove trailing # and ##
    $text = preg_replace('/#+$/', '', $text);
    // Convert from CP850 to UTF-8
    $utf8 = @mb_convert_encoding($text, 'UTF-8', 'CP850');
    if ($utf8 === false || $utf8 === '') $utf8 = $text;
    // Clean control characters
    $utf8 = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $utf8);
    // Trim whitespace
    $utf8 = trim($utf8);
    return $utf8;
}

function get_field_subfield($record, $tag, $subfield = 'a') {
    if (!isset($record[$tag])) return '';
    $field = $record[$tag][0];
    if (isset($field['subfields'][$subfield])) {
        return clean_text($field['subfields'][$subfield]);
    }
    if (isset($field['subfields']['_'])) {
        return clean_text($field['subfields']['_']);
    }
    return clean_text($field['data']);
}

function get_all_field_subfields($record, $tag, $subfield = 'a') {
    if (!isset($record[$tag])) return [];
    $results = [];
    foreach ($record[$tag] as $field) {
        if (isset($field['subfields'][$subfield])) {
            $val = clean_text($field['subfields'][$subfield]);
            if ($val !== '') $results[] = $val;
        }
    }
    return $results;
}

// Extract structured book data
$books_data = [];
$unique_authors = [];
$unique_publishers = [];
$unique_categories = [];
$skipped = 0;
$errors = [];

foreach ($records as $idx => $record) {
    // Title from 245^a
    $titulo = get_field_subfield($record, '245', 'a');
    if ($titulo === '') {
        $skipped++;
        continue;
    }
    
    // Author from 100^a or 110^a (corporate)
    $autor = get_field_subfield($record, '100', 'a');
    if ($autor === '') {
        $autor = get_field_subfield($record, '110', 'a');
    }
    // Clean author name - remove trailing numbers and artifacts
    $autor = preg_replace('/\d+$/', '', $autor);
    $autor = trim($autor, " \t\n\r\0\x0B,.");
    
    // Publisher from 260^a (format: "City: Publisher, Year")
    $pub_raw = get_field_subfield($record, '260', 'a');
    $editorial = '';
    $anio = '';
    if ($pub_raw !== '') {
        // Parse "Buenos Aires: Editorial, 1998" format
        // Sometimes it's "Editorial, Year" without city
        if (preg_match('/^(.+?):\s*(.+?)(?:,\s*(\d{4}))?\.?\s*$/', $pub_raw, $pm)) {
            $editorial = trim($pm[2], " \t\n\r\0\x0B,.");
            if (isset($pm[3])) $anio = $pm[3];
        } elseif (preg_match('/^(.+?)(?:,\s*(\d{4}))?\.?\s*$/', $pub_raw, $pm)) {
            $editorial = trim($pm[1], " \t\n\r\0\x0B,.");
            if (isset($pm[2])) $anio = $pm[2];
        }
        // If editorial still has city prefix, take after colon
        if (strpos($editorial, ':') !== false) {
            $parts = explode(':', $editorial);
            $editorial = trim(end($parts), " \t\n\r\0\x0B,.");
        }
    }
    
    // ISBN from 020^a
    $isbn = get_field_subfield($record, '020', 'a');
    $isbn = preg_replace('/#+$/', '', $isbn);
    
    // Inventory numbers from 859^a
    $inventarios = get_all_field_subfields($record, '859', 'a');
    
    // Classification from 859^m
    $clasificacion = get_all_field_subfields($record, '859', 'm');
    
    // Category from 659^a (primary category), then 653^a, then 650^a
    $categoria = get_field_subfield($record, '659', 'a');
    if ($categoria === '') {
        $categoria = get_field_subfield($record, '653', 'a');
    }
    if ($categoria === '') {
        $sf = get_field_subfield($record, '650', 'a');
        if ($sf !== '') {
            // May contain % delimited multiple subjects, take first
            $parts = explode('%', $sf);
            $categoria = trim($parts[0]);
        }
    }
    // Clean category - take first term if multiple with %
    if (strpos($categoria, '%') !== false) {
        $parts = explode('%', $categoria);
        $categoria = trim($parts[0]);
    }
    // Title case for category
    $categoria = mb_convert_case(mb_strtolower($categoria, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    // Fix common category names
    $categoria = str_replace(['Ensenanza', 'Educacion', 'Administracion'], ['Enseñanza', 'Educación', 'Administración'], $categoria);
    
    // Edition from 250^a
    $edicion = get_field_subfield($record, '250', 'a');
    
    // Physical description from 300^a
    $descripcion_fisica = get_field_subfield($record, '300', 'a');
    
    // Notes from 500^a
    $notas = get_field_subfield($record, '500', 'a');
    
    // Educational level from 521^a
    $nivel = get_field_subfield($record, '521', 'a');
    
    // Series from 440^a
    $serie = get_field_subfield($record, '440', 'a');
    
    // Language from 008^a
    $idioma = get_field_subfield($record, '008', 'a');
    
    // Control number
    $control = get_field_subfield($record, '001', '_');
    
    // Track unique values
    if ($autor !== '') $unique_authors[$autor] = true;
    if ($editorial !== '') $unique_publishers[$editorial] = true;
    if ($categoria !== '') $unique_categories[$categoria] = true;
    
    // Build book entry - one entry per inventory number
    if (!empty($inventarios)) {
        foreach ($inventarios as $inv_idx => $inv_num) {
            $books_data[] = [
                'titulo' => $titulo,
                'autor' => $autor,
                'editorial' => $editorial,
                'isbn' => $isbn,
                'inventario' => $inv_num,
                'categoria' => $categoria,
                'edicion' => $edicion,
                'anio' => $anio,
                'notas' => $notas,
                'nivel' => $nivel,
                'serie' => $serie,
                'clasificacion' => isset($clasificacion[$inv_idx]) ? $clasificacion[$inv_idx] : '',
                'control' => $control,
            ];
        }
    } else {
        // No inventory numbers - still add the book
        $books_data[] = [
            'titulo' => $titulo,
            'autor' => $autor,
            'editorial' => $editorial,
            'isbn' => $isbn,
            'inventario' => '',
            'categoria' => $categoria,
            'edicion' => $edicion,
            'anio' => $anio,
            'notas' => $notas,
            'nivel' => $nivel,
            'serie' => $serie,
            'clasificacion' => '',
            'control' => $control,
        ];
    }
}

echo "Libros extraídos (con inventarios): " . count($books_data) . "\n";
echo "Registros sin título (omitidos): $skipped\n";
echo "Autores únicos: " . count($unique_authors) . "\n";
echo "Editoriales únicas: " . count($unique_publishers) . "\n";
echo "Categorías únicas: " . count($unique_categories) . "\n\n";

// Show some samples
echo "--- Muestra de 5 libros ---\n";
for ($i = 0; $i < min(5, count($books_data)); $i++) {
    $b = $books_data[$i];
    echo "  #" . ($i+1) . ": \"{$b['titulo']}\" | Autor: {$b['autor']} | Editorial: {$b['editorial']} | ISBN: {$b['isbn']} | Inv: {$b['inventario']} | Cat: {$b['categoria']}\n";
}

// =====================================================
// PASO 3: RESETEAR BASE DE DATOS
// =====================================================

echo "\n=== PASO 3: Reseteando base de datos ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Disable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate tables
    $pdo->exec("TRUNCATE TABLE transactions");
    $pdo->exec("TRUNCATE TABLE books");
    $pdo->exec("TRUNCATE TABLE authors");
    $pdo->exec("TRUNCATE TABLE publishers");
    $pdo->exec("TRUNCATE TABLE categories");
    $pdo->exec("TRUNCATE TABLE racks");
    
    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "✓ Tablas limpiadas (transactions, books, authors, publishers, categories, racks)\n";
    
    // =====================================================
    // PASO 4: INSERTAR AUTORES
    // =====================================================
    
    echo "\n=== PASO 4: Insertando autores ===\n\n";
    
    $author_map = []; // name => id
    $author_id = 1;
    $stmt_author = $pdo->prepare("INSERT INTO authors (id, name) VALUES (?, ?)");
    
    // Sort authors alphabetically
    $authors_list = array_keys($unique_authors);
    sort($authors_list);
    
    foreach ($authors_list as $name) {
        if (trim($name) === '') continue;
        // Skip names that are clearly not authors (page counts, dates, etc.)
        if (preg_match('/^\d+\s*p\.?$/i', $name)) continue;
        if (preg_match('/^\d{4}$/', $name)) continue;
        if (preg_match('/^[ivxlcdm]+,\s*\d+\s*p/i', $name)) continue;
        if (strlen($name) > 250) $name = substr($name, 0, 250);
        
        $stmt_author->execute([$author_id, $name]);
        $author_map[$name] = $author_id;
        $author_id++;
    }
    
    echo "✓ " . count($author_map) . " autores insertados\n";
    
    // =====================================================
    // PASO 5: INSERTAR EDITORIALES
    // =====================================================
    
    echo "\n=== PASO 5: Insertando editoriales ===\n\n";
    
    $publisher_map = []; // name => id
    $pub_id = 1;
    $stmt_pub = $pdo->prepare("INSERT INTO publishers (id, name) VALUES (?, ?)");
    
    $publishers_list = array_keys($unique_publishers);
    sort($publishers_list);
    
    foreach ($publishers_list as $name) {
        if (trim($name) === '') continue;
        // Skip values that are clearly not publishers
        if (preg_match('/^\d{4}$/', $name)) continue;
        if (strlen($name) < 2) continue;
        if (strlen($name) > 250) $name = substr($name, 0, 250);
        
        $stmt_pub->execute([$pub_id, $name]);
        $publisher_map[$name] = $pub_id;
        $pub_id++;
    }
    
    echo "✓ " . count($publisher_map) . " editoriales insertadas\n";
    
    // =====================================================
    // PASO 6: INSERTAR CATEGORÍAS
    // =====================================================
    
    echo "\n=== PASO 6: Insertando categorías ===\n\n";
    
    $category_map = []; // name => id
    $cat_id = 1;
    $stmt_cat = $pdo->prepare("INSERT INTO categories (id, name) VALUES (?, ?)");
    
    $categories_list = array_keys($unique_categories);
    sort($categories_list);
    
    foreach ($categories_list as $name) {
        if (trim($name) === '') continue;
        if (strlen($name) > 250) $name = substr($name, 0, 250);
        
        $stmt_cat->execute([$cat_id, $name]);
        $category_map[$name] = $cat_id;
        $cat_id++;
    }
    
    echo "✓ " . count($category_map) . " categorías insertadas\n";
    
    // =====================================================
    // PASO 7: INSERTAR LIBROS
    // =====================================================
    
    echo "\n=== PASO 7: Insertando libros ===\n\n";
    
    $stmt_book = $pdo->prepare("INSERT INTO books (titulo, author_id, isbn, publisher_id, category_id, rack_id, estado) VALUES (?, ?, ?, ?, ?, NULL, 'Disponible')");
    
    $inserted = 0;
    $book_errors = 0;
    
    foreach ($books_data as $book) {
        $titulo = $book['titulo'];
        if (strlen($titulo) > 255) $titulo = substr($titulo, 0, 252) . '...';
        
        $author_id = isset($author_map[$book['autor']]) ? $author_map[$book['autor']] : null;
        $publisher_id = isset($publisher_map[$book['editorial']]) ? $publisher_map[$book['editorial']] : null;
        $category_id = isset($category_map[$book['categoria']]) ? $category_map[$book['categoria']] : null;
        
        // Use inventory number as the ISBN field
        $inventario = $book['inventario'];
        if ($inventario === '' && $book['isbn'] !== '') {
            // If no inventory but has ISBN, use ISBN
            $inventario = $book['isbn'];
        }
        
        try {
            $stmt_book->execute([$titulo, $author_id, $inventario, $publisher_id, $category_id]);
            $inserted++;
        } catch (Exception $e) {
            $book_errors++;
            if ($book_errors <= 5) {
                echo "  ERROR en '$titulo': " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "✓ $inserted libros insertados\n";
    if ($book_errors > 0) echo "  ⚠ $book_errors errores\n";
    
    // =====================================================
    // PASO 8: RE-INSERTAR USUARIO ADMIN
    // =====================================================
    
    echo "\n=== PASO 8: Re-insertando usuario administrador ===\n\n";
    
    // Check if users table was affected
    $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($user_count == 0) {
        $pdo->exec("INSERT INTO users (nombre, rol, email, password) VALUES 
            ('Admin Biblioteca', 'Administrador', 'admin@biblioteca.com', '\$2y\$10\$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
            ('Lector Prueba', 'Lector', 'lector@biblioteca.com', '\$2y\$10\$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u')
        ");
        echo "✓ Usuarios admin recreados\n";
    } else {
        echo "✓ Usuarios existentes preservados ($user_count usuarios)\n";
    }
    
    $pdo->commit();
    
    echo "\n╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                    MIGRACIÓN COMPLETADA                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    
    // Final stats
    $final_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $final_authors = $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn();
    $final_publishers = $pdo->query("SELECT COUNT(*) FROM publishers")->fetchColumn();
    $final_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    
    echo "  📚 Libros: $final_books\n";
    echo "  ✍️  Autores: $final_authors\n";
    echo "  🏢 Editoriales: $final_publishers\n";
    echo "  📂 Categorías: $final_categories\n";
    echo "  🗄️  Estantes: REMOVIDOS (no existen en Aguapay)\n\n";
    
    // Show sample of final data
    echo "--- Muestra de libros en la base de datos ---\n\n";
    $sample = $pdo->query("
        SELECT b.id, b.titulo, b.isbn as inventario, a.name as autor, p.name as editorial, c.name as categoria
        FROM books b
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN publishers p ON b.publisher_id = p.id
        LEFT JOIN categories c ON b.category_id = c.id
        ORDER BY b.id ASC
        LIMIT 20
    ")->fetchAll();
    
    foreach ($sample as $s) {
        echo "  [{$s['id']}] {$s['titulo']}\n";
        echo "       Autor: " . ($s['autor'] ?? 'N/A') . " | Editorial: " . ($s['editorial'] ?? 'N/A') . " | Inv: " . ($s['inventario'] ?? 'N/A') . " | Cat: " . ($s['categoria'] ?? 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR FATAL: " . $e->getMessage() . "\n";
    echo "Se revirtieron todos los cambios.\n";
}

echo "\n=== FIN ===\n</pre>";
?>
