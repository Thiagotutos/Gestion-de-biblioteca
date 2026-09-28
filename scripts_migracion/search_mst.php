<?php
$mst_file = __DIR__ . '/bibliotecla/libros.mst';
$data = file_get_contents($mst_file);
$pos = stripos($data, 'Historia de Entre R');
if ($pos !== false) {
    echo "Found at $pos\n";
    $context = substr($data, max(0, $pos - 100), 200);
    echo preg_replace('/[\x00-\x1F\x80-\xFF]/', '.', $context);
} else {
    echo "Not found\n";
}
