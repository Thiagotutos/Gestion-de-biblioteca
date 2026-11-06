<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Paginación
$per_page = 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Búsqueda
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_by = isset($_GET['search_by']) ? trim($_GET['search_by']) : 'all';
$filter_estado = isset($_GET['filter']) ? trim($_GET['filter']) : '';

$where_clauses = [];
$params = [];

if ($search !== '') {
    $search_param = "%$search%";
    switch ($search_by) {
        case 'title':
            $where_clauses[] = "b.titulo LIKE ?";
            $params[] = $search_param;
            break;
        case 'author':
            $where_clauses[] = "a.name LIKE ?";
            $params[] = $search_param;
            break;
        case 'isbn':
            $where_clauses[] = "b.isbn LIKE ?";
            $params[] = $search_param;
            break;
        case 'signatura':
            $where_clauses[] = "(b.signatura LIKE ? OR b.observacion LIKE ?)";
            $params[] = $search_param;
            $params[] = $search_param;
            break;
        case 'isbn_editorial':
            $where_clauses[] = "(b.isbn_editorial LIKE ? OR b.observacion LIKE ?)";
            $params[] = $search_param;
            $params[] = $search_param;
            break;
        case 'category':
            $where_clauses[] = "c.name LIKE ?";
            $params[] = $search_param;
            break;
        case 'publisher':
            $where_clauses[] = "p.name LIKE ?";
            $params[] = $search_param;
            break;
        default: // 'all'
            $where_clauses[] = "(b.titulo LIKE ? OR a.name LIKE ? OR b.isbn LIKE ? OR b.signatura LIKE ? OR b.isbn_editorial LIKE ? OR c.name LIKE ? OR p.name LIKE ? OR b.lugar_editorial LIKE ? OR b.observacion LIKE ?)";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
            break;
    }
}

