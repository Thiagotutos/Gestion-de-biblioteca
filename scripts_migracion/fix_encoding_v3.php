<?php
/**
 * fix_encoding_v3.php
 * Corrige nombres de autores con caracteres mal codificados en la base de datos.
 * Detecta patrones como "NikolÔö£íi" y los reemplaza por "Nikolái", etc.
 * Fusiona duplicados manteniendo relaciones con libros.
 */
require_once 'config.php';

// Iniciar sesión para verificar permisos si se accede por navegador
if (php_sapi_name() !== 'cli') {
    session_start();
}

echo "<pre>";
echo "=== CORRECCIÓN DE ENCODING v3 ===\n\n";

// Mapa manual de correcciones conocidas
// Formato: 'nombre incorrecto o patrón' => 'nombre correcto'
$manual_corrections = [
    // Autores con problemas de encoding conocidos
    'Gogol, NikolÔö£íi' => 'Gogol, Nikolái',
    'Gogol, Nikol├íi' => 'Gogol, Nikolái',
    'Gogol, NikolÃ¡i' => 'Gogol, Nikolái',
    
    // Patrones comunes de doble encoding UTF-8
    'CortÔö£íazar' => 'Cortázar',
    'Cort├ízar' => 'Cortázar',
    'CortÃ¡zar' => 'Cortázar',
    
    'GarcÔö£ía' => 'García',
    'Garc├¡a' => 'García',
    'GarcÃ­a' => 'García',
    
    'MÔö£íarquez' => 'Márquez',
    'M├írquez' => 'Márquez',
    'MÃ¡rquez' => 'Márquez',
    
    'BÔö£íecquer' => 'Bécquer',
    'B├®cquer' => 'Bécquer',
    'BÃ©cquer' => 'Bécquer',
    
    'RodrÔö£íguez' => 'Rodríguez',
    'Rodr├¡guez' => 'Rodríguez',
    'RodrÃ­guez' => 'Rodríguez',
    
    'GÔö£ímez' => 'Gómez',
    'G├│mez' => 'Gómez',
    'GÃ³mez' => 'Gómez',
    
    'DÔö£íaz' => 'Díaz',
    'D├¡az' => 'Díaz',
    'DÃ­az' => 'Díaz',
    
    'PÔö£íerez' => 'Pérez',
    'P├®rez' => 'Pérez',
    'PÃ©rez' => 'Pérez',
    
    'GonzÔö£íalez' => 'González',
    'Gonz├ílez' => 'González',
    'GonzÃ¡lez' => 'González',
    
    'HernÔö£íandez' => 'Hernández',
    'Hern├índez' => 'Hernández',
    'HernÃ¡ndez' => 'Hernández',
    
    'LÔö£ípez' => 'López',
    'L├│pez' => 'López',
    'LÃ³pez' => 'López',
    
    'MartÔö£ínez' => 'Martínez',
    'Mart├¡nez' => 'Martínez',
    'MartÃ­nez' => 'Martínez',
    
    'SÔö£ínchez' => 'Sánchez',
    'S├ínchez' => 'Sánchez',
    'SÃ¡nchez' => 'Sánchez',
    
    'RamÔö£írez' => 'Ramírez',
    'Ram├¡rez' => 'Ramírez',
    'RamÃ­rez' => 'Ramírez',
    
    'JimÔö£ínez' => 'Jiménez',
    'Jim├®nez' => 'Jiménez',
    'JimÃ©nez' => 'Jiménez',
    
    'FernÔö£índez' => 'Fernández',
    'Fern├índez' => 'Fernández',
    'FernÃ¡ndez' => 'Fernández',
    
    'NuÔö£íez' => 'Núñez',
    'Nu├▒ez' => 'Núñez',
    'NuÃ±ez' => 'Núñez',
];

// Mapa de reemplazos de secuencias de bytes rotos -> carácter correcto
$byte_replacements = [
    // Doble encoding UTF-8 common patterns
    "\xC3\x83\xC2\xA1" => 'á',
    "\xC3\x83\xC2\xA9" => 'é',
    "\xC3\x83\xC2\xAD" => 'í',
    "\xC3\x83\xC2\xB3" => 'ó',
    "\xC3\x83\xC2\xBA" => 'ú',
    "\xC3\x83\xC2\xB1" => 'ñ',
    "\xC3\x83\xC2\xBC" => 'ü',
];

/**
 * Intenta reparar una cadena con encoding roto.
 * Prueba múltiples estrategias de decodificación.
 */
