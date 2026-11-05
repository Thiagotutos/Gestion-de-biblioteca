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

// Calcular el último número de inventario registrado y el siguiente disponible
$stmt_max = $pdo->query("SELECT isbn FROM books WHERE isbn REGEXP '^[0-9]+$' ORDER BY CAST(isbn AS UNSIGNED) DESC LIMIT 1");
$last_isbn = $stmt_max->fetchColumn();
$siguiente_isbn = '000001';
if ($last_isbn !== false && $last_isbn !== null && $last_isbn !== '') {
    $digits = strlen($last_isbn);
    $next_val = (int)$last_isbn + 1;
    $siguiente_isbn = str_pad((string)$next_val, max(6, $digits), '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $author_id = !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null;
    $author_name = trim($_POST['author_name'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
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

    $cantidad = isset($_POST['cantidad']) ? max(1, min(100, (int)$_POST['cantidad'])) : 1;

    // Manejo de imagen
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['imagen']['type'];
        if (in_array($fileType, $allowed)) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = uniqid('book_') . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/books/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagen);
        } else {
            $error = "Formato de imagen no permitido. Use JPG, PNG, GIF o WebP.";
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

            // Si el campo de inventario vino vacío, asignar el sugerido
            if ($isbn === '') {
                $isbn = $siguiente_isbn;
            }

            // Detectar si el Nº de Inventario tiene formato numérico para correlativo
            $has_numeric_inv = false;
            $inv_prefix = '';
            $inv_num = 0;
            $inv_len = 0;
            if ($isbn !== '' && preg_match('/^(.*?)(\d+)$/', $isbn, $matches)) {
                $has_numeric_inv = true;
                $inv_prefix = $matches[1];
                $inv_num = (int)$matches[2];
                $inv_len = strlen($matches[2]);
            }

            // Validar que ninguno de los inventarios a insertar esté repetido en la BD
            for ($i = 0; $i < $cantidad; $i++) {
                $test_isbn = null;
                if ($isbn !== '') {
                    if ($i === 0) {
                        $test_isbn = $isbn;
                    } else {
                        if ($has_numeric_inv) {
                            $next_num = $inv_num + $i;
                            $test_isbn = $inv_prefix . str_pad((string)$next_num, $inv_len, '0', STR_PAD_LEFT);
                        } else {
                            $test_isbn = $isbn . ' (' . ($i + 1) . ')';
                        }
                    }
                }

                if (!empty($test_isbn)) {
                    $stmt_check = $pdo->prepare("SELECT id, titulo FROM books WHERE isbn = ? LIMIT 1");
                    $stmt_check->execute([$test_isbn]);
                    $duplicado = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    if ($duplicado) {
                        throw new Exception("El Nº de Inventario '{$test_isbn}' ya está asignado al libro '{$duplicado['titulo']}' (ID: {$duplicado['id']}). Por favor elige otro número para evitar duplicados.");
                    }
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO books (
                    titulo, author_id, isbn, publisher_id, lugar_editorial, 
                    fecha_publicacion, category_id, rack_id, imagen, notas, 
                    observacion, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < $cantidad; $i++) {
                $current_isbn = null;
                if ($isbn !== '') {
                    if ($i === 0) {
                        $current_isbn = $isbn;
                    } else {
                        if ($has_numeric_inv) {
                            $next_num = $inv_num + $i;
                            $current_isbn = $inv_prefix . str_pad((string)$next_num, $inv_len, '0', STR_PAD_LEFT);
                        } else {
                            $current_isbn = $isbn . ' (' . ($i + 1) . ')';
                        }
                    }
                }

                $stmt->execute([
                    $titulo, 
                    $author_id, 
                    $current_isbn, 
                    $publisher_id, 
                    $lugar_editorial, 
                    $fecha_publicacion, 
                    $category_id, 
                    $rack_id, 
                    $imagen, 
                    $notas, 
                    $observacion, 
                    $estado
                ]);
            }

            $pdo->commit();

            if ($cantidad > 1) {
                $_SESSION['success_msg'] = "Se registraron exitosamente {$cantidad} ejemplares de '{$titulo}'.";
            } else {
                $_SESSION['success_msg'] = "Libro agregado exitosamente con Nº de Inventario '{$isbn}'.";
            }
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
    <h2>Agregar Nuevo Libro</h2>
    <a href="books.php" class="btn btn-primary" style="background-color: #666;">Volver</a>
</div>

<div class="form-container" style="max-width: 800px; width: 100%;">
    <?php if (isset($error)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label>Título *</label>
            <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" required placeholder="Ej: Rayuela, Cien años de soledad...">
        </div>

        <!-- Buscador en tiempo real: Autor -->
        <div class="form-group" style="position: relative;">
            <label style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                <span>Autor *</span>
                <span id="authorStatusBadge" class="status-badge" style="display: none; font-size: 0.75rem; padding: 2px 6px;"></span>
            </label>
            <div style="position: relative;">
                <input type="text" id="authorSearchInput" name="author_name" class="form-control" placeholder="Escribe para buscar o ingresar autor..." value="<?php echo htmlspecialchars($_POST['author_name'] ?? ''); ?>" autocomplete="off" required>
                <input type="hidden" name="author_id" id="selectedAuthorId" value="<?php echo htmlspecialchars($_POST['author_id'] ?? ''); ?>">
                <span id="authorSpinner" class="search-spinner" style="display: none;"></span>
            </div>
            <div id="authorSearchResults" class="autocomplete-dropdown" style="display: none;"></div>
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">
                🔍 Escribe el nombre del autor. Si no existe en la biblioteca, se creará automáticamente al guardar.
            </small>
        </div>

        <!-- Cantidad de Ejemplares -->
        <div class="form-group" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 15px; margin-bottom: 18px;">
            <label style="display: flex; align-items: center; justify-content: space-between; font-weight: 600; color: #1e293b; flex-wrap: wrap; gap: 4px;">
                <span>Cantidad de Ejemplares (Copias) *</span>
                <span style="font-size: 0.8rem; font-weight: normal; color: #64748b;">(Ej: donación de varios libros iguales)</span>
            </label>
            <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px; flex-wrap: wrap;">
                <input type="number" name="cantidad" id="inputCantidad" class="form-control" min="1" max="100" value="<?php echo (int)($_POST['cantidad'] ?? 1); ?>" style="max-width: 120px; font-weight: bold; font-size: 1.05rem;" required>
                <span id="cantidadHelp" style="font-size: 0.88rem; color: #555;">1 libro individual.</span>
            </div>
            <small style="display: block; margin-top: 8px; color: #0288d1; font-size: 0.8rem;">
                💡 Si ingresas 2 o más ejemplares, el sistema asignará correlativamente los números de inventario (ej: <code><?php echo htmlspecialchars($siguiente_isbn); ?></code>, <code><?php echo htmlspecialchars(str_pad((string)((int)$siguiente_isbn + 1), strlen($siguiente_isbn), '0', STR_PAD_LEFT)); ?></code>...).
            </small>
        </div>

        <!-- Nº de Inventario con sugerencia automática del último registrado -->
        <div class="form-group">
            <label style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                <span>Nº Inventario *</span>
                <span id="isbnStatusBadge" class="status-badge" style="display: none; font-size: 0.75rem; padding: 2px 6px;"></span>
            </label>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="isbn" id="isbnInput" class="form-control" value="<?php echo htmlspecialchars($_POST['isbn'] ?? $siguiente_isbn); ?>" placeholder="Ej: <?php echo htmlspecialchars($siguiente_isbn); ?>" style="font-weight: bold; max-width: 260px; min-width: 150px; flex: 1;" required>
                <button type="button" id="btnSugerirIsbn" class="btn" style="background: #e2e8f0; color: #334155; font-size: 0.8rem; padding: 8px 12px; white-space: nowrap;" title="Restablecer al siguiente disponible">
                    ↻ Siguiente: <?php echo htmlspecialchars($siguiente_isbn); ?>
                </button>
            </div>
            <div id="isbnFeedback" style="display: none; margin-top: 6px; font-size: 0.85rem; padding: 6px 10px; border-radius: 4px;"></div>
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">
                Último inventario registrado en la base de datos: <strong><?php echo htmlspecialchars($last_isbn ?: 'Ninguno'); ?></strong>. Sugerido automáticamente: <strong><?php echo htmlspecialchars($siguiente_isbn); ?></strong> para evitar duplicados.
            </small>
        </div>

        <!-- Buscador en tiempo real: Editorial -->
        <div class="form-group" style="position: relative;">
            <label style="display: flex; justify-content: space-between; align-items: center;">
                <span>Editorial</span>
                <span id="publisherStatusBadge" class="status-badge" style="display: none; font-size: 0.75rem; padding: 2px 6px;"></span>
            </label>
            <div style="position: relative;">
                <input type="text" id="publisherSearchInput" name="publisher_name" class="form-control" placeholder="Escribe para buscar o ingresar editorial..." value="<?php echo htmlspecialchars($_POST['publisher_name'] ?? ''); ?>" autocomplete="off">
                <input type="hidden" name="publisher_id" id="selectedPublisherId" value="<?php echo htmlspecialchars($_POST['publisher_id'] ?? ''); ?>">
                <span id="publisherSpinner" class="search-spinner" style="display: none;"></span>
            </div>
            <div id="publisherSearchResults" class="autocomplete-dropdown" style="display: none;"></div>
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">
                🔍 Escribe la editorial. Si no existe, se creará automáticamente.
            </small>
        </div>

        <div class="form-group">
            <label>Lugar de la Editorial</label>
            <input type="text" name="lugar_editorial" class="form-control" placeholder="Ej: Buenos Aires, Madrid..." value="<?php echo htmlspecialchars($_POST['lugar_editorial'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>Fecha de Publicación</label>
            <input type="date" name="fecha_publicacion" id="fechaPublicacion" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['fecha_publicacion'] ?? ''); ?>">
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 3px;">Solo se permiten fechas pasadas o la fecha de hoy.</small>
        </div>

        <!-- Buscador en tiempo real: Categoría -->
        <div class="form-group" style="position: relative;">
            <label style="display: flex; justify-content: space-between; align-items: center;">
                <span>Categoría</span>
                <span id="categoryStatusBadge" class="status-badge" style="display: none; font-size: 0.75rem; padding: 2px 6px;"></span>
            </label>
            <div style="position: relative;">
                <input type="text" id="categorySearchInput" name="category_name" class="form-control" placeholder="Escribe para buscar o ingresar categoría..." value="<?php echo htmlspecialchars($_POST['category_name'] ?? ''); ?>" autocomplete="off">
                <input type="hidden" name="category_id" id="selectedCategoryId" value="<?php echo htmlspecialchars($_POST['category_id'] ?? ''); ?>">
                <span id="categorySpinner" class="search-spinner" style="display: none;"></span>
            </div>
            <div id="categorySearchResults" class="autocomplete-dropdown" style="display: none;"></div>
            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">
                🔍 Escribe la categoría (ej: Literatura, Novela, Historia). Si no existe, se creará automáticamente.
            </small>
        </div>

        <div class="form-group">
            <label>Portada del Libro</label>
            <div class="image-upload-area" id="uploadArea">
                <input type="file" name="imagen" id="imagenInput" class="image-input" accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="upload-placeholder" id="uploadPlaceholder">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                    <p>Haz clic o arrastra una imagen aquí</p>
                    <span>JPG, PNG, GIF o WebP (máx. 5MB)</span>
                </div>
                <img id="imagePreview" class="image-preview" style="display:none;" alt="Vista previa">
            </div>
        </div>

        <div class="form-group">
            <label>Notas (opcional)</label>
            <textarea name="notas" class="form-control" rows="3" placeholder="Notas internas..."><?php echo htmlspecialchars($_POST['notas'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Observación (opcional)</label>
            <textarea name="observacion" class="form-control" rows="3" placeholder="Observaciones adicionales..."><?php echo htmlspecialchars($_POST['observacion'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Estado Inicial</label>
            <select name="estado" class="form-control">
                <option value="Disponible" <?php echo (($_POST['estado'] ?? '') === 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                <option value="Prestado" <?php echo (($_POST['estado'] ?? '') === 'Prestado') ? 'selected' : ''; ?>>Prestado</option>
                <option value="Extraviado" <?php echo (($_POST['estado'] ?? '') === 'Extraviado') ? 'selected' : ''; ?>>Extraviado</option>
            </select>
        </div>

        <button type="submit" id="btnGuardarLibro" class="btn btn-primary" style="padding: 10px 24px; font-size: 1rem;">Guardar Libro</button>
    </form>
</div>

<script>
// ============================================================================
// 1. Componente Reutilizable de Autocompletado y Creación en Tiempo Real
// ============================================================================
function setupAutocomplete(opts) {
    var input = document.getElementById(opts.inputId);
    var hidden = document.getElementById(opts.hiddenId);
    var dropdown = document.getElementById(opts.dropdownId);
    var spinner = document.getElementById(opts.spinnerId);
    var badge = document.getElementById(opts.badgeId);
    var timer = null;
    var xhr = null;

    if (!input || !dropdown) return;

    function updateBadge(type, text) {
        if (!badge) return;
        if (!text) {
            badge.style.display = 'none';
            return;
        }
        badge.style.display = 'inline-block';
        if (type === 'existing') {
            badge.style.background = '#2e7d32';
            badge.style.color = '#fff';
            badge.textContent = '✓ ' + text;
        } else {
            badge.style.background = '#0288d1';
            badge.style.color = '#fff';
            badge.textContent = '✨ ' + text;
        }
    }

    function doSearch() {
        var q = input.value.trim();
        if (spinner) spinner.style.display = 'inline-block';

        if (xhr) xhr.abort();
        xhr = new XMLHttpRequest();
        xhr.open('GET', '../api/search_entities_json.php?type=' + encodeURIComponent(opts.type) + '&q=' + encodeURIComponent(q), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (spinner) spinner.style.display = 'none';
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        renderDropdown(data, q);
                    } catch (e) {
                        console.error('Error parseando JSON:', e);
                    }
                }
            }
        };
        xhr.send();
    }

    function renderDropdown(items, q) {
        dropdown.innerHTML = '';
        var exactMatch = false;

        if (items && items.length > 0) {
            items.forEach(function(item) {
                if (item.name.toLowerCase() === q.toLowerCase()) {
                    exactMatch = true;
                }
                var div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = '<span>' + escapeHtml(item.name) + '</span><small style="color: #94a3b8; font-size: 0.75rem;">ID: #' + item.id + '</small>';
                div.addEventListener('click', function() {
                    input.value = item.name;
                    hidden.value = item.id;
                    dropdown.style.display = 'none';
                    updateBadge('existing', 'Registrado (#' + item.id + ')');
                });
                dropdown.appendChild(div);
            });
        }

        // Si se escribió algo y no es coincidencia exacta, ofrecer crearlo
        if (q.length > 0 && !exactMatch) {
            var createDiv = document.createElement('div');
            createDiv.className = 'autocomplete-create-item';
            createDiv.innerHTML = '➕ Crear nuevo/a ' + opts.singular + ': "<strong>' + escapeHtml(q) + '</strong>"';
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
        hidden.value = ''; // Resetear ID al tipear algo nuevo
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

// Inicializar Autocompletado para Autor, Editorial y Categoría
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


// ============================================================================
// 2. Validación y Sugerencia de Número de Inventario en Tiempo Real
// ============================================================================
(function() {
    var isbnInput = document.getElementById('isbnInput');
    var btnSugerir = document.getElementById('btnSugerirIsbn');
    var feedback = document.getElementById('isbnFeedback');
    var badge = document.getElementById('isbnStatusBadge');
    var siguienteIsbn = '<?php echo htmlspecialchars($siguiente_isbn); ?>';
    var timer = null;
    var xhr = null;

    if (btnSugerir && isbnInput) {
        btnSugerir.addEventListener('click', function() {
            isbnInput.value = siguienteIsbn;
            checkIsbn(siguienteIsbn);
        });
    }

    function checkIsbn(val) {
        val = val.trim();
        if (!val) {
            feedback.style.display = 'none';
            badge.style.display = 'none';
            return;
        }

        if (xhr) xhr.abort();
        xhr = new XMLHttpRequest();
        xhr.open('GET', '../api/search_entities_json.php?type=check_isbn&isbn=' + encodeURIComponent(val), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    feedback.style.display = 'block';
                    badge.style.display = 'inline-block';
                    if (res.exists) {
                        badge.style.background = '#d32f2f';
                        badge.style.color = '#fff';
                        badge.textContent = '✗ Ya en uso';
                        feedback.style.background = '#fee2e2';
                        feedback.style.color = '#991b1b';
                        feedback.style.border = '1px solid #f87171';
                        feedback.innerHTML = '⚠️ <strong>Inventario Duplicado:</strong> Este número ya está asignado al libro <em>"' + escapeHtml(res.book.titulo) + '"</em> (ID: #' + res.book.id + '). Elige otro número o presiona el botón para usar el siguiente disponible.';
                    } else {
                        badge.style.background = '#2e7d32';
                        badge.style.color = '#fff';
                        badge.textContent = '✓ Disponible';
                        feedback.style.background = '#ecfdf5';
                        feedback.style.color = '#065f46';
                        feedback.style.border = '1px solid #a7f3d0';
                        feedback.innerHTML = '✓ <strong>Disponible:</strong> Este número de inventario no está en uso y es válido.';
                    }
                } catch (e) {
                    console.error('Error parseando respuesta check_isbn:', e);
                }
            }
        };
        xhr.send();
    }

    if (isbnInput) {
        isbnInput.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                checkIsbn(isbnInput.value);
            }, 300);
        });
        // Comprobar valor inicial
        if (isbnInput.value) {
            checkIsbn(isbnInput.value);
        }
    }
})();


// ============================================================================
// 3. Cantidad de Ejemplares y Adaptación de Botón
// ============================================================================
var inputCantidad = document.getElementById('inputCantidad');
var cantidadHelp = document.getElementById('cantidadHelp');
var btnGuardar = document.getElementById('btnGuardarLibro');

if (inputCantidad && cantidadHelp) {
    function updateCantidadDisplay() {
        var val = parseInt(inputCantidad.value, 10) || 1;
        if (val > 1) {
            cantidadHelp.textContent = 'Se crearán ' + val + ' ejemplares idénticos en el sistema.';
            cantidadHelp.style.color = '#0288d1';
            cantidadHelp.style.fontWeight = 'bold';
            if (btnGuardar) btnGuardar.textContent = 'Guardar ' + val + ' Ejemplares';
        } else {
            cantidadHelp.textContent = '1 libro individual.';
            cantidadHelp.style.color = '#555';
            cantidadHelp.style.fontWeight = 'normal';
            if (btnGuardar) btnGuardar.textContent = 'Guardar Libro';
        }
    }
    inputCantidad.addEventListener('input', updateCantidadDisplay);
    inputCantidad.addEventListener('change', updateCantidadDisplay);
    updateCantidadDisplay();
}


// ============================================================================
// 4. Subida y Vista Previa de Imagen
// ============================================================================
var uploadArea = document.getElementById('uploadArea');
var imageInput = document.getElementById('imagenInput');
var imagePreview = document.getElementById('imagePreview');
var placeholder = document.getElementById('uploadPlaceholder');

if (uploadArea && imageInput) {
    uploadArea.addEventListener('click', function() { imageInput.click(); });
    uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('drag-over'); });
    uploadArea.addEventListener('dragleave', function() { uploadArea.classList.remove('drag-over'); });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            imageInput.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });

    imageInput.addEventListener('change', function(e) {
        if (e.target.files.length) showPreview(e.target.files[0]);
    });

    function showPreview(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

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
