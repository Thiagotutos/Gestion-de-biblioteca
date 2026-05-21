<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO publishers (name) VALUES (?)");
        try { $stmt->execute([$name]); $_SESSION['success_msg'] = "Editorial agregada."; } 
        catch (Exception $e) { $error = "La editorial ya existe."; }
        header('Location: publishers.php'); exit;
    }
}

if (isset($_GET['delete'])) {
    if ($_SESSION['user_role'] === 'Lector') { header('Location: publishers.php'); exit; }
    $id = $_GET['delete'];
    $pdo->prepare("UPDATE books SET publisher_id = NULL WHERE publisher_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM publishers WHERE id = ?")->execute([$id]);
    $_SESSION['success_msg'] = "Editorial eliminada.";
    header('Location: publishers.php'); exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;
$where = ''; $params = [];
if ($search !== '') { $where = 'WHERE name LIKE ?'; $params = ["%$search%"]; }

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM publishers $where");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM publishers $where ORDER BY name ASC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$publishers = $stmt->fetchAll();
$url_params = [];
if ($search !== '') $url_params['search'] = $search;
?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar Editoriales</h2>
    <span id="result-count" style="color:#666;font-size:0.9rem;"><?php echo number_format($total); ?> editoriales</span>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display:flex;gap:20px;">
    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
    <div class="form-container" style="flex:1;height:max-content;">
        <h3>Agregar Editorial</h3><br>
        <form method="POST">
            <div class="form-group"><label>Nombre de la Editorial</label>
            <input type="text" name="name" class="form-control" required></div>
            <button type="submit" name="add_item" class="btn btn-primary">Guardar</button>
        </form>
    </div>
    <?php endif; ?>

    <div style="flex:2;">
        <form method="GET" class="search-filters-bar" style="margin-bottom:15px;">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="search" id="liveSearchInput" class="search-input" placeholder="Buscar editorial..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                <span id="searchSpinner" class="search-spinner" style="display:none;"></span>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if ($search !== ''): ?><a href="publishers.php" class="btn" style="background:#999;">Limpiar</a><?php endif; ?>
        </form>

        <div id="searchResults">
        <table class="data-table"><thead><tr><th>ID</th><th>Nombre</th><th>Libros</th><th>Acción</th></tr></thead>
            <tbody>
                <?php foreach ($publishers as $item): 
                    $bc = $pdo->prepare("SELECT COUNT(*) FROM books WHERE publisher_id = ?");
                    $bc->execute([$item['id']]); $count = $bc->fetchColumn(); ?>
                <tr>
                    <td><?php echo $item['id']; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><span class="status-badge status-available"><?php echo $count; ?></span></td>
                    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
                    <td><a href="publishers.php?delete=<?php echo $item['id']; ?>" class="btn btn-delete" onclick="return confirm('¿Borrar editorial?');">Borrar</a></td>
                    <?php else: ?><td>-</td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($publishers)): ?><tr><td colspan="4" style="text-align:center;">No hay editoriales.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>" class="pagination-btn">&laquo;</a><?php endif; ?>
            <?php for ($i = max(1, $page-3); $i <= min($total_pages, $page+3); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?><a href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>" class="pagination-btn">&raquo;</a><?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){
    var si=document.getElementById('liveSearchInput'),rc=document.getElementById('searchResults'),
        cnt=document.getElementById('result-count'),sp=document.getElementById('searchSpinner'),dt=null,xhr=null;
    function search(){
        sp.style.display='inline-block';
        if(xhr){xhr.abort();}
        xhr=window.XMLHttpRequest?new XMLHttpRequest():new ActiveXObject("Microsoft.XMLHTTP");
        xhr.onreadystatechange=function(){
            if(xhr.readyState===4){sp.style.display='none';if(xhr.status===200){
                rc.innerHTML=xhr.responseText;
                var c=document.getElementById('ajax-count');if(c&&cnt){cnt.textContent=c.textContent;}
                bindPag();
            }}
        };
        xhr.open('GET','search_ajax.php?type=publishers&q='+encodeURIComponent(si.value)+'&page=1',true);
        xhr.send();
    }
    function bindPag(){
        var l=rc.getElementsByClassName('pagination-btn');
        for(var i=0;i<l.length;i++){l[i].onclick=function(e){e.preventDefault();var p=this.getAttribute('data-page');if(p)loadP(parseInt(p));return false;};}
    }
    function loadP(pg){
        sp.style.display='inline-block';if(xhr){xhr.abort();}
        xhr=window.XMLHttpRequest?new XMLHttpRequest():new ActiveXObject("Microsoft.XMLHTTP");
        xhr.onreadystatechange=function(){
            if(xhr.readyState===4){sp.style.display='none';if(xhr.status===200){
                rc.innerHTML=xhr.responseText;
                var c=document.getElementById('ajax-count');if(c&&cnt){cnt.textContent=c.textContent;}
                bindPag();
            }}
        };
        xhr.open('GET','search_ajax.php?type=publishers&q='+encodeURIComponent(si.value)+'&page='+pg,true);
        xhr.send();
    }
    if(si){si.onkeyup=function(){if(dt)clearTimeout(dt);dt=setTimeout(search,500);};}
})();
</script>

<?php require_once 'includes/footer.php'; ?>