if ($filter_estado === 'missing') {
    $where_clauses[] = "b.estado = 'Extraviado'";
} elseif ($filter_estado === 'prestado') {
    $where_clauses[] = "b.estado = 'Prestado'";
} elseif ($filter_estado === 'disponible') {
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

// Obtener libros con paginación
$query = "
    SELECT b.id, b.titulo, b.isbn, b.signatura, b.isbn_editorial, b.estado, b.imagen, b.created_at,
           b.lugar_editorial, b.fecha_publicacion, b.notas, b.observacion,
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

// Build URL params for pagination links
$url_params = [];
if ($search !== '') $url_params['search'] = $search;
if ($search_by !== 'all') $url_params['search_by'] = $search_by;
if ($filter_estado !== '') $url_params['filter'] = $filter_estado;
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar Libros</h2>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <span id="result-count" style="color: #666; font-size: 0.9rem;"><?php echo number_format($total); ?> libros encontrados</span>
        <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
        <a href="add_book.php" class="btn btn-primary">+ Agregar Libro</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success">
        <?php 
            echo htmlspecialchars($_SESSION['success_msg']); 
            unset($_SESSION['success_msg']);
        ?>
    </div>
<?php endif; ?>

<!-- Buscador y Filtros -->
<div class="search-filters-bar">
    <form method="GET" class="search-form" id="searchForm">
        <select name="search_by" id="searchBySelect" class="filter-select" title="Criterio de búsqueda">
            <option value="all" <?php echo $search_by === 'all' ? 'selected' : ''; ?>>Todos los campos</option>
            <option value="title" <?php echo $search_by === 'title' ? 'selected' : ''; ?>>Título</option>
            <option value="author" <?php echo $search_by === 'author' ? 'selected' : ''; ?>>Autor</option>
            <option value="isbn" <?php echo $search_by === 'isbn' ? 'selected' : ''; ?>>Nº Inventario</option>
            <option value="signatura" <?php echo $search_by === 'signatura' ? 'selected' : ''; ?>>Signatura Topográfica</option>
            <option value="isbn_editorial" <?php echo $search_by === 'isbn_editorial' ? 'selected' : ''; ?>>ISBN (Editorial)</option>
            <option value="category" <?php echo $search_by === 'category' ? 'selected' : ''; ?>>Materia</option>
            <option value="publisher" <?php echo $search_by === 'publisher' ? 'selected' : ''; ?>>Editorial</option>
        </select>
        <div class="search-input-wrapper">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text" name="search" id="liveSearchInput" class="search-input" placeholder="Buscar por título, autor, signatura, inventario, ISBN, materia o editorial..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
            <span id="searchSpinner" class="search-spinner" style="display:none;"></span>
        </div>
        <select name="filter" id="filterSelect" class="filter-select" title="Filtrar por estado">
            <option value="">Todos los estados</option>
            <option value="disponible" <?php echo $filter_estado === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
            <option value="prestado" <?php echo $filter_estado === 'prestado' ? 'selected' : ''; ?>>Prestado</option>
            <option value="missing" <?php echo $filter_estado === 'missing' ? 'selected' : ''; ?>>Extraviado</option>
        </select>
        <button type="submit" class="btn btn-primary">Buscar</button>
        <?php if ($search !== '' || $filter_estado !== '' || $search_by !== 'all'): ?>
        <a href="books.php" class="btn" style="background: #999;">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Contenedor de resultados (se actualiza con AJAX) -->
<div id="searchResults">
<div class="table-responsive">
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Libro</th>
            <th>Nº Inventario</th>
            <th>Signatura</th>
            <th>ISBN</th>
            <th>Autor</th>
            <th>Materia</th>
            <th>Editorial</th>
            <th>Estado</th>
            <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
            <th>Acción</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($libros as $libro): ?>
        <?php
            $sig_val = !empty($libro['signatura']) ? $libro['signatura'] : '';
            if ($sig_val === '' && !empty($libro['observacion']) && preg_match('/Signatura:\s*([^\|]+)/i', $libro['observacion'], $ms)) {
                $sig_val = trim($ms[1]);
            }
            $isbn_val = !empty($libro['isbn_editorial']) ? $libro['isbn_editorial'] : '';
            if ($isbn_val === '' && !empty($libro['observacion']) && preg_match('/ISBN:\s*([^\|]+)/i', $libro['observacion'], $mi)) {
                $isbn_val = trim($mi[1]);
            }
        ?>
        <tr>
            <td><?php echo $libro['id']; ?></td>
            <td>
                <div style="display: flex; align-items: center;">
                    <?php if (!empty($libro['imagen'])): ?>
                        <img src="../uploads/books/' . htmlspecialchars($libro['imagen']); ?>" alt="Portada" class="book-cover zoomable-image" style="object-fit: cover; border-radius: 4px; cursor: pointer;" onclick="openImageModal(this.src)">
                    <?php else: ?>
                        <div class="book-cover book-cover-placeholder">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5">
                                <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong><?php echo htmlspecialchars($libro['titulo']); ?></strong>
                        <?php if (!empty($libro['fecha_publicacion'])): ?>
                            <div style="font-size: 0.78rem; color: #777;">Pub: <?php echo date('d/m/Y', strtotime($libro['fecha_publicacion'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($libro['notas'])): ?>
                            <div style="font-size: 0.75rem; color: #888; font-style: italic;" title="<?php echo htmlspecialchars($libro['notas']); ?>">Nota: <?php echo htmlspecialchars(mb_strimwidth($libro['notas'], 0, 40, '...')); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($libro['observacion'])): ?>
                            <div style="font-size: 0.72rem; color: #0288d1; margin-top: 2px;" title="<?php echo htmlspecialchars($libro['observacion']); ?>">Obs: <?php echo htmlspecialchars(mb_strimwidth($libro['observacion'], 0, 48, '...')); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td><strong><?php echo htmlspecialchars($libro['isbn'] ?? 'N/A'); ?></strong></td>
            <td><?php echo !empty($sig_val) ? '<span style="display:inline-block; background:#e0f2fe; color:#0284c7; padding:2px 7px; border-radius:4px; font-weight:600; font-size:0.83rem;">' . htmlspecialchars($sig_val) . '</span>' : '<span style="color:#94a3b8;">-</span>'; ?></td>
            <td><?php echo !empty($isbn_val) ? '<span style="font-size:0.83rem; font-family:monospace;">' . htmlspecialchars($isbn_val) . '</span>' : '<span style="color:#94a3b8;">-</span>'; ?></td>
            <td><?php echo htmlspecialchars($libro['autor'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($libro['categoria'] ?? 'N/A'); ?></td>
            <td>
                <?php echo htmlspecialchars($libro['editorial'] ?? 'N/A'); ?>
                <?php if (!empty($libro['lugar_editorial'])): ?>
                    <div style="font-size: 0.78rem; color: #777;"><?php echo htmlspecialchars($libro['lugar_editorial']); ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php 
                    $statusClass = 'status-available';
                    if ($libro['estado'] === 'Prestado') $statusClass = 'status-borrowed';
                    if ($libro['estado'] === 'Extraviado') $statusClass = 'status-missing';
                ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($libro['estado']); ?>
                </span>
            </td>
            <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
            <td>
                <a href="edit_book.php?id=<?php echo $libro['id']; ?>" class="btn btn-edit">Editar</a>
                <a href="delete_book.php?id=<?php echo $libro['id']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este libro?');">Borrar</a>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($libros)): ?>
        <tr>
            <td colspan="<?php echo ($_SESSION['user_role'] !== 'Lector') ? 10 : 9; ?>" style="text-align: center; padding: 30px;">No se encontraron libros.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Paginación -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>" class="pagination-btn">&laquo; Anterior</a>
    <?php endif; ?>
    
    <?php
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $page + 3);
    
    if ($start_page > 1): ?>
        <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => 1])); ?>" class="pagination-btn">1</a>
        <?php if ($start_page > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
    <?php endif;
    
    for ($i = $start_page; $i <= $end_page; $i++): ?>
        <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor;
    
    if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
        <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $total_pages])); ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
    <?php endif; ?>
    
    <?php if ($page < $total_pages): ?>
        <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>" class="pagination-btn">Siguiente &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
    <img class="image-modal-content" id="modalImage">