function fix_broken_encoding($str) {
    global $byte_replacements, $manual_corrections;
    
    // Primero verificar si hay una corrección manual exacta
    if (isset($manual_corrections[$str])) {
        return $manual_corrections[$str];
    }
    
    $original = $str;
    
    // Verificar si la cadena tiene caracteres problemáticos
    $has_problems = false;
    
    // Detectar patrones de encoding roto
    $problem_patterns = [
        '/Ôö£/', '/├/', '/Ã[^\s]/', '/[\x80-\x9F]/',
        '/\xC3[\x80-\xBF]\xC2[\x80-\xBF]/',  // doble-encoding UTF-8
    ];
    
    foreach ($problem_patterns as $pattern) {
        if (@preg_match($pattern, $str)) {
            $has_problems = true;
            break;
        }
    }
    
    if (!$has_problems) {
        return $str;
    }
    
    // Estrategia 1: Aplicar reemplazos de bytes conocidos
    foreach ($byte_replacements as $broken => $fixed) {
        if ($fixed !== '' && strpos($str, $broken) !== false) {
            $str = str_replace($broken, $fixed, $str);
        }
    }
    
    // Estrategia 2: Intentar decodificar doble-encoding UTF-8
    // (la cadena fue codificada como UTF-8 dos veces)
    if ($str === $original) {
        $decoded = @mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        if ($decoded !== false && $decoded !== $str && mb_check_encoding($decoded, 'UTF-8')) {
            $str = $decoded;
        }
    }
    
    // Estrategia 3: Intentar desde Latin-1 (ISO-8859-1) a UTF-8
    if ($str === $original) {
        $decoded = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $str);
        if ($decoded !== false && strlen($decoded) > 0) {
            // Verificar que el resultado es UTF-8 válido cuando se re-codifica
            $re_encoded = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $decoded);
            if ($re_encoded !== false && mb_check_encoding($re_encoded, 'UTF-8')) {
                // Verificar que no perdimos demasiados caracteres
                if (strlen($decoded) >= strlen($original) * 0.5) {
                    $str = $decoded;
                }
            }
        }
    }
    
    // Estrategia 4: CP850 a UTF-8
    if ($str === $original) {
        $decoded = @iconv('CP850', 'UTF-8//IGNORE', $str);
        if ($decoded !== false && strlen($decoded) > 0 && mb_check_encoding($decoded, 'UTF-8')) {
            if (strlen($decoded) >= strlen($original) * 0.5) {
                $str = $decoded;
            }
        }
    }
    
    // Verificar corrección manual parcial
    foreach ($manual_corrections as $broken => $fixed) {
        if (strpos($str, $broken) !== false) {
            $str = str_replace($broken, $fixed, $str);
        }
    }
    
    return $str;
}

/**
 * Detectar si un nombre tiene caracteres sospechosos
 */
function has_encoding_issues($name) {
    // Caracteres que normalmente no deberían aparecer en nombres de autores
    $suspicious_patterns = [
        '/[Ôö£]/',           // Artefactos de triple encoding
        '/├/',               // CP850/Latin corrupto
        '/Ã[¡©­³ºñ¼]/',     // Doble encoding UTF-8 
        '/[\x00-\x1F]/',     // Caracteres de control
        '/[\x80-\x9F]/',     // Rango C1 (no debería existir en UTF-8 válido)
        '/â€/',              // Windows-1252 mangled
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (@preg_match($pattern, $name)) {
            return true;
        }
    }
    
    return false;
}

// ============================================
// PASO 1: Buscar y corregir autores con encoding roto
// ============================================
echo "--- PASO 1: Corrigiendo autores con encoding roto ---\n";

$stmt = $pdo->query("SELECT id, name FROM authors ORDER BY id");
$authors = $stmt->fetchAll();
$fixed_count = 0;
$issues_found = [];

foreach ($authors as $a) {
    $original = $a['name'];
    
    // Detectar problemas
    if (has_encoding_issues($original)) {
        $fixed_name = fix_broken_encoding($original);
        
        // Solo actualizar si cambió
        if ($fixed_name !== $original && $fixed_name !== '') {
            $issues_found[] = [
                'id' => $a['id'],
                'original' => $original,
                'fixed' => $fixed_name
            ];
        } else {
            // Si no pudimos arreglar automáticamente, registrar
            $issues_found[] = [
                'id' => $a['id'],
                'original' => $original,
                'fixed' => null
            ];
        }
    }
}

// Aplicar correcciones
foreach ($issues_found as $issue) {
    if ($issue['fixed'] !== null) {
        // Verificar si ya existe un autor con el nombre correcto
        $check = $pdo->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
        $check->execute([$issue['fixed'], $issue['id']]);
        $existing = $check->fetch();
        
        if ($existing) {
            // Reasignar libros del autor duplicado al existente
            $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
                ->execute([$existing['id'], $issue['id']]);
            // Eliminar el autor duplicado
            $pdo->prepare("DELETE FROM authors WHERE id = ?")->execute([$issue['id']]);
            echo "  FUSIONADO: '{$issue['original']}' (ID:{$issue['id']}) -> '{$issue['fixed']}' (ID:{$existing['id']})\n";
        } else {
            // Simplemente actualizar el nombre
            $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")
                ->execute([$issue['fixed'], $issue['id']]);
            echo "  CORREGIDO: '{$issue['original']}' -> '{$issue['fixed']}'\n";
        }
        $fixed_count++;
    } else {
        echo "  [!] NO PUDE ARREGLAR: '{$issue['original']}' (ID:{$issue['id']})\n";
    }
}

