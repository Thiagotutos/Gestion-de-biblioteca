<?php
/**
 * fix_question_marks_v3.php 
 * Tercera pasada - usa heurísticas inteligentes para resolver los ???? restantes.
 * Analiza el contexto alrededor de ???? para determinar qué acento va.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

echo "<pre>";
echo "=== CORRECCIÓN DE ???? v3 (heurística) ===\n\n";

/**
 * Intenta reemplazar todas las ocurrencias de ???? en un string
 * usando heurísticas basadas en patrones del español.
 */
function fix_qmarks($str) {
    // Primero intentar patrones específicos conocidos
    $specific = [
        'M????rega' => 'Mérega', 'Rouqui????' => 'Rouquié', 'Hayde????' => 'Haydée',
        'Halper????n' => 'Halperín', 'Zuvir????a' => 'Zuviría', 'Cort????s' => 'Cortés',
        'L????a' => 'Lía', 'Cers????simo' => 'Cersósimo', 'Le????n' => 'León',
        'M????rmol' => 'Mármol', 'Can????' => 'Cané', 'V????lez' => 'Vélez',
        'S????bato' => 'Sábato', 'Si????n' => 'sión', 'ci????n' => 'ción',
        'J????uregui' => 'Jáuregui', 'J????regui' => 'Jáuregui',
        'Cers??????simo' => 'Cersósimo', 'Le??????n' => 'León',
        'Palper??????n' => 'Halperín',
        'ver??????n' => 'verón', 'Ver??????n' => 'Verón',
        'Obiols' => 'Obiols',
        'B????rte' => 'Bérte',  'Bert????' => 'Berté',
        'Noe????' => 'Noé',
        'Le????nard' => 'Léonard',
        'Fran????ois' => 'François', 'Fran??????ois' => 'François',
        'G????rard' => 'Gérard',
        'Genevi????ve' => 'Geneviève', 'Genevi??????ve' => 'Geneviève',
        'Moli????re' => 'Molière',
        'Ren????' => 'René', 'Ren??????' => 'René',
        'V????ctor' => 'Víctor', 'Th????non' => 'Thénon',
        'B????guet' => 'Béguet',
        'D????Imperio' => "D'Imperio",
        'D????Arielle' => "D'Arielle",
        'Doczi' => 'Doczi', 'D????czi' => 'Dóczi',
        'Ag????ero' => 'Agüero', 'Ag??????ero' => 'Agüero',
        'G????iraldes' => 'Güiraldes', 'Gu????iraldes' => 'Güiraldes',
        'G??????iraldes' => 'Güiraldes', 'Gu??????iraldes' => 'Güiraldes',
        'Lin????ística' => 'Lingüística',
        'Mosst????n' => 'Mosstón',
        'Fern????ndez' => 'Fernández',
        'Hern????ndez' => 'Hernández',
        'Gonz????lez' => 'González',
        'Rodr????guez' => 'Rodríguez',
        'Mart????nez' => 'Martínez',
        'L????pez' => 'López',
        'Garc????a' => 'García',
        'G????mez' => 'Gómez',
        'D????az' => 'Díaz',
        'P????rez' => 'Pérez',
        'S????nchez' => 'Sánchez',
        'Ram????rez' => 'Ramírez',
        'Jim????nez' => 'Jiménez',
        'Dom????nguez' => 'Domínguez',
        'V????zquez' => 'Vázquez',
        'Mar????a' => 'María',
        'H????ctor' => 'Héctor',
        'C????sar' => 'César',
        'In????s' => 'Inés',
        'Mart????n' => 'Martín',
        'Jos????' => 'José',
        'Ram????n' => 'Ramón',
        'Adri????n' => 'Adrián',
        'Juli????n' => 'Julián',
        'Fabi????n' => 'Fabián',
        'Iv????n' => 'Iván',
        'Hern????n' => 'Hernán',
        'Rub????n' => 'Rubén',
        'Dar????o' => 'Darío',
        'Tom????s' => 'Tomás',
        'Andr????s' => 'Andrés',
        'Nicol????s' => 'Nicolás',
        'F????lix' => 'Félix',
        'N????stor' => 'Néstor',
        'An????bal' => 'Aníbal',
        'Mois????s' => 'Moisés',
        '????ngel' => 'Ángel', '????ngela' => 'Ángela',
        'M????rquez' => 'Márquez',
        'Cort????zar' => 'Cortázar',
        'Su????rez' => 'Suárez',
        'R????os' => 'Ríos', 'R????o' => 'Río',
        'C????mara' => 'Cámara',
        'Ra????l' => 'Raúl',
        'Am????rica' => 'América',
        'M????xico' => 'México',
        'Bol????var' => 'Bolívar',
        'Matem????tica' => 'Matemática',
        'Did????ctica' => 'Didáctica',
        'P????blica' => 'Pública',
        'Hist????rica' => 'Histórica',
        'Ant????gona' => 'Antígona',
        'pol????tica' => 'política',
        'Pol????tica' => 'Política',
        'econ????mica' => 'económica',
        'Econ????mica' => 'Económica',
        
        // ??????  patterns (6 question marks)
        'Ra??????l' => 'Raúl', 'ra??????l' => 'raúl',
        'Mu??????oz' => 'Muñoz', 'mu??????oz' => 'muñoz',
        'Nu??????ez' => 'Núñez', 'nu??????ez' => 'núñez',
        'Pi??????eiro' => 'Piñeiro', 'Pi??????eyro' => 'Piñeyro',
        'Espa??????a' => 'España',
        'Pe??????a' => 'Peña', 'pe??????a' => 'peña',
        'an??????o' => 'año', 'An??????o' => 'Año',
        'Nin??????o' => 'Niño',
        'Disen??????o' => 'Diseño', 'disen??????o' => 'diseño',
    ];
    
    $result = $str;
    foreach ($specific as $wrong => $correct) {
        if (strpos($result, $wrong) !== false) {
            $result = str_replace($wrong, $correct, $result);
        }
    }
    
    // If still has ????, try generic approach:
    // ???? before a consonant often = é or á
    // ???? after a consonant before 'a' = í
    // etc.
    // For now, just replace remaining ???? with the most likely accent
    if (strpos($result, '????') !== false) {
        // Try to figure out from context
        // Pattern: consonant + ???? + vowel = the ???? is likely an accented vowel
        // Check what comes after ????
        $result = preg_replace_callback('/(\w?)(\?{4,6})(\w?)/', function($m) {
            $before = $m[1];
            $qlen = strlen($m[2]);
            $after = $m[3];
            
            // If ???? is at the end of a word (nothing after) -> likely é
            if ($after === '') return $before . 'é';
            
            // If ???? is before 'a' -> likely í (María, García)
            if (strtolower($after) === 'a') return $before . 'í' . $after;
            
            // If ???? is before 'n' -> likely ó or á (Ramón, Adrián, Sebastián)
            if (strtolower($after) === 'n') return $before . 'á' . $after;
            
            // If ???? is before 's' -> likely é (Andrés, Inés, Moisés)
            if (strtolower($after) === 's') return $before . 'é' . $after;
            
            // If 6 question marks -> likely ñ or ü
            if ($qlen >= 6) {
                if (strtolower($after) === 'o') return $before . 'ñ' . $after;
                if (strtolower($after) === 'a') return $before . 'ñ' . $after;
                if (strtolower($after) === 'e') return $before . 'ñ' . $after;
                if (strtolower($after) === 'i') return $before . 'ü' . $after;
            }
            
            // Default: likely á
            return $before . 'á' . $after;
        }, $result);
    }
    
    return $result;
}

