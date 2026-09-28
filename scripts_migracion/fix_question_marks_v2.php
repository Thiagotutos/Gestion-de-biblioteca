<?php
/**
 * fix_question_marks_v2.php
 * Segunda pasada para corregir los ???? restantes.
 * Maneja patrones con diferente cantidad de ? (????, ??????, etc.)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

echo "<pre>";
echo "=== CORRECCIÓN DE ???? v2 ===\n\n";

// Patrones con diferente cantidad de signos de interrogación
$replacements = [
    // 6 question marks (??????) - typically ú, ñ, ü
    'Ra??????l' => 'Raúl',
    'ra??????l' => 'raúl',
    'Sa??????l' => 'Saúl',
    'Mu??????oz' => 'Muñoz',
    'mu??????oz' => 'muñoz',
    'Nu??????ez' => 'Núñez',
    'nu??????ez' => 'núñez',
    'an??????o' => 'año',
    'An??????o' => 'Año',
    'Pi??????eiro' => 'Piñeiro',
    'pi??????eiro' => 'piñeiro',
    'Pi??????eyro' => 'Piñeyro',
    'Espa??????a' => 'España',
    'espa??????a' => 'españa',
    'ensen??????anza' => 'enseñanza',
    'Ensen??????anza' => 'Enseñanza',
    'Nin??????o' => 'Niño',
    'nin??????o' => 'niño',
    'Nin??????a' => 'Niña',
    'Pe??????a' => 'Peña',
    'pe??????a' => 'peña',
    'Iba??????ez' => 'Ibáñez',
    'Disen??????o' => 'Diseño',
    'disen??????o' => 'diseño',
    'Compa??????ero' => 'Compañero',
    'compa??????ero' => 'compañero',
    'an??????os' => 'años',
    'Caban??????as' => 'Cabañas',
    'caban??????as' => 'cabañas',
    'Arag??????n' => 'Aragón',
    'N??????ñez' => 'Núñez',
    'Gui??????aldes' => 'Güiraldes',
    'Agu??????ero' => 'Agüero',
    'agu??????ero' => 'agüero',
    'Gu??????emes' => 'Güemes',
    'Lin??????ística' => 'Lingüística',
    
    // 4 question marks (????) - typically á, é, í, ó
    'Gonz????lez' => 'González',
    'gonz????lez' => 'gonzález',
    'L????pez' => 'López',
    'l????pez' => 'lópez',
    'Garc????a' => 'García',
    'garc????a' => 'garcía',
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
    'V????zquez' => 'Vázquez',
    'v????zquez' => 'vázquez',
    'Mar????a' => 'María',
    'mar????a' => 'maría',
    'Mar????n' => 'Marín',
    'H????ctor' => 'Héctor',
    'h????ctor' => 'héctor',
    'C????sar' => 'César',
    'c????sar' => 'césar',
    'In????s' => 'Inés',
    'in????s' => 'inés',
    'Mart????n' => 'Martín',
    'mart????n' => 'martín',
    'Jos????' => 'José',
    'jos????' => 'josé',
    'Jes????s' => 'Jesús',
    'jes????s' => 'jesús',
    'Ram????n' => 'Ramón',
    'ram????n' => 'ramón',
    'Sim????n' => 'Simón',
    'Germ????n' => 'Germán',
    'germ????n' => 'germán',
    'Sebasti????n' => 'Sebastián',
    'Adri????n' => 'Adrián',
    'adri????n' => 'adrián',
    'Juli????n' => 'Julián',
    'Fabi????n' => 'Fabián',
    'Iv????n' => 'Iván',
    'Hern????n' => 'Hernán',
    'Rub????n' => 'Rubén',
    'rub????n' => 'rubén',
    'V????ctor' => 'Víctor',
    'v????ctor' => 'víctor',
    'Dar????o' => 'Darío',
    'dar????o' => 'darío',
    'Tom????s' => 'Tomás',
    'tom????s' => 'tomás',
    'Andr????s' => 'Andrés',
    'andr????s' => 'andrés',
    'Nicol????s' => 'Nicolás',
    'nicol????s' => 'nicolás',
    'F????lix' => 'Félix',
    'f????lix' => 'félix',
    'Le????n' => 'León',
    'le????n' => 'león',
    'N????stor' => 'Néstor',
    'An????bal' => 'Aníbal',
    'Jer????nimo' => 'Jerónimo',
    'Mois????s' => 'Moisés',
    '????ngel' => 'Ángel',
    '????ngela' => 'Ángela',
    '????tica' => 'Ética',
    '????lgebra' => 'Álgebra',
    '????frica' => 'África',
    '????ltima' => 'Última',
    'M????rquez' => 'Márquez',
    'B????cquer' => 'Bécquer',
    'Cort????zar' => 'Cortázar',
    'Su????rez' => 'Suárez',
    'su????rez' => 'suárez',
    'Vel????zquez' => 'Velázquez',
    'R????os' => 'Ríos',
    'r????os' => 'ríos',
    'R????o' => 'Río',
    'r????o' => 'río',
    'C????mara' => 'Cámara',
    'G????ndara' => 'Gándara',
    'Cr????nica' => 'Crónica',
    'cr????nica' => 'crónica',
    'An????lisis' => 'Análisis',
    'M????dica' => 'Médica',
    'M????xico' => 'México',
    'm????xico' => 'méxico',
    'Am????rica' => 'América',
    'am????rica' => 'américa',
    'Jer????nimo' => 'Jerónimo',
    'Bol????var' => 'Bolívar',
    'Ag????ero' => 'Agüero',
    'Matem????tica' => 'Matemática',
    'Did????ctica' => 'Didáctica',
    'P????blica' => 'Pública',
    'Biol????gica' => 'Biológica',
    'Hist????rica' => 'Histórica',
    'Ecol????gica' => 'Ecológica',
    'Pedag????gica' => 'Pedagógica',
    'Ant????gona' => 'Antígona',
    'pol????tica' => 'política',
    'Pol????tica' => 'Política',
    'econ????mica' => 'económica',
    'Econ????mica' => 'Económica',
    'gen????tica' => 'genética',
    'Gen????tica' => 'Genética',
    'Constituci????n' => 'Constitución',
    'constituci????n' => 'constitución',
    'Educaci????n' => 'Educación',
    'educaci????n' => 'educación',
    'Formaci????n' => 'Formación',
    'formaci????n' => 'formación',
    'Comunicaci????n' => 'Comunicación',
    'comunicaci????n' => 'comunicación',
    'Naci????n' => 'Nación',
    'naci????n' => 'nación',
    'evaluaci????n' => 'evaluación',
    'Evaluaci????n' => 'Evaluación',
    'investigaci????n' => 'investigación',
    'Investigaci????n' => 'Investigación',
    'Prevenci????n' => 'Prevención',
    'Adicci????n' => 'Adicción',
    'Fundaci????n' => 'Fundación',
    'Producci????n' => 'Producción',
    'Certificaci????n' => 'Certificación',
    'direcci????n' => 'dirección',
    'Direcci????n' => 'Dirección',
    'colecci????n' => 'colección',
    'Colecci????n' => 'Colección',
    'selecci????n' => 'selección',
    'Selecci????n' => 'Selección',
    'poblaci????n' => 'población',
    'Poblaci????n' => 'Población',
    'informaci????n' => 'información',
    'Informaci????n' => 'Información',
    'organizaci????n' => 'organización',
    'Organizaci????n' => 'Organización',
    'construcci????n' => 'construcción',
    'Construcci????n' => 'Construcción',
    // -ión pattern
    'ci????n' => 'ción',
    'si????n' => 'sión',
    // -ín pattern  
    'Benja????n' => 'Benjamín',
    'Joaqu????n' => 'Joaquín',
    'joaqu????n' => 'joaquín',
    // Cont with extra
    'Cont????nua' => 'Contínua',
    'cont????nua' => 'contínua',
];

// Process authors
echo "--- Corrigiendo autores ---\n";
$fixed_a = 0;
$stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%????%' OR name LIKE '%??????%'");
$items = $stmt->fetchAll();
echo "Encontrados: " . count($items) . "\n\n";

foreach ($items as $a) {
    $orig = $a['name'];
    $fix = $orig;
    foreach ($replacements as $w => $c) {
        if (strpos($fix, $w) !== false) { $fix = str_replace($w, $c, $fix); }
    }
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
        echo "  [?] NO MATCH: '$orig' (ID:{$a['id']})\n";
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
    $fix = $orig;
    foreach ($replacements as $w => $c) {
        if (strpos($fix, $w) !== false) { $fix = str_replace($w, $c, $fix); }
    }
    if ($fix !== $orig) {
        $pdo->prepare("UPDATE books SET titulo = ? WHERE id = ?")->execute([$fix, $b['id']]);
        echo "  CORREGIDO: '$orig' -> '$fix'\n";
        $fixed_b++;
    } else {
        echo "  [?] NO MATCH: '$orig' (ID:{$b['id']})\n";
    }
}
echo "\nTítulos: $fixed_b\n\n";

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
echo "\nDuplicados: $merged\n\n";

// Summary
$rem = $pdo->query("SELECT COUNT(*) FROM authors WHERE name LIKE '%????%'")->fetchColumn();
echo "=== RESUMEN ===\n";
echo "Autores corregidos: $fixed_a | Títulos: $fixed_b | Fusionados: $merged\n";
echo "Restantes con ????: $rem\n";
echo "Total autores: " . $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn() . "\n";
echo "Total libros: " . $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() . "\n";

if ($rem > 0) {
    echo "\n--- Restantes ---\n";
    $stmt = $pdo->query("SELECT id, name FROM authors WHERE name LIKE '%????%' LIMIT 30");
    foreach ($stmt->fetchAll() as $a) { echo "  [{$a['id']}] {$a['name']}\n"; }
}
echo "\n=== COMPLETADO ===\n</pre>";
?>
