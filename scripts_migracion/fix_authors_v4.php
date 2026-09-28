<?php
/**
 * fix_authors_v4.php
 * Corrige nombres de autores específicos que están mal escritos.
 * - Limpia sufijos numéricos (ej: "Leonardo1" -> "Leonardo")
 * - Corrige nombres con /et al/ -> et al.
 * - Corrige nombres específicos mal escritos
 * - Fusiona duplicados resultantes
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

echo "<pre>";
echo "=== CORRECCIÓN DE AUTORES v4 ===\n\n";

// ============================================
// PASO 1: Correcciones específicas de nombres
// ============================================
$specific_fixes = [
    // Gogol - nombre correcto
    "Gogol, Nikolái" => "Gógol, Nikolái",
    
    // Nombres con 'José' que deberían ser 'Joseph'
    "Conrad, Joséph" => "Conrad, Joseph",
    "Kindle, Joséph H." => "Kindle, Joseph H.",
    "Stiglitz, Joséph E." => "Stiglitz, Joseph E.",
    "McEvoy, Joséph" => "McEvoy, Joseph",
    
    // 'Maríano' -> 'Mariano' (la i no lleva acento)
    "Iturriza, Maríana" => "Iturriza, Mariana",
    "Chinelli, Maríano, coord." => "Chinelli, Mariano, coord.",
    "Narodovski, Maríano" => "Narodovski, Mariano",
    "De Diego, Juan Maríano" => "De Diego, Juan Mariano",
    
    // 'Ral' -> 'Raúl'
    "González, Ral A" => "González, Raúl A",
    "González, Ral A." => "González, Raúl A.",
    
    // Conrad con J
    "Conrad, Joshep" => "Conrad, Joseph",
    
    // Puchkin -> Pushkin
    "Puchkin, Alejandro S." => "Pushkin, Alejandro S.",
    
    // Güiraldes con acento raro
    "Gûiraldes, Ricardo" => "Güiraldes, Ricardo",
    
    // Franz, Kafka -> Kafka, Franz
    "Franz, Kafka" => "Kafka, Franz",
    
    // Pisano con punto y coma
    "Pisano; Juan Pablo" => "Pisano, Juan Pablo",
    
    // Rodr????guez
    "Rodr????guez, Estela E./et al/" => "Rodríguez, Estela E. et al.",
];

echo "--- PASO 1: Correcciones específicas ---\n";
$fixed_specific = 0;

foreach ($specific_fixes as $wrong => $correct) {
    $stmt = $pdo->prepare("SELECT id FROM authors WHERE name = ?");
    $stmt->execute([$wrong]);
    $found = $stmt->fetchAll();
    
    if (count($found) > 0) {
        foreach ($found as $f) {
            // Verificar si ya existe el nombre correcto
            $check = $pdo->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
            $check->execute([$correct, $f['id']]);
            $existing = $check->fetch();
            
            if ($existing) {
                // Fusionar: reasignar libros y eliminar
                $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
                    ->execute([$existing['id'], $f['id']]);
                $pdo->prepare("DELETE FROM authors WHERE id = ?")
                    ->execute([$f['id']]);
                echo "  FUSIONADO: '$wrong' (ID:{$f['id']}) -> '$correct' (ID:{$existing['id']})\n";
            } else {
                $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")
                    ->execute([$correct, $f['id']]);
                echo "  CORREGIDO: '$wrong' -> '$correct' (ID:{$f['id']})\n";
            }
            $fixed_specific++;
        }
    }
}

echo "\nCorrecciones específicas: $fixed_specific\n\n";

// ============================================
// PASO 2: Limpiar sufijos numéricos (ej: "Leonardo1" -> "Leonardo")
// ============================================
echo "--- PASO 2: Limpiando sufijos numéricos ---\n";

$stmt = $pdo->query("SELECT id, name FROM authors WHERE name REGEXP '[0-9]+$'");
$with_numbers = $stmt->fetchAll();
$fixed_numbers = 0;

foreach ($with_numbers as $a) {
    $clean = preg_replace('/\d+$/', '', $a['name']);
    $clean = trim($clean);
    
    if ($clean !== '' && $clean !== $a['name']) {
        // Verificar duplicado
        $check = $pdo->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
        $check->execute([$clean, $a['id']]);
        $existing = $check->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
                ->execute([$existing['id'], $a['id']]);
            $pdo->prepare("DELETE FROM authors WHERE id = ?")
                ->execute([$a['id']]);
            echo "  FUSIONADO: '{$a['name']}' -> '$clean' (con ID:{$existing['id']})\n";
        } else {
            $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")
                ->execute([$clean, $a['id']]);
            echo "  LIMPIADO: '{$a['name']}' -> '$clean'\n";
        }
        $fixed_numbers++;
    }
}

echo "\nSufijos numéricos limpiados: $fixed_numbers\n\n";

// ============================================
// PASO 3: Limpiar /et al/ -> et al.
// ============================================
echo "--- PASO 3: Limpiando /et al/ ---\n";

$stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%/et al/%'");
$etal_items = $stmt->fetchAll();
$fixed_etal = 0;

foreach ($etal_items as $a) {
    $clean = str_replace(' /et al/', ' et al.', $a['name']);
    $clean = str_replace('/et al/', ' et al.', $clean);
    $clean = trim($clean);
    
    if ($clean !== $a['name']) {
        $check = $pdo->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
        $check->execute([$clean, $a['id']]);
        $existing = $check->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
                ->execute([$existing['id'], $a['id']]);
            $pdo->prepare("DELETE FROM authors WHERE id = ?")
                ->execute([$a['id']]);
            echo "  FUSIONADO: '{$a['name']}' -> '$clean'\n";
        } else {
            $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")
                ->execute([$clean, $a['id']]);
            echo "  LIMPIADO: '{$a['name']}' -> '$clean'\n";
        }
        $fixed_etal++;
    }
}

echo "\n/et al/ limpiados: $fixed_etal\n\n";

// ============================================
// PASO 4: Limpiar ## de editoriales
// ============================================
echo "--- PASO 4: Limpiando ## de editoriales ---\n";

$stmt = $pdo->query("SELECT id, name FROM publishers WHERE name LIKE '%##%'");
$hash_items = $stmt->fetchAll();
$fixed_hash = 0;

foreach ($hash_items as $p) {
    $clean = str_replace('##', '', $p['name']);
    $clean = trim($clean);
    
    if ($clean !== $p['name'] && $clean !== '') {
        $check = $pdo->prepare("SELECT id FROM publishers WHERE name = ? AND id != ?");
        $check->execute([$clean, $p['id']]);
        $existing = $check->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE books SET publisher_id = ? WHERE publisher_id = ?")
                ->execute([$existing['id'], $p['id']]);
            $pdo->prepare("DELETE FROM publishers WHERE id = ?")
                ->execute([$p['id']]);
            echo "  FUSIONADO: '{$p['name']}' -> '$clean'\n";
        } else {
            $pdo->prepare("UPDATE publishers SET name = ? WHERE id = ?")
                ->execute([$clean, $p['id']]);
            echo "  LIMPIADO: '{$p['name']}' -> '$clean'\n";
        }
        $fixed_hash++;
    }
}

echo "\n## limpiados: $fixed_hash\n\n";

// ============================================
// PASO 5: Fusionar duplicados finales
// ============================================
echo "--- PASO 5: Fusionando duplicados ---\n";

$stmt = $pdo->query("SELECT name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt FROM authors GROUP BY name HAVING cnt > 1");
$dups = $stmt->fetchAll();
$merged = 0;

foreach ($dups as $dup) {
    $ids = explode(',', $dup['ids']);
    $keep = $ids[0];
    
    for ($i = 1; $i < count($ids); $i++) {
        $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
            ->execute([$keep, $ids[$i]]);
        $pdo->prepare("DELETE FROM authors WHERE id = ?")
            ->execute([$ids[$i]]);
        echo "  DUPLICADO: '{$dup['name']}' ID:{$ids[$i]} -> fusionado con ID:$keep\n";
        $merged++;
    }
}

echo "\nDuplicados fusionados: $merged\n\n";

// ============================================
// RESUMEN FINAL
// ============================================
echo "=== RESUMEN ===\n";
echo "Correcciones específicas: $fixed_specific\n";
echo "Sufijos numéricos: $fixed_numbers\n";
echo "/et al/ limpiados: $fixed_etal\n";
echo "## limpiados: $fixed_hash\n";
echo "Duplicados fusionados: $merged\n";

$total_authors = $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn();
$total_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
echo "\nTotal autores en BD: $total_authors\n";
echo "Total libros en BD: $total_books\n";

echo "\n=== PROCESO COMPLETADO ===\n";
echo "</pre>";
?>