// Process authors
echo "--- Corrigiendo autores ---\n";
$fixed_a = 0;
$stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%????%' OR name LIKE '%??????%'");
$items = $stmt->fetchAll();
echo "Encontrados: " . count($items) . "\n\n";

foreach ($items as $a) {
    $orig = $a['name'];
    $fix = fix_qmarks($orig);
    if ($fix !== $orig && $fix !== '') {
        $ch = $pdo->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
        $ch->execute([$fix, $a['id']]);
        $ex = $ch->fetch();
        if ($ex) {
            $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")->execute([$ex['id'], $a['id']]);
            $pdo->prepare("DELETE FROM authors WHERE id = ?")->execute([$a['id']]);
            echo "  FUSIONADO: '$orig' -> '$fix'\n";
        } else {
            $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")->execute([$fix, $a['id']]);
            echo "  CORREGIDO: '$orig' -> '$fix'\n";
        }
        $fixed_a++;
    } else {
        echo "  [?] '$orig' (ID:{$a['id']})\n";
    }
}
echo "\nAutores: $fixed_a\n\n";

// Process books
echo "--- Corrigiendo títulos ---\n";
$fixed_b = 0;
$stmt = $pdo->query("SELECT id, titulo FROM books WHERE titulo LIKE '%????%' OR titulo LIKE '%??????%'");
$items = $stmt->fetchAll();
echo "Encontrados: " . count($items) . "\n\n";

