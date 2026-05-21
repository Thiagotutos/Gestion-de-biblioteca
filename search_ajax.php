<?php
/**
 * search_ajax.php
 * Endpoint AJAX para búsqueda en tiempo real.
 * Compatible con Windows XP / navegadores antiguos.
 * Devuelve HTML parcial para inyectar en las tablas.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('No autorizado');
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = ($type === 'books') ? 25 : 20;
$offset = ($page - 1) * $per_page;
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Lector';

header('Content-Type: text/html; charset=utf-8');

switch ($type) {
    case 'books':
        search_books($pdo, $search, $filter, $per_page, $offset, $page, $user_role);
        break;
    case 'authors':
        search_simple($pdo, 'authors', $search, $per_page, $offset, $page, $user_role);
        break;
    case 'categories':
        search_simple($pdo, 'categories', $search, $per_page, $offset, $page, $user_role);
        break;
    case 'publishers':
        search_simple($pdo, 'publishers', $search, $per_page, $offset, $page, $user_role);
        break;
    case 'racks':
        search_simple($pdo, 'racks', $search, $per_page, $offset, $page, $user_role);
        break;
    default:
        echo 'Tipo no válido';
}

function search_books($pdo, $search, $filter, $per_page, $offset, $page, $user_role) {
    $where_clauses = [];
    $params = [];
    
    if ($search !== '') {
        $where_clauses[] = "(b.titulo LIKE ? OR a.name LIKE ? OR b.isbn LIKE ? OR c.name LIKE ? OR p.name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }
    
    if ($filter === 'missing') {
        $where_clauses[] = "b.estado = 'Extraviado'";
    } elseif ($filter === 'prestado') {
        $where_clauses[] = "b.estado = 'Prestado'";
    } elseif ($filter === 'disponible') {
        $where_clauses[] = "b.estado = 'Disponible'";
    }
    
    $where = '';
    if (!empty($where_clauses)) {
        $where = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Contar total
    $count_query = "SELECT COUNT(*) FROM books b LEFT JOIN categories c ON b.category_id = c.id LEFT JOIN authors a ON b.author_id = a.id LEFT JOIN publishers p ON b.publisher_id = p.id $where";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));
    
    // Obtener libros
    $query = "
        SELECT b.id, b.titulo, b.isbn, b.estado, b.imagen,
               c.name as categoria, a.name as autor, p.name as editorial
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN publishers p ON b.publisher_id = p.id
        $where
        ORDER BY b.id DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $libros = $stmt->fetchAll();
    
    // Generar HTML
    echo '<div id="ajax-count" data-total="' . $total . '">' . number_format($total) . ' libros encontrados</div>';
    echo '<table class="data-table"><thead><tr>';
    echo '<th>ID</th><th>Libro</th><th>Nº Inventario</th><th>Autor</th><th>Categoría</th><th>Editorial</th><th>Estado</th>';
    if ($user_role !== 'Lector') echo '<th>Acción</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($libros as $libro) {
        echo '<tr>';
        echo '<td>' . $libro['id'] . '</td>';
        echo '<td><div style="display: flex; align-items: center;">';
        if (!empty($libro['imagen'])) {
            echo '<img src="uploads/books/' . htmlspecialchars($libro['imagen']) . '" alt="Portada" class="book-cover zoomable-image" style="object-fit: cover; border-radius: 4px; cursor: pointer;" onclick="openImageModal(this.src)">';
        } else {
            echo '<div class="book-cover book-cover-placeholder"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></div>';
        }
        echo '<span>' . htmlspecialchars($libro['titulo']) . '</span></div></td>';
        echo '<td>' . htmlspecialchars($libro['isbn'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($libro['autor'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($libro['categoria'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($libro['editorial'] ?? 'N/A') . '</td>';
        
        $statusClass = 'status-available';
        if ($libro['estado'] === 'Prestado') $statusClass = 'status-borrowed';
        if ($libro['estado'] === 'Extraviado') $statusClass = 'status-missing';
        echo '<td><span class="status-badge ' . $statusClass . '">' . htmlspecialchars($libro['estado']) . '</span></td>';
        
        if ($user_role !== 'Lector') {
            echo '<td>';
            echo '<a href="edit_book.php?id=' . $libro['id'] . '" class="btn btn-edit">Editar</a> ';
            echo '<a href="delete_book.php?id=' . $libro['id'] . '" class="btn btn-delete" onclick="return confirm(\'¿Estás seguro de eliminar este libro?\');">Borrar</a>';
            echo '</td>';
        }
        echo '</tr>';
    }
    
    if (empty($libros)) {
        $cols = ($user_role !== 'Lector') ? 8 : 7;
        echo '<tr><td colspan="' . $cols . '" style="text-align: center; padding: 30px;">No se encontraron libros.</td></tr>';
    }
    
    echo '</tbody></table>';
    
    // Paginación
    if ($total_pages > 1) {
        $url_params = [];
        if ($search !== '') $url_params['search'] = $search;
        if ($filter !== '') $url_params['filter'] = $filter;
        
        echo '<div class="pagination">';
        if ($page > 1) {
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $page - 1])) . '" class="pagination-btn" data-page="' . ($page-1) . '">&laquo; Anterior</a>';
        }
        
        $start_page = max(1, $page - 3);
        $end_page = min($total_pages, $page + 3);
        
        if ($start_page > 1) {
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => 1])) . '" class="pagination-btn" data-page="1">1</a>';
            if ($start_page > 2) echo '<span class="pagination-dots">...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = $i === $page ? 'active' : '';
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $i])) . '" class="pagination-btn ' . $active . '" data-page="' . $i . '">' . $i . '</a>';
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) echo '<span class="pagination-dots">...</span>';
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $total_pages])) . '" class="pagination-btn" data-page="' . $total_pages . '">' . $total_pages . '</a>';
        }
        
        if ($page < $total_pages) {
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $page + 1])) . '" class="pagination-btn" data-page="' . ($page+1) . '">Siguiente &raquo;</a>';
        }
        echo '</div>';
    }
}

function search_simple($pdo, $table, $search, $per_page, $offset, $page, $user_role) {
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE name LIKE ?';
        $params = ["%$search%"];
    }
    
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM $table $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));
    
    $stmt = $pdo->prepare("SELECT * FROM $table $where ORDER BY name ASC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    // Nombre amigable para la tabla
    $table_names = [
        'authors' => 'autores',
        'categories' => 'categorías',
        'publishers' => 'editoriales',
        'racks' => 'estantes'
    ];
    $friendly_name = isset($table_names[$table]) ? $table_names[$table] : $table;
    
    // Texto para borrado
    $delete_labels = [
        'authors' => ['Borrar', '¿Borrar autor? Se desvinculará de sus libros.'],
        'categories' => ['Borrar', '¿Borrar categoría?'],
        'publishers' => ['Borrar', '¿Borrar editorial?'],
        'racks' => ['Borrar', '¿Borrar estante?']
    ];
    $delete_info = isset($delete_labels[$table]) ? $delete_labels[$table] : ['Borrar', '¿Borrar?'];
    
    // Relación con libros
    $book_fk = [
        'authors' => 'author_id',
        'categories' => 'category_id',
        'publishers' => 'publisher_id',
        'racks' => 'rack_id'
    ];
    $fk = isset($book_fk[$table]) ? $book_fk[$table] : '';
    
    echo '<div id="ajax-count" data-total="' . $total . '">' . number_format($total) . ' ' . $friendly_name . '</div>';
    echo '<table class="data-table"><thead><tr>';
    echo '<th>ID</th><th>Nombre</th><th>Libros</th><th>Acción</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($items as $item) {
        $count = 0;
        if ($fk) {
            $book_count = $pdo->prepare("SELECT COUNT(*) FROM books WHERE $fk = ?");
            $book_count->execute([$item['id']]);
            $count = $book_count->fetchColumn();
        }
        
        echo '<tr>';
        echo '<td>' . $item['id'] . '</td>';
        echo '<td>' . htmlspecialchars($item['name']) . '</td>';
        echo '<td><span class="status-badge status-available">' . $count . '</span></td>';
        
        if ($user_role !== 'Lector') {
            echo '<td><a href="' . $table . '.php?delete=' . $item['id'] . '" class="btn btn-delete" onclick="return confirm(\'' . $delete_info[1] . '\');">' . $delete_info[0] . '</a></td>';
        } else {
            echo '<td>-</td>';
        }
        echo '</tr>';
    }
    
    if (empty($items)) {
        echo '<tr><td colspan="4" style="text-align: center;">No hay ' . $friendly_name . '.</td></tr>';
    }
    
    echo '</tbody></table>';
    
    // Paginación
    if ($total_pages > 1) {
        $url_params = [];
        if ($search !== '') $url_params['search'] = $search;
        
        echo '<div class="pagination">';
        if ($page > 1) {
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $page - 1])) . '" class="pagination-btn" data-page="' . ($page-1) . '">&laquo;</a>';
        }
        for ($i = max(1, $page-3); $i <= min($total_pages, $page+3); $i++) {
            $active = $i === $page ? 'active' : '';
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $i])) . '" class="pagination-btn ' . $active . '" data-page="' . $i . '">' . $i . '</a>';
        }
        if ($page < $total_pages) {
            echo '<a href="?' . http_build_query(array_merge($url_params, ['page' => $page + 1])) . '" class="pagination-btn" data-page="' . ($page+1) . '">&raquo;</a>';
        }
        echo '</div>';
    }
}
?>
