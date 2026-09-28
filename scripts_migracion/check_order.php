<?php
require_once 'config.php';

$mst_file = __DIR__ . '/bibliotecla/libros.mst';
$data = file_get_contents($mst_file);

// Extract all inventory numbers and titles in order from MST
preg_match_all('/#\d*\^a([^#\^]+).*?\^a(\d{6})\^[hcm]/s', $data, $matches, PREG_SET_ORDER);
// Wait, regex might skip some. Let's do it simply
preg_match_all('/\^a(\d{6})\^[hcm]/', $data, $inv_matches, PREG_OFFSET_CAPTURE);

$mst_records = [];
foreach ($inv_matches[1] as $match) {
    $inv_num = $match[0];
    $offset = $match[1];
    $start = max(0, $offset - 1000);
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
    $mst_records[] = [
        'inv' => $inv_num,
        'title' => mb_substr($title, 0, 50)
    ];
}

$stmt = $pdo->query("SELECT id, titulo FROM books WHERE id > 12 ORDER BY id ASC LIMIT 50");
$db_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "MST Records (first 20):\n";
for($i=0; $i<20; $i++) {
    echo $i . ": INV=" . $mst_records[$i]['inv'] . " TITLE=" . $mst_records[$i]['title'] . "\n";
}

echo "\nDB Books (first 20):\n";
for($i=0; $i<20; $i++) {
    echo $i . ": ID=" . $db_books[$i]['id'] . " TITLE=" . mb_substr($db_books[$i]['titulo'], 0, 50) . "\n";
}