foreach ($items as $b) {
    $orig = $b['titulo'];
    $fix = fix_qmarks($orig);
    if ($fix !== $orig) {
        $pdo->prepare("UPDATE books SET titulo = ? WHERE id = ?")->execute([$fix, $b['id']]);
        $fixed_b++;
    }
}
echo "Títulos: $fixed_b\n\n";

// Process publishers
echo "--- Corrigiendo editoriales ---\n";
$fixed_p = 0;
$stmt = $pdo->query("SELECT id, name FROM publishers WHERE name LIKE '%????%' OR name LIKE '%??????%'");
$items = $stmt->fetchAll();
foreach ($items as $p) {
    $orig = $p['name'];
    $fix = fix_qmarks($orig);
    if ($fix !== $orig && $fix !== '') {
        $ch = $pdo->prepare("SELECT id FROM publishers WHERE name = ? AND id != ?");
        $ch->execute([$fix, $p['id']]);
        $ex = $ch->fetch();
        if ($ex) {
            $pdo->prepare("UPDATE books SET publisher_id = ? WHERE publisher_id = ?")->execute([$ex['id'], $p['id']]);
            $pdo->prepare("DELETE FROM publishers WHERE id = ?")->execute([$p['id']]);
        } else {
            $pdo->prepare("UPDATE publishers SET name = ? WHERE id = ?")->execute([$fix, $p['id']]);
        }
        $fixed_p++;
    }
}
echo "Editoriales: $fixed_p\n\n";

// Merge duplicates
echo "--- Fusionando duplicados ---\n";
$merged = 0;
$stmt = $pdo->query("SELECT name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt FROM authors GROUP BY name HAVING cnt > 1");
foreach ($stmt->fetchAll() as $d) {
    $ids = explode(',', $d['ids']); $k = $ids[0];
    for ($i = 1; $i < count($ids); $i++) {
        $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")->execute([$k, $ids[$i]]);
        $pdo->prepare("DELETE FROM authors WHERE id = ?")->execute([$ids[$i]]);
        echo "  DUP: '{$d['name']}' ID:{$ids[$i]} -> ID:$k\n";
        $merged++;
    }
}
echo "Duplicados: $merged\n\n";

// Summary
$rem = $pdo->query("SELECT COUNT(*) FROM authors WHERE name LIKE '%????%'")->fetchColumn();
echo "=== RESUMEN ===\n";
echo "Autores: $fixed_a | Títulos: $fixed_b | Editoriales: $fixed_p | Fusionados: $merged\n";
echo "Restantes con ????: $rem\n";
echo "Total autores: " . $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn() . "\n";
echo "Total libros: " . $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() . "\n";

if ($rem > 0) {
    echo "\n--- Restantes ---\n";
    $stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%????%' LIMIT 20");
    foreach ($stmt->fetchAll() as $a) { echo "  [{$a['id']}] {$a['name']}\n"; }
}
echo "\n=== COMPLETADO ===\n</pre>";
?>
