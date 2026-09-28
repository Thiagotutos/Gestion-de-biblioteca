<?php
require_once 'config.php';

echo "=== FIX ENCODING v2 ===\n\n";

// The characters shown as broken are CP850-encoded bytes stored in a UTF-8 column
// For example: "├®" is the UTF-8 representation of bytes 0xC3 0xAE which is "î" in UTF-8
// But the original intent was "é" (0xE9 in Latin-1/CP850)
// This means the data was CP850, got interpreted as UTF-8 incorrectly

function fix_cp850_to_utf8($str) {
    // Check if string contains typical CP850-misencoded patterns
    $original = $str;
    
    // Try to detect CP850 bytes stored as-is in a UTF-8 field
    // These are single bytes 0x80-0xFF that should be interpreted as CP850
    $has_bad_chars = false;
    for ($i = 0; $i < strlen($str); $i++) {
        $byte = ord($str[$i]);
        // If we find high bytes that aren't valid UTF-8 sequences, it's likely CP850
        if ($byte >= 0x80 && $byte <= 0xBF) {
            // This is a continuation byte without a proper start - might be CP850
            $has_bad_chars = true;
            break;
        }
    }
    
    // Method 1: If string contains bytes that look like raw CP850
    if ($has_bad_chars || !mb_check_encoding($str, 'UTF-8')) {
        $converted = @iconv('CP850', 'UTF-8//IGNORE', $str);
        if ($converted !== false && strlen($converted) > 0) {
            return $converted;
        }
    }
    
    // Method 2: If string contains wrongly-encoded sequences
    // e.g., the UTF-8 encoding of CP850 characters
    // CP850 0xE9 (é) stored as single byte in a UTF-8 context shows as replacement
    $cp850_map = [
        "\xC3\xA1" => "á", "\xC3\xA9" => "é", "\xC3\xAD" => "í", 
        "\xC3\xB3" => "ó", "\xC3\xBA" => "ú", "\xC3\xB1" => "ñ",
        "\xC3\xBC" => "ü", "\xC3\x81" => "Á", "\xC3\x89" => "É",
        "\xC3\x8D" => "Í", "\xC3\x93" => "Ó", "\xC3\x9A" => "Ú",
        "\xC3\x91" => "Ñ", "\xC3\x9C" => "Ü",
    ];
    // These are already valid UTF-8 for those characters, so that's fine.
    
    // The real problem: CP850 byte like 0x82 (é in CP850) stored raw
    // Let's try to convert byte-by-byte
    $result = '';
    $len = strlen($str);
    $i = 0;
    $modified = false;
    while ($i < $len) {
        $byte = ord($str[$i]);
        
        if ($byte < 0x80) {
            // ASCII - fine
            $result .= $str[$i];
            $i++;
        } elseif (($byte & 0xE0) == 0xC0) {
            // 2-byte UTF-8 sequence
            if ($i + 1 < $len) {
                $result .= $str[$i] . $str[$i+1];
                $i += 2;
            } else {
                $result .= '?';
                $i++;
                $modified = true;
            }
        } elseif (($byte & 0xF0) == 0xE0) {
            // 3-byte UTF-8 sequence
            if ($i + 2 < $len) {
                $result .= $str[$i] . $str[$i+1] . $str[$i+2];
                $i += 3;
            } else {
                $result .= '?';
                $i++;
                $modified = true;
            }
        } elseif ($byte >= 0x80) {
            // Single high byte - likely CP850
            $cp850_char = chr($byte);
            $utf8_char = @iconv('CP850', 'UTF-8//IGNORE', $cp850_char);
            if ($utf8_char !== false && strlen($utf8_char) > 0) {
                $result .= $utf8_char;
                $modified = true;
            } else {
                $result .= '?';
                $modified = true;
            }
            $i++;
        } else {
            $result .= $str[$i];
            $i++;
        }
    }
    
    if ($modified) {
        return $result;
    }
    
    return $str;
}

// Fix all authors
$stmt = $pdo->query("SELECT id, name FROM authors");
$authors = $stmt->fetchAll();
$fixed = 0;
foreach ($authors as $a) {
    $original = $a['name'];
    $new_name = fix_cp850_to_utf8($original);
    
    // Also clean up trailing artifacts
    $new_name = preg_replace('/\d+$/', '', $new_name);
    $new_name = rtrim($new_name, '#');
    $new_name = str_replace(' /et al/', ' et al.', $new_name);
    $new_name = trim($new_name);
    
    if ($new_name !== $original && $new_name !== '') {
        $pdo->prepare("UPDATE authors SET name = ? WHERE id = ?")->execute([$new_name, $a['id']]);
        echo "  FIXED: '$original' -> '$new_name'\n";
        $fixed++;
    }
}
echo "Fixed $fixed authors.\n\n";

// Fix all book titles
$stmt = $pdo->query("SELECT id, titulo FROM books");
$books = $stmt->fetchAll();
$fixed_books = 0;
foreach ($books as $b) {
    $original = $b['titulo'];
    $new_title = fix_cp850_to_utf8($original);
    $new_title = rtrim($new_title, '#');
    $new_title = trim($new_title);
    
    if ($new_title !== $original && $new_title !== '') {
        $pdo->prepare("UPDATE books SET titulo = ? WHERE id = ?")->execute([$new_title, $b['id']]);
        $fixed_books++;
    }
}
echo "Fixed $fixed_books book titles.\n\n";

// Fix publishers
$stmt = $pdo->query("SELECT id, name FROM publishers");
$pubs = $stmt->fetchAll();
$fixed_pubs = 0;
foreach ($pubs as $p) {
    $original = $p['name'];
    $new_name = fix_cp850_to_utf8($original);
    $new_name = rtrim($new_name, '#');
    $new_name = trim($new_name);
    
    if ($new_name !== $original && $new_name !== '') {
        $pdo->prepare("UPDATE publishers SET name = ? WHERE id = ?")->execute([$new_name, $p['id']]);
        $fixed_pubs++;
    }
}
echo "Fixed $fixed_pubs publishers.\n\n";

// Merge duplicate authors after fixing encoding
$stmt = $pdo->query("SELECT name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt FROM authors GROUP BY name HAVING cnt > 1");
$dups = $stmt->fetchAll();
$merged = 0;
foreach ($dups as $dup) {
    $ids = explode(',', $dup['ids']);
    $keep = $ids[0];
    for ($i = 1; $i < count($ids); $i++) {
        $pdo->prepare("UPDATE books SET author_id = ? WHERE author_id = ?")->execute([$keep, $ids[$i]]);
        $pdo->prepare("DELETE FROM authors WHERE id = ?")->execute([$ids[$i]]);
        $merged++;
    }
}
echo "Merged $merged duplicate authors.\n";

// Show some sample authors to verify
echo "\n=== SAMPLE AUTHORS ===\n";
$stmt = $pdo->query("SELECT name FROM authors ORDER BY name LIMIT 20");
foreach ($stmt->fetchAll() as $a) {
    echo "  " . $a['name'] . "\n";
}