</div>

<script>
function openImageModal(src) {
    var modal = document.getElementById('imageModal');
    var modalImg = document.getElementById('modalImage');
    modal.style.display = 'flex';
    modalImg.src = src;
}

function closeImageModal() {
    var modal = document.getElementById('imageModal');
    modal.style.display = 'none';
}

// === BÚSQUEDA EN VIVO Y POR CAMPO ===
(function() {
    var searchInput = document.getElementById('liveSearchInput');
    var searchBySelect = document.getElementById('searchBySelect');
    var filterSelect = document.getElementById('filterSelect');
    var resultsContainer = document.getElementById('searchResults');
    var resultCount = document.getElementById('result-count');
    var spinner = document.getElementById('searchSpinner');
    var debounceTimer = null;
    var currentXHR = null;

    var placeholders = {
        'all': 'Buscar por título, autor, signatura, inventario, ISBN, materia o editorial...',
        'title': 'Escribe el título del libro...',
        'author': 'Escribe el nombre del autor...',
        'isbn': 'Escribe el número de inventario...',
        'signatura': 'Escribe la signatura topográfica (ej: 001 SAG)...',
        'isbn_editorial': 'Escribe el ISBN editorial (ej: 84-08-01645-8)...',
        'category': 'Escribe el nombre de la materia...',
        'publisher': 'Escribe el nombre de la editorial...'
    };

    function updatePlaceholder() {
        if (searchBySelect && searchInput) {
            var selectedBy = searchBySelect.value;
            searchInput.placeholder = placeholders[selectedBy] || placeholders['all'];
        }
    }
    
    function doLiveSearch(resetPage) {
        var query = searchInput.value;
        var searchBy = searchBySelect ? searchBySelect.value : 'all';
        var filter = filterSelect.value;
        var page = resetPage ? 1 : 1;
        
        // Mostrar spinner
        spinner.style.display = 'inline-block';
        
        // Cancelar petición anterior si existe
        if (currentXHR) {
            currentXHR.abort();
        }
        
        // Crear petición AJAX (compatible con navegadores antiguos / CRT)
        if (window.XMLHttpRequest) {
            currentXHR = new XMLHttpRequest();
        } else {
            currentXHR = new ActiveXObject("Microsoft.XMLHTTP");
        }
        
        var url = '../api/search_ajax.php?type=books&q=' + encodeURIComponent(query) + 
                  '&search_by=' + encodeURIComponent(searchBy) + 
                  '&filter=' + encodeURIComponent(filter) + 
                  '&page=' + page;
        
        currentXHR.onreadystatechange = function() {
            if (currentXHR.readyState === 4) {
                spinner.style.display = 'none';
                if (currentXHR.status === 200) {
                    resultsContainer.innerHTML = currentXHR.responseText;
                    // Actualizar contador
                    var countEl = document.getElementById('ajax-count');
                    if (countEl && resultCount) {
                        resultCount.textContent = countEl.textContent;
                    }
                    // Vincular paginación AJAX
                    bindPaginationLinks();
                }
            }
        };
        
        currentXHR.open('GET', url, true);
        currentXHR.send();
    }
    
    function bindPaginationLinks() {
        var links = resultsContainer.getElementsByClassName('pagination-btn');
        for (var i = 0; i < links.length; i++) {
            links[i].onclick = function(e) {
                e.preventDefault();
                var pageNum = this.getAttribute('data-page');
                if (pageNum) {
                    loadPage(parseInt(pageNum));
                }
                return false;
            };
        }
    }
    
    function loadPage(pageNum) {
        var query = searchInput.value;
        var searchBy = searchBySelect ? searchBySelect.value : 'all';
        var filter = filterSelect.value;
        
        spinner.style.display = 'inline-block';
        
        if (currentXHR) {
            currentXHR.abort();
        }
        
        if (window.XMLHttpRequest) {
            currentXHR = new XMLHttpRequest();
        } else {
            currentXHR = new ActiveXObject("Microsoft.XMLHTTP");
        }
        
        var url = '../api/search_ajax.php?type=books&q=' + encodeURIComponent(query) + 
                  '&search_by=' + encodeURIComponent(searchBy) + 
                  '&filter=' + encodeURIComponent(filter) + 
                  '&page=' + pageNum;
        
        currentXHR.onreadystatechange = function() {
            if (currentXHR.readyState === 4) {
                spinner.style.display = 'none';
                if (currentXHR.status === 200) {
                    resultsContainer.innerHTML = currentXHR.responseText;
                    var countEl = document.getElementById('ajax-count');
                    if (countEl && resultCount) {
                        resultCount.textContent = countEl.textContent;
                    }
                    bindPaginationLinks();
                }
            }
        };
        
        currentXHR.open('GET', url, true);
        currentXHR.send();
    }
    
    // Escuchar cambios en el input con debounce de 400ms
    if (searchInput) {
        searchInput.onkeyup = function() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(function() {
                doLiveSearch(true);
            }, 400);
        };
    }

    // Escuchar cambios en el selector de campo de búsqueda
    if (searchBySelect) {
        searchBySelect.onchange = function() {
            updatePlaceholder();
            doLiveSearch(true);
        };
    }
    
    // Escuchar cambios en el filtro de estado
    if (filterSelect) {
        filterSelect.onchange = function() {
            doLiveSearch(true);
        };
    }

    updatePlaceholder();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
