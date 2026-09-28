<?php
/**
 * fix_question_marks.php
 * Corrige nombres de autores que tienen ???? donde deberían tener acentos.
 * Estos fueron generados por un fix anterior que reemplazó bytes inválidos con '?'.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

echo "<pre>";
echo "=== CORRECCIÓN DE SIGNOS DE INTERROGACIÓN ===\n\n";

// Mapeo de patrones con ???? a sus versiones correctas
// El ???? reemplaza un carácter acentuado que fue corrompido
$replacements = [
    // Vocales acentuadas
    'Mar????a' => 'María',
    'mar????a' => 'maría',
    'H????ctor' => 'Héctor',
    'h????ctor' => 'héctor',
    'C????sar' => 'César',
    'c????sar' => 'césar',
    'In????s' => 'Inés',
    'in????s' => 'inés',
    'Mart????n' => 'Martín',
    'mart????n' => 'martín',
    'Jer????nimo' => 'Jerónimo',
    'N????stor' => 'Néstor',
    'n????stor' => 'néstor',
    'Tom????s' => 'Tomás',
    'tom????s' => 'tomás',
    'Andr????s' => 'Andrés',
    'andr????s' => 'andrés',
    'Jos????' => 'José',
    'jos????' => 'josé',
    'Josu????' => 'Josué',
    'Jes????s' => 'Jesús',
    'jes????s' => 'jesús',
    'Mois????s' => 'Moisés',
    'Ram????n' => 'Ramón',
    'ram????n' => 'ramón',
    'Sim????n' => 'Simón',
    'Germ????n' => 'Germán',
    'germ????n' => 'germán',
    'Sebasti????n' => 'Sebastián',
    'sebasti????n' => 'sebastián',
    'Adri????n' => 'Adrián',
    'adri????n' => 'adrián',
    'Juli????n' => 'Julián',
    'juli????n' => 'julián',
    'Fabi????n' => 'Fabián',
    'fabi????n' => 'fabián',
    'Iv????n' => 'Iván',
    'iv????n' => 'iván',
    'Hern????n' => 'Hernán',
    'hern????n' => 'hernán',
    'Rub????n' => 'Rubén',
    'rub????n' => 'rubén',
    'V????ctor' => 'Víctor',
    'v????ctor' => 'víctor',
    'Dar????o' => 'Darío',
    'dar????o' => 'darío',
    'Mar????o' => 'Mario',
    'Julio' => 'Julio', // no tiene acento
    'Ra????l' => 'Raúl',
    'ra????l' => 'raúl',
    'Sa????l' => 'Saúl',
    'sa????l' => 'saúl',
    'An????bal' => 'Aníbal',
    'An????lisis' => 'Análisis',
    'an????lisis' => 'análisis',
    'Cr????nica' => 'Crónica',
    'cr????nica' => 'crónica',
    '????ngel' => 'Ángel',
    '????ngela' => 'Ángela',
    '????tica' => 'Ética',
    '????tica' => 'ética',
    'F????lix' => 'Félix',
    'f????lix' => 'félix',
    'Nicol????s' => 'Nicolás',
    'nicol????s' => 'nicolás',
    'L????pez' => 'López',
    'l????pez' => 'lópez',
    'Gonz????lez' => 'González',
    'gonz????lez' => 'gonzález',
    'Fern????ndez' => 'Fernández',
    'fern????ndez' => 'fernández',
    'Hern????ndez' => 'Hernández',
    'hern????ndez' => 'hernández',
    'Rodr????guez' => 'Rodríguez',
    'rodr????guez' => 'rodríguez',
    'Mart????nez' => 'Martínez',
    'mart????nez' => 'martínez',
    'G????mez' => 'Gómez',
    'g????mez' => 'gómez',
    'D????az' => 'Díaz',
    'd????az' => 'díaz',
    'P????rez' => 'Pérez',
    'p????rez' => 'pérez',
    'S????nchez' => 'Sánchez',
    's????nchez' => 'sánchez',
    'Ram????rez' => 'Ramírez',
    'ram????rez' => 'ramírez',
    'Jim????nez' => 'Jiménez',
    'jim????nez' => 'jiménez',
    'Dom????nguez' => 'Domínguez',
    'dom????nguez' => 'domínguez',
    'N????ez' => 'Núñez',
    'n????ez' => 'núñez',
    'Garc????a' => 'García',
    'garc????a' => 'garcía',
    'M????rquez' => 'Márquez',
    'm????rquez' => 'márquez',
    'B????cquer' => 'Bécquer',
    'Cort????zar' => 'Cortázar',
    'cort????zar' => 'cortázar',
    'Alvar????z' => 'Alvárez',
    'Su????rez' => 'Suárez',
    'su????rez' => 'suárez',
    'V????zquez' => 'Vázquez',
    'v????zquez' => 'vázquez',
    'Vel????zquez' => 'Velázquez',
    'Ant????gona' => 'Antígona',
    'Biol????gica' => 'Biológica',
    'biol????gica' => 'biológica',
    'P????blica' => 'Pública',
    'p????blica' => 'pública',
    'Rep????blica' => 'República',
    'Geogr????fica' => 'Geográfica',
    'Hist????rica' => 'Histórica',
    'Matem????tica' => 'Matemática',
    'matem????tica' => 'matemática',
    'Ling????????stica' => 'Lingüística',
    'Acad????mica' => 'Académica',
    'Pedag????gica' => 'Pedagógica',
    'pedag????gica' => 'pedagógica',
    'Ecol????gica' => 'Ecológica',
    'Did????ctica' => 'Didáctica',
    'did????ctica' => 'didáctica',
    'Mec????nica' => 'Mecánica',
    'Org????nica' => 'Orgánica',
    'Constituci??????n' => 'Constitución',
    'constituci??????n' => 'constitución',
    'Educaci??????n' => 'Educación',
    'educaci??????n' => 'educación',
    'Formaci??????n' => 'Formación',
    'formaci??????n' => 'formación',
    'Comunicaci??????n' => 'Comunicación',
    'comunicaci??????n' => 'comunicación',
    'Naci??????n' => 'Nación',
    'naci??????n' => 'nación',
    'Prevenci??????n' => 'Prevención',
    'Adicci??????n' => 'Adicción',
    'Fundaci??????n' => 'Fundación',
    'Certificaci??????n' => 'Certificación',
    'Producci??????n' => 'Producción',
    'R????os' => 'Ríos',
    'r????os' => 'ríos',
    'C????mara' => 'Cámara',
    'c????mara' => 'cámara',
    'Ag??????ero' => 'Agüero',
    'G??????emes' => 'Güemes',
    'Gu??????iraldes' => 'Güiraldes',
    'Le????n' => 'León',
    'le????n' => 'león',
    'Er????as' => 'Erías',
    'R????o' => 'Río',
    'r????o' => 'río',
    '????lgebra' => 'Álgebra',
    '????frica' => 'África',
    '????ltima' => 'Última',
    // Ñ con ????
    'Espa????a' => 'España',
    'espa????a' => 'españa',
    'ense????anza' => 'enseñanza',
    'Ense????anza' => 'Enseñanza',
    'Mu????oz' => 'Muñoz',
    'mu????oz' => 'muñoz',
    'Nu????ez' => 'Núñez',
    'Caba????as' => 'Cabañas',
    'ni????o' => 'niño',
    'Ni????o' => 'Niño',
    'a????o' => 'año',
    'A????o' => 'Año',
    'compa????ero' => 'compañero',
    'Compa????ero' => 'Compañero',
    'Ara????a' => 'Araña',
    'Pi????eiro' => 'Piñeiro',
    'pi????eiro' => 'piñeiro',
    'Pi????eyro' => 'Piñeyro',
    'dise????o' => 'diseño',
    'Dise????o' => 'Diseño',
    'Pe????a' => 'Peña',
    'pe????a' => 'peña',
    'Iba????ez' => 'Ibáñez',
    'iba????ez' => 'ibáñez',
    'Orde????ana' => 'Ordeñana',
    // ü con ????
    'G????iraldes' => 'Güiraldes',
    'Ling????ística' => 'Lingüística',
];

echo "--- Corrigiendo autores ---\n";
$fixed_authors = 0;

$stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%????%'");
$broken_authors = $stmt->fetchAll();

echo "Autores con ???? encontrados: " . count($broken_authors) . "\n\n";

foreach ($broken_authors as $a) {
    $original = $a['name'];
    $fixed = $original;
    
    // Aplicar cada reemplazo
    foreach ($replacements as $wrong => $correct) {
        if (strpos($fixed, $wrong) !== false) {
            $fixed = str_replace($wrong, $correct, $fixed);
        }
    }
    
    if ($fixed !== $original) {
        // Verificar si ya existe el autor con el nombre correcto
        $check = $pdo->prepare("SELECT id FROM authors WHERE name = ? AND id != ?");
        $check->execute([$fixed, $a['id']]);
        $existing = $check->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
                ->execute([$existing['id'], $a['id']]);
            $pdo->prepare("DELETE FROM authors WHERE id = ?")
                ->execute([$a['id']]);
            echo "  FUSIONADO: '$original' -> '$fixed' (con ID:{$existing['id']})\n";
        } else {
            $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")
                ->execute([$fixed, $a['id']]);
            echo "  CORREGIDO: '$original' -> '$fixed'\n";
        }
        $fixed_authors++;
    } else {
        echo "  [?] SIN PATRON: '$original' (ID:{$a['id']})\n";
    }
}

echo "\nAutores corregidos: $fixed_authors\n\n";

// ============================================
// PASO 2: Corregir títulos de libros
// ============================================
echo "--- Corrigiendo títulos de libros ---\n";
$fixed_titles = 0;

$stmt = $pdo->query("SELECT id, titulo FROM books WHERE titulo LIKE '%????%'");
$broken_titles = $stmt->fetchAll();

echo "Títulos con ???? encontrados: " . count($broken_titles) . "\n\n";

foreach ($broken_titles as $b) {
    $original = $b['titulo'];
    $fixed = $original;
    
    foreach ($replacements as $wrong => $correct) {
        if (strpos($fixed, $wrong) !== false) {
            $fixed = str_replace($wrong, $correct, $fixed);
        }
    }
    
    if ($fixed !== $original) {
        $pdo->prepare("UPDATE books SET titulo = ? WHERE id = ?")
            ->execute([$fixed, $b['id']]);
        echo "  CORREGIDO: '$original' -> '$fixed'\n";
        $fixed_titles++;
    } else {
        echo "  [?] SIN PATRON: '$original' (ID:{$b['id']})\n";
    }
}

echo "\nTítulos corregidos: $fixed_titles\n\n";

// ============================================
// PASO 3: Corregir editoriales
// ============================================
echo "--- Corrigiendo editoriales ---\n";
$fixed_pubs = 0;

$stmt = $pdo->query("SELECT id, name FROM publishers WHERE name LIKE '%????%'");
$broken_pubs = $stmt->fetchAll();

foreach ($broken_pubs as $p) {
    $original = $p['name'];
    $fixed = $original;
    
    foreach ($replacements as $wrong => $correct) {
        if (strpos($fixed, $wrong) !== false) {
            $fixed = str_replace($wrong, $correct, $fixed);
        }
    }
    
    if ($fixed !== $original) {
        $check = $pdo->prepare("SELECT id FROM publishers WHERE name = ? AND id != ?");
        $check->execute([$fixed, $p['id']]);
        $existing = $check->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE books SET publisher_id = ? WHERE publisher_id = ?")
                ->execute([$existing['id'], $p['id']]);
            $pdo->prepare("DELETE FROM publishers WHERE id = ?")
                ->execute([$p['id']]);
            echo "  FUSIONADO: '$original' -> '$fixed'\n";
        } else {
            $pdo->prepare("UPDATE publishers SET name = ? WHERE id = ?")
                ->execute([$fixed, $p['id']]);
            echo "  CORREGIDO: '$original' -> '$fixed'\n";
        }
        $fixed_pubs++;
    }
}

echo "\nEditoriales corregidas: $fixed_pubs\n\n";

// ============================================
// PASO 4: Fusionar duplicados
// ============================================
echo "--- Fusionando duplicados ---\n";
$merged = 0;

$stmt = $pdo->query("SELECT name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt FROM authors GROUP BY name HAVING cnt > 1");
$dups = $stmt->fetchAll();

foreach ($dups as $dup) {
    $ids = explode(',', $dup['ids']);
    $keep = $ids[0];
    for ($i = 1; $i < count($ids); $i++) {
        $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")
            ->execute([$keep, $ids[$i]]);
        $pdo->prepare("DELETE FROM authors WHERE id = ?")
            ->execute([$ids[$i]]);
        echo "  DUP: '{$dup['name']}' ID:{$ids[$i]} -> ID:$keep\n";
        $merged++;
    }
}

echo "\nDuplicados fusionados: $merged\n\n";

// ============================================
// RESUMEN
// ============================================
$remaining = $pdo->query("SELECT COUNT(*) FROM authors WHERE name LIKE '%????%'")->fetchColumn();
$total_a = $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn();
$total_b = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();

echo "=== RESUMEN ===\n";
echo "Autores corregidos: $fixed_authors\n";
echo "Títulos corregidos: $fixed_titles\n";
echo "Editoriales corregidas: $fixed_pubs\n";
echo "Duplicados fusionados: $merged\n";
echo "Autores restantes con ????: $remaining\n";
echo "Total autores: $total_a\n";
echo "Total libros: $total_b\n";

if ($remaining > 0) {
    echo "\n--- Autores aún con ???? ---\n";
    $stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%????%' ORDER BY name LIMIT 50");
    foreach ($stmt->fetchAll() as $a) {
        echo "  [{$a['id']}] {$a['name']}\n";
    }
}

echo "\n=== COMPLETADO ===\n";
echo "</pre>";
?>