echo "\nAutores corregidos: $fixed_count\n\n";

// ============================================
// PASO 2: Corregir títulos de libros con encoding roto
// ============================================
echo "--- PASO 2: Corrigiendo títulos de libros ---\n";

$stmt = $pdo->query("SELECT id, titulo FROM books");
$books = $stmt->fetchAll();
$fixed_books = 0;

foreach ($books as $b) {
    if (has_encoding_issues($b['titulo'])) {
        $fixed_title = fix_broken_encoding($b['titulo']);
        if ($fixed_title !== $b['titulo'] && $fixed_title !== '') {
            $pdo->prepare("UPDATE books SET titulo = ? WHERE id = ?")
                ->execute([$fixed_title, $b['id']]);
            echo "  LIBRO: '{$b['titulo']}' -> '$fixed_title'\n";
            $fixed_books++;
        }
    }
}

echo "\nTítulos corregidos: $fixed_books\n\n";

// ============================================
// PASO 3: Corregir editoriales con encoding roto
// ============================================
echo "--- PASO 3: Corrigiendo editoriales ---\n";

$stmt = $pdo->query("SELECT id, name FROM publishers");
$pubs = $stmt->fetchAll();
$fixed_pubs = 0;

foreach ($pubs as $p) {
    if (has_encoding_issues($p['name'])) {
        $fixed_name = fix_broken_encoding($p['name']);
        if ($fixed_name !== $p['name'] && $fixed_name !== '') {
            // Verificar duplicados
            $check = $pdo->prepare("SELECT id FROM publishers WHERE name = ? AND id != ?");
            $check->execute([$fixed_name, $p['id']]);
            $existing = $check->fetch();
            
            if ($existing) {
                $pdo->prepare("UPDATE books SET publisher_id = ? WHERE publisher_id = ?")
                    ->execute([$existing['id'], $p['id']]);
                $pdo->prepare("DELETE FROM publishers WHERE id = ?")->execute([$p['id']]);
                echo "  FUSIONADO: '{$p['name']}' -> '$fixed_name'\n";
            } else {
                $pdo->prepare("UPDATE publishers SET name = ? WHERE id = ?")
                    ->execute([$fixed_name, $p['id']]);
                echo "  CORREGIDO: '{$p['name']}' -> '$fixed_name'\n";
            }
            $fixed_pubs++;
        }
    }
}

echo "\nEditoriales corregidas: $fixed_pubs\n\n";

// ============================================
// PASO 4: Fusionar autores duplicados (mismo nombre, distinto ID)
// ============================================
echo "--- PASO 4: Fusionando autores duplicados ---\n";

$stmt = $pdo->query("SELECT name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt FROM authors GROUP BY name HAVING cnt > 1");
$dups = $stmt->fetchAll();
$merged = 0;

foreach ($dups as $dup) {
    $ids = explode(',', $dup['ids']);
    $keep = $ids[0]; // Mantener el primer ID
    
    for ($i = 1; $i < count($ids); $i++) {
        // Reasignar libros
        $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
            ->execute([$keep, $ids[$i]]);
        // Eliminar duplicado
        $pdo->prepare("DELETE FROM authors WHERE id = ?")->execute([$ids[$i]]);
        echo "  DUPLICADO: '{$dup['name']}' ID:{$ids[$i]} -> fusionado con ID:$keep\n";
        $merged++;
    }
}

echo "\nDuplicados fusionados: $merged\n\n";

// ============================================
// PASO 5: Mostrar autores que aún podrían tener problemas
// ============================================
echo "--- VERIFICACIÓN FINAL ---\n";
$stmt = $pdo->query("SELECT id, name FROM authors WHERE name REGEXP '[^ -~áéíóúñÁÉÍÓÚÑüÜçÇàèìòùÀÈÌÒÙâêîôûÂÊÎÔÛäëïöÄËÏÖãõÃÕ]' OR name LIKE '%##%' OR name LIKE '%/et al/%' OR name LIKE '%1' ORDER BY name LIMIT 30");
$suspicious = $stmt->fetchAll();

if (count($suspicious) > 0) {
    echo "Autores con posibles problemas restantes:\n";
    foreach ($suspicious as $s) {
        echo "  [{$s['id']}] {$s['name']}\n";
    }
} else {
    echo "No se encontraron autores con caracteres sospechosos.\n";
}

echo "\n=== PROCESO COMPLETADO ===\n";
echo "</pre>";
?>
