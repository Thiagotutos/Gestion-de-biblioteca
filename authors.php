<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lógica para agregar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO authors (name) VALUES (?)");
        try { $stmt->execute([$name]); $_SESSION['success_msg'] = "Autor agregado."; } 
        catch (Exception $e) { $error = "El autor ya existe."; }
        header('Location: authors.php');
        exit;
    }
}

// Lógica para borrar
if (isset($_GET['delete'])) {
    if ($_SESSION['user_role'] === 'Lector') { header('Location: authors.php'); exit; }
    $id = $_GET['delete'];
    $pdo->prepare("UPDATE books SET author_id = NULL WHERE author_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM authors WHERE id = ?")->execute([$id]);
    $_SESSION['success_msg'] = "Autor eliminado.";
    
    if (isset($_SERVER['HTTP_REFERER'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    } else {
        header('Location: authors.php');
    }
    exit;
}

// Búsqueda y paginación
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE name LIKE ?';
    $params = ["%$search%"];
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM authors $where");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM authors $where ORDER BY name ASC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$authors = $stmt->fetchAll();

$url_params = [];
if ($search !== '') $url_params['search'] = $search;
?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar Autores</h2>
    <span id="result-count" style="color: #666; font-size: 0.9rem;"><?php echo number_format($total); ?> autores</span>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display: flex; gap: 20px;">
    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
    <!-- Formulario para agregar -->
    <div class="form-container" style="flex: 1; height: max-content;">
        <h3>Agregar Autor</h3><br>
        <form method="POST">
            <div class="form-group">
                <label>Nombre del Autor</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <button type="submit" name="add_item" class="btn btn-primary">Guardar</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tabla -->
    <div style="flex: 2;">
        <!-- Buscador -->
        <form method="GET" class="search-filters-bar" style="margin-bottom: 15px;" id="searchForm">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" name="search" id="liveSearchInput" class="search-input" placeholder="Buscar autor..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                <span id="searchSpinner" class="search-spinner" style="display:none;"></span>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if ($search !== ''): ?>
            <a href="authors.php" class="btn" style="background: #999;">Limpiar</a>
            <?php endif; ?>
        </form>

        <div id="searchResults">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Libros</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($authors as $item): 
                    $book_count = $pdo->prepare("SELECT COUNT(*) FROM books WHERE author_id = ?");
                    $book_count->execute([$item['id']]);
                    $count = $book_count->fetchColumn();
                ?>
                <tr>
                    <td><?php echo $item['id']; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><span class="status-badge status-available"><?php echo $count; ?></span></td>
                    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
                    <td>
                        <a href="authors.php?delete=<?php echo $item['id']; ?>" class="btn btn-delete" onclick="return confirm('¿Borrar autor? Se desvinculará de sus libros.');">Borrar</a>
                    </td>
                    <?php else: ?>
                    <td>-</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($authors)): ?>
                <tr><td colspan="4" style="text-align: center;">No hay autores.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>" class="pagination-btn">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-3); $i <= min($total_pages, $page+3); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>" class="pagination-btn">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
// === BÚSQUEDA EN VIVO ===
(function() {
    var searchInput = document.getElementById('liveSearchInput');
    var resultsContainer = document.getElementById('searchResults');
    var resultCount = document.getElementById('result-count');
    var spinner = document.getElementById('searchSpinner');
    var debounceTimer = null;
    var currentXHR = null;
    
    function doLiveSearch() {
        var query = searchInput.value;
        
        spinner.style.display = 'inline-block';
        
        if (currentXHR) {
            currentXHR.abort();
        }
        
        if (window.XMLHttpRequest) {
            currentXHR = new XMLHttpRequest();
        } else {
            currentXHR = new ActiveXObject("Microsoft.XMLHTTP");
        }
        
        var url = 'search_ajax.php?type=authors&q=' + encodeURIComponent(query) + '&page=1';
        
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
        
        spinner.style.display = 'inline-block';
        
        if (currentXHR) { currentXHR.abort(); }
        
        if (window.XMLHttpRequest) {
            currentXHR = new XMLHttpRequest();
        } else {
            currentXHR = new ActiveXObject("Microsoft.XMLHTTP");
        }
        
        var url = 'search_ajax.php?type=authors&q=' + encodeURIComponent(query) + '&page=' + pageNum;
        
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
    
    if (searchInput) {
        searchInput.onkeyup = function() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(function() {
                doLiveSearch();
            }, 500);
        };
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
