<?php
$mst_file = __DIR__ . '/bibliotecla/libros.mst';
$data = file_get_contents($mst_file);

// Find first few titles in MST
preg_match_all('/#(?:10|1#|0#)\^a([^#\^]{5,50})/', $data, $matches);
print_r(array_slice($matches[1], 0, 10));
