<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['user_role'] === 'Lector') {
    header('Location: books.php');
    exit;
}


$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: books.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$id]);
$libro = $stmt->fetch();

if (!$libro) {
    header('Location: books.php');
    exit;
}

// Obtener nombres actuales de autor, editorial y categoría
$curr_author = '';
if (!empty($libro['author_id'])) {
    $stmt_ca = $pdo->prepare("SELECT name FROM authors WHERE id = ?");
    $stmt_ca->execute([$libro['author_id']]);
    $curr_author = $stmt_ca->fetchColumn() ?: '';
}

$curr_publisher = '';
if (!empty($libro['publisher_id'])) {
    $stmt_cp = $pdo->prepare("SELECT name FROM publishers WHERE id = ?");
    $stmt_cp->execute([$libro['publisher_id']]);
    $curr_publisher = $stmt_cp->fetchColumn() ?: '';
}

$curr_category = '';
if (!empty($libro['category_id'])) {
    $stmt_cc = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt_cc->execute([$libro['category_id']]);
    $curr_category = $stmt_cc->fetchColumn() ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $author_id = !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null;
    $author_name = trim($_POST['author_name'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $signatura = trim($_POST['signatura'] ?? '') ?: null;
    $isbn_editorial = trim($_POST['isbn_editorial'] ?? '') ?: null;
    $publisher_id = !empty($_POST['publisher_id']) ? (int)$_POST['publisher_id'] : null;
    $publisher_name = trim($_POST['publisher_name'] ?? '');
    $lugar_editorial = trim($_POST['lugar_editorial'] ?? '') ?: null;
    $fecha_publicacion = !empty($_POST['fecha_publicacion']) ? $_POST['fecha_publicacion'] : null;
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $category_name = trim($_POST['category_name'] ?? '');
    $notas = trim($_POST['notas'] ?? '') ?: null;
    $observacion = trim($_POST['observacion'] ?? '') ?: null;
    $rack_id = null;
    $estado = $_POST['estado'] ?? 'Disponible';

    // Manejo de imagen (mantenimiento retrocompatible sin subidas)
    $imagen = $libro['imagen'] ?? null;

    // Validar duplicado de inventario si se modifica
    if (!isset($error) && !empty($isbn)) {
        $stmt_check = $pdo->prepare("SELECT id, titulo FROM books WHERE isbn = ? AND id != ? LIMIT 1");
        $stmt_check->execute([$isbn, $id]);
        $duplicado = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($duplicado) {
            $error = "El Nº de Inventario '{$isbn}' ya está asignado al libro '{$duplicado['titulo']}' (ID: {$duplicado['id']}). Elige otro número para evitar duplicados.";
        }
    }

    if (!isset($error)) {
        if (!$titulo) {
            $error = "El Título del libro es obligatorio.";
        } elseif (!$author_id && $author_name === '') {
            $error = "El Autor es obligatorio. Busca o escribe uno.";
        } elseif (!empty($fecha_publicacion)) {
            $hoy = date('Y-m-d');
            if ($fecha_publicacion > $hoy) {
                $error = "La fecha de publicación no puede ser una fecha futura (solo se permite hoy o fechas pasadas).";
            }
        }
    }

    if (!isset($error)) {
        $pdo->beginTransaction();
        try {
            // 1. Resolver o Crear Autor si no existe
            if (!$author_id && $author_name !== '') {
                $stmt_a = $pdo->prepare("SELECT id FROM authors WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
                $stmt_a->execute([$author_name]);
                $existing_a = $stmt_a->fetchColumn();
                if ($existing_a) {
                    $author_id = (int)$existing_a;
                } else {
                    $stmt_ins_a = $pdo->prepare("INSERT INTO authors (name) VALUES (?)");
                    $stmt_ins_a->execute([$author_name]);
                    $author_id = (int)$pdo->lastInsertId();
                }
            }

            // 2. Resolver o Crear Editorial si no existe
            if (!$publisher_id && $publisher_name !== '') {
                $stmt_p = $pdo->prepare("SELECT id FROM publishers WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
                $stmt_p->execute([$publisher_name]);
                $existing_p = $stmt_p->fetchColumn();
                if ($existing_p) {
                    $publisher_id = (int)$existing_p;
                } else {
                    $stmt_ins_p = $pdo->prepare("INSERT INTO publishers (name) VALUES (?)");
                    $stmt_ins_p->execute([$publisher_name]);
                    $publisher_id = (int)$pdo->lastInsertId();
                }
            }

            // 3. Resolver o Crear Categoría si no existe
            if (!$category_id && $category_name !== '') {
                $stmt_c = $pdo->prepare("SELECT id FROM categories WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
                $stmt_c->execute([$category_name]);
                $existing_c = $stmt_c->fetchColumn();
                if ($existing_c) {
                    $category_id = (int)$existing_c;
                } else {
                    $stmt_ins_c = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                    $stmt_ins_c->execute([$category_name]);
                    $category_id = (int)$pdo->lastInsertId();
                }
            }

            $stmt = $pdo->prepare("UPDATE books SET titulo=?, author_id=?, isbn=?, signatura=?, isbn_editorial=?, publisher_id=?, lugar_editorial=?, fecha_publicacion=?, category_id=?, rack_id=?, imagen=?, notas=?, observacion=?, estado=? WHERE id=?");
            $stmt->execute([$titulo, $author_id, $isbn, $signatura, $isbn_editorial, $publisher_id, $lugar_editorial, $fecha_publicacion, $category_id, $rack_id, $imagen, $notas, $observacion, $estado, $id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Libro actualizado exitosamente.";
            header('Location: books.php');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Editar Libro #<?php echo $id; ?></h2>
    <a href="books.php" class="btn btn-primary" style="background-color: #666;">Volver</a>
</div>

<div class="form-container" style="max-width: 800px; width: 100%;">
    <?php if (isset($error)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label>Título *</label>
            <input type="text" name="titulo" class="form-control" required value="<?php echo htmlspecialchars($_POST['titulo'] ?? $libro['titulo']); ?>">
        </div>

        <!-- Buscador en tiempo real: Autor -->
        <div class="form-group" style="position: relative;">
            <label style="display: flex; justify-content: space-between; align-items: center;">
                <span>Autor *</span>
                <span id="authorStatusBadge" class="status-badge" style="<?php echo $curr_author ? 'background:#2e7d32;color:#fff;' : 'display:none;'; ?> font-size: 0.75rem; padding: 2px 6px;">
                    <?php echo $curr_author ? 'Seleccionado' : ''; ?>
                </span>
            </label>
            <div style="position: relative;">
                <input type="text" id="authorSearchInput" name="author_name" class="form-control" placeholder="Escribe para buscar o ingresar autor..." value="<?php echo htmlspecialchars($_POST['author_name'] ?? $curr_author); ?>" autocomplete="off" required>
                <input type="hidden" name="author_id" id="selectedAuthorId" value="<?php echo htmlspecialchars($_POST['author_id'] ?? $libro['author_id']); ?>">
                <span id="authorSpinner" class="search-spinner" style="display: none;"></span>
            </div>
            <div id="authorSearchResults" class="autocomplete-dropdown" style="display: none;"></div>
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">
                Escribe el nombre del autor. Si no existe, se creará automáticamente.
            </small>
        </div>

        <div class="form-group">
            <label>Nº Inventario</label>
            <input type="text" name="isbn" class="form-control" value="<?php echo htmlspecialchars($_POST['isbn'] ?? $libro['isbn'] ?? ''); ?>">
        </div>

        <!-- Signatura Topográfica e ISBN Editorial -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Signatura Topográfica</label>
                <input type="text" name="signatura" class="form-control" placeholder="Ej: 001 SAG, R 806.0-3 REA..." value="<?php echo htmlspecialchars($_POST['signatura'] ?? $libro['signatura'] ?? ''); ?>">
                <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">Ubicación en estantería (CDD / Código de autor).</small>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>ISBN (Editorial)</label>
                <input type="text" name="isbn_editorial" class="form-control" placeholder="Ej: 84-08-01645-8, 978-950-07..." value="<?php echo htmlspecialchars($_POST['isbn_editorial'] ?? $libro['isbn_editorial'] ?? ''); ?>">
                <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">Código comercial internacional impreso en el libro.</small>
            </div>
        </div>

        <!-- Buscador en tiempo real: Editorial -->
        <div class="form-group" style="position: relative;">
            <label style="display: flex; justify-content: space-between; align-items: center;">
                <span>Editorial</span>
                <span id="publisherStatusBadge" class="status-badge" style="<?php echo $curr_publisher ? 'background:#2e7d32;color:#fff;' : 'display:none;'; ?> font-size: 0.75rem; padding: 2px 6px;">
                    <?php echo $curr_publisher ? 'Seleccionado' : ''; ?>
                </span>
            </label>
            <div style="position: relative;">
                <input type="text" id="publisherSearchInput" name="publisher_name" class="form-control" placeholder="Escribe para buscar o ingresar editorial..." value="<?php echo htmlspecialchars($_POST['publisher_name'] ?? $curr_publisher); ?>" autocomplete="off">
                <input type="hidden" name="publisher_id" id="selectedPublisherId" value="<?php echo htmlspecialchars($_POST['publisher_id'] ?? $libro['publisher_id']); ?>">
                <span id="publisherSpinner" class="search-spinner" style="display: none;"></span>
            </div>
            <div id="publisherSearchResults" class="autocomplete-dropdown" style="display: none;"></div>
        </div>

        <div class="form-group">
            <label>Lugar de la Editorial</label>
            <input type="text" name="lugar_editorial" class="form-control" value="<?php echo htmlspecialchars($_POST['lugar_editorial'] ?? $libro['lugar_editorial'] ?? ''); ?>" placeholder="Ej: Buenos Aires">
        </div>

        <div class="form-group">
            <label>Fecha de Publicación</label>
            <input type="date" name="fecha_publicacion" id="fechaPublicacion" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['fecha_publicacion'] ?? $libro['fecha_publicacion'] ?? ''); ?>">
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 3px;">Solo se permiten fechas pasadas o la fecha de hoy.</small>
        </div>

        <!-- Buscador en tiempo real: Materia -->
        <div class="form-group" style="position: relative;">
            <label style="display: flex; justify-content: space-between; align-items: center;">
                <span>Materia</span>
                <span id="categoryStatusBadge" class="status-badge" style="<?php echo $curr_category ? 'background:#2e7d32;color:#fff;' : 'display:none;'; ?> font-size: 0.75rem; padding: 2px 6px;">
                    <?php echo $curr_category ? 'Seleccionado' : ''; ?>
                </span>
            </label>
            <div style="position: relative;">
                <input type="text" id="categorySearchInput" name="category_name" class="form-control" placeholder="Escribe para buscar o ingresar materia..." value="<?php echo htmlspecialchars($_POST['category_name'] ?? $curr_category); ?>" autocomplete="off">
                <input type="hidden" name="category_id" id="selectedCategoryId" value="<?php echo htmlspecialchars($_POST['category_id'] ?? $libro['category_id']); ?>">
                <span id="categorySpinner" class="search-spinner" style="display: none;"></span>
            </div>
            <div id="categorySearchResults" class="autocomplete-dropdown" style="display: none;"></div>
        </div>


        <div class="form-group">
            <label>Notas (opcional)</label>
            <textarea name="notas" class="form-control" rows="3" placeholder="Notas internas..."><?php echo htmlspecialchars($_POST['notas'] ?? $libro['notas'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Observación (opcional)</label>
            <textarea name="observacion" class="form-control" rows="3" placeholder="Observaciones adicionales..."><?php echo htmlspecialchars($_POST['observacion'] ?? $libro['observacion'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Estado</label>
            <select name="estado" class="form-control">
                <option value="Disponible" <?php if (($libro['estado'] ?? '') == 'Disponible') echo 'selected'; ?>>Disponible</option>
                <option value="Prestado" <?php if (($libro['estado'] ?? '') == 'Prestado') echo 'selected'; ?>>Prestado</option>
                <option value="Extraviado" <?php if (($libro['estado'] ?? '') == 'Extraviado') echo 'selected'; ?>>Extraviado</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">Actualizar Libro</button>
    </form>
</div>

<script>
// ============================================================================
// Autocompletado dinámico con AJAX compatible con CRT / navegadores antiguos
// ============================================================================
function setupAutocomplete(config) {
    var input = document.getElementById(config.inputId);
    var hidden = document.getElementById(config.hiddenId);
    var dropdown = document.getElementById(config.dropdownId);
    var spinner = document.getElementById(config.spinnerId);
    var badge = document.getElementById(config.badgeId);
    var timer = null;
    var xhr = null;

    if (!input || !dropdown) return;

    function updateBadge(status, text) {
        if (!badge) return;
        if (status === 'found') {
            badge.style.display = 'inline-block';
            badge.style.background = '#2e7d32';
            badge.style.color = '#fff';
            badge.textContent = text || 'Existente';
        } else if (status === 'new') {
            badge.style.display = 'inline-block';
            badge.style.background = '#0288d1';
            badge.style.color = '#fff';
            badge.textContent = text || '+ Se creará nuevo';
        } else {
            badge.style.display = 'none';
        }
    }

    function doSearch() {
        var query = input.value.trim();
        if (query.length < 1) {
            dropdown.style.display = 'none';
            if (spinner) spinner.style.display = 'none';
            return;
        }

        if (spinner) spinner.style.display = 'inline-block';
        if (xhr) xhr.abort();

        if (window.XMLHttpRequest) {
            xhr = new XMLHttpRequest();
        } else {
            xhr = new ActiveXObject("Microsoft.XMLHTTP");
        }

        xhr.open('GET', '../api/search_entities_json.php?type=' + encodeURIComponent(config.type) + '&q=' + encodeURIComponent(query), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (spinner) spinner.style.display = 'none';
                if (xhr.status === 200) {
                    try {
                        var items = JSON.parse(xhr.responseText);
                        renderDropdown(items, query);
                    } catch (e) {
                        dropdown.style.display = 'none';
                    }
                }
            }
        };
        xhr.send();
    }

    function renderDropdown(items, query) {
        dropdown.innerHTML = '';
        var exactMatch = false;

        items.forEach(function(item) {
            if (item.name.toLowerCase() === query.toLowerCase()) {
                exactMatch = true;
            }
            var div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.innerHTML = '<span>' + escapeHtml(item.name) + '</span>' +
                            '<span style="font-size:0.75rem; color:#94a3b8; background:#f1f5f9; padding:2px 6px; border-radius:10px;">' + (item.count || 0) + ' libros</span>';
            div.addEventListener('click', function() {
                input.value = item.name;
                hidden.value = item.id;
                dropdown.style.display = 'none';
                updateBadge('found', 'Seleccionado');
            });
            dropdown.appendChild(div);
        });

        if (!exactMatch && query.length > 0) {
            var createDiv = document.createElement('div');
            createDiv.className = 'autocomplete-create-item';
            createDiv.innerHTML = '+ Crear ' + escapeHtml(config.singular) + ': <strong>"' + escapeHtml(query) + '"</strong>';
            createDiv.addEventListener('click', function() {
                hidden.value = '';
                dropdown.style.display = 'none';
                updateBadge('new', 'Se creará nuevo');
            });
            dropdown.appendChild(createDiv);
        }

        if (dropdown.children.length > 0) {
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    }

    input.addEventListener('input', function() {
        hidden.value = '';
        if (input.value.trim() === '') {
            updateBadge(null, '');
            dropdown.style.display = 'none';
            return;
        }
        updateBadge('new', 'Se creará nuevo');
        clearTimeout(timer);
        timer = setTimeout(doSearch, 250);
    });

    input.addEventListener('focus', function() {
        if (input.value.trim().length > 0) {
            doSearch();
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input && e.target !== dropdown && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

setupAutocomplete({
    inputId: 'authorSearchInput',
    hiddenId: 'selectedAuthorId',
    dropdownId: 'authorSearchResults',
    spinnerId: 'authorSpinner',
    badgeId: 'authorStatusBadge',
    type: 'authors',
    singular: 'Autor'
});

setupAutocomplete({
    inputId: 'publisherSearchInput',
    hiddenId: 'selectedPublisherId',
    dropdownId: 'publisherSearchResults',
    spinnerId: 'publisherSpinner',
    badgeId: 'publisherStatusBadge',
    type: 'publishers',
    singular: 'Editorial'
});

setupAutocomplete({
    inputId: 'categorySearchInput',
    hiddenId: 'selectedCategoryId',
    dropdownId: 'categorySearchResults',
    spinnerId: 'categorySpinner',
    badgeId: 'categoryStatusBadge',
    type: 'categories',
    singular: 'Categoría'
});


// Validación de fecha futura en el envío del formulario
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form');
    var fechaInput = document.getElementById('fechaPublicacion');
    if (form && fechaInput) {
        form.addEventListener('submit', function(e) {
            if (fechaInput.value) {
                var today = new Date().toISOString().split('T')[0];
                if (fechaInput.value > today) {
                    e.preventDefault();
                    alert('La fecha de publicación no puede ser una fecha futura. Ingrese la fecha de hoy o una fecha pasada.');
                    fechaInput.focus();
                    return false;
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
