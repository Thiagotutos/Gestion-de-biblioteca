<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// -------------------------------------------------------------
// 1. Procesar Préstamo con Límite de 1 Semana (7 Días)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $book_id = $_POST['book_id'] ?? null;
    $lector_nombre = trim($_POST['lector_nombre'] ?? '');
    $lector_documento = trim($_POST['lector_documento'] ?? '');
    $turno = $_POST['turno'] ?? null;
    $curso = trim($_POST['curso'] ?? '');
    $librarian_id = $_SESSION['user_id'];
    
    // Fecha límite por defecto: 1 semana a partir de hoy (7 días)
    $fecha_devolucion_esperada = date('Y-m-d', strtotime('+7 days'));
    
    if ($book_id && $lector_nombre) {
        $pdo->beginTransaction();
        try {
            // Validar que el libro exista y esté efectivamente disponible para prestar
            $stmt_avail = $pdo->prepare("SELECT titulo, estado FROM books WHERE id = ? FOR UPDATE");
            $stmt_avail->execute([$book_id]);
            $libro_actual = $stmt_avail->fetch();
            if (!$libro_actual) {
                throw new Exception("El libro seleccionado no existe.");
            }
            if ($libro_actual['estado'] !== 'Disponible') {
                throw new Exception("El libro '{$libro_actual['titulo']}' no está disponible (estado actual: {$libro_actual['estado']}).");
            }

            // Insertar transacción de préstamo con fecha esperada
            $stmt = $pdo->prepare("
                INSERT INTO transactions (book_id, user_id, lector_nombre, lector_documento, turno, curso, librarian_id, accion, fecha_devolucion_esperada) 
                VALUES (?, NULL, ?, ?, ?, ?, ?, 'Prestamo', ?)
            ");
            $stmt->execute([$book_id, $lector_nombre, $lector_documento, $turno, $curso, $librarian_id, $fecha_devolucion_esperada]);
            
            // Actualizar estado del libro
            $stmt2 = $pdo->prepare("UPDATE books SET estado = 'Prestado' WHERE id = ?");
            $stmt2->execute([$book_id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Libro prestado exitosamente. Fecha límite de devolución/renovación: " . date('d/m/Y', strtotime($fecha_devolucion_esperada)) . " (1 semana).";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error_msg'] = "Error al procesar el préstamo: " . $e->getMessage();
        }
        header('Location: issue_books.php');
        exit;
    }
}

// -------------------------------------------------------------
// 2. Procesar Renovación (Extiende el Límite por 1 Semana Más)
// -------------------------------------------------------------
if (isset($_GET['renew_book'])) {
    $book_id = (int)$_GET['renew_book'];
    
    $pdo->beginTransaction();
    try {
        // Verificar que el libro esté efectivamente prestado
        $stmt_bk = $pdo->prepare("SELECT titulo, estado FROM books WHERE id = ? FOR UPDATE");
        $stmt_bk->execute([$book_id]);
        $bk = $stmt_bk->fetch();
        if (!$bk || $bk['estado'] !== 'Prestado') {
            throw new Exception("Solo se pueden renovar libros que se encuentren actualmente prestados.");
        }

        // Obtener el préstamo activo actual
        $stmt_user = $pdo->prepare("
            SELECT id, fecha_devolucion_esperada, lector_nombre 
            FROM transactions 
            WHERE book_id = ? AND accion = 'Prestamo' 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt_user->execute([$book_id]);
        $last_trans = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        if ($last_trans) {
            // Calcular nueva fecha: 7 días a partir de hoy (o de la fecha límite actual si aún está vigente)
            $hoy = date('Y-m-d');
            $fecha_base = (!empty($last_trans['fecha_devolucion_esperada']) && $last_trans['fecha_devolucion_esperada'] > $hoy)
                ? strtotime($last_trans['fecha_devolucion_esperada'])
                : time();
            
            $nueva_fecha_esperada = date('Y-m-d', strtotime('+7 days', $fecha_base));
            
            $stmt_renew = $pdo->prepare("UPDATE transactions SET fecha_devolucion_esperada = ? WHERE id = ?");
            $stmt_renew->execute([$nueva_fecha_esperada, $last_trans['id']]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Préstamo renovado con éxito por 1 semana adicional. Nueva fecha límite: " . date('d/m/Y', strtotime($nueva_fecha_esperada)) . ".";
        } else {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_msg'] = "No se encontró un préstamo activo para renovar.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_msg'] = "Error al renovar el préstamo: " . $e->getMessage();
    }
    header('Location: issue_books.php');
    exit;
}

// -------------------------------------------------------------
// 3. Procesar Devolución
// -------------------------------------------------------------
if (isset($_GET['return_book'])) {
    $book_id = (int)$_GET['return_book'];
    $librarian_id = $_SESSION['user_id'];
    
    $pdo->beginTransaction();
    try {
        // Verificar que el libro exista y esté efectivamente prestado
        $stmt_bk = $pdo->prepare("SELECT titulo, estado FROM books WHERE id = ? FOR UPDATE");
        $stmt_bk->execute([$book_id]);
        $bk = $stmt_bk->fetch();
        if (!$bk || $bk['estado'] !== 'Prestado') {
            throw new Exception("El libro ya se encuentra disponible o no está registrado como prestado.");
        }

        // Encontrar quién tenía el libro y recuperar turno y curso
        $stmt_user = $pdo->prepare("SELECT id, user_id, lector_nombre, lector_documento, turno, curso FROM transactions WHERE book_id = ? AND accion = 'Prestamo' ORDER BY id DESC LIMIT 1");
        $stmt_user->execute([$book_id]);
        $last_trans = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        // Registrar devolución con fecha_devolucion_real
        $stmt = $pdo->prepare("INSERT INTO transactions (book_id, user_id, lector_nombre, lector_documento, turno, curso, librarian_id, accion, fecha_devolucion_real) VALUES (?, ?, ?, ?, ?, ?, ?, 'Devolucion', NOW())");
        $stmt->execute([
            $book_id, 
            $last_trans['user_id'] ?? null, 
            $last_trans['lector_nombre'] ?? null, 
            $last_trans['lector_documento'] ?? null, 
            $last_trans['turno'] ?? null, 
            $last_trans['curso'] ?? null, 
            $librarian_id
        ]);
        
        // Actualizar fecha_devolucion_real en el registro de préstamo original
        if (!empty($last_trans['id'])) {
            $stmt_update_orig = $pdo->prepare("UPDATE transactions SET fecha_devolucion_real = NOW() WHERE id = ?");
            $stmt_update_orig->execute([$last_trans['id']]);
        }

        // Actualizar estado del libro a Disponible
        $stmt2 = $pdo->prepare("UPDATE books SET estado = 'Disponible' WHERE id = ?");
        $stmt2->execute([$book_id]);
        
        $pdo->commit();
        $_SESSION['success_msg'] = "Libro devuelto exitosamente.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_msg'] = "Error al devolver el libro: " . $e->getMessage();
    }
    header('Location: issue_books.php');
    exit;
}

// Obtener datos para los select
$readers = $pdo->query("SELECT id, nombre, email FROM users ORDER BY nombre ASC")->fetchAll();

// Obtener historial de préstamos activos (con Número de inventario, fecha, límite de devolución, etc.)
$query = "
    SELECT t.id, b.titulo, b.isbn, b.id as book_id, 
           a.name as autor,
           COALESCE(t.lector_nombre, u.nombre) as lector, 
           t.lector_documento, t.turno, t.curso, t.fecha_hora,
           t.fecha_devolucion_esperada
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id IN (
        SELECT MAX(id) FROM transactions GROUP BY book_id
    ) AND t.accion = 'Prestamo' AND b.estado = 'Prestado'
    ORDER BY t.fecha_hora DESC
";
$prestamos = $pdo->query($query)->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Préstamos y Devoluciones</h2>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="split-layout">
    <!-- Formulario Prestar -->
    <div class="form-container form-pane">
        <h3>Registrar Préstamo</h3>
        <p style="font-size: 0.85rem; color: #666; margin-bottom: 15px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Plazo de préstamo: <strong>1 semana</strong> (renovable).
        </p>
        <form method="POST">
            <div class="form-group" style="position: relative;">
                <label>Libro Disponible (Título, Autor o Nº Inv.) *</label>
                <input type="text" id="bookSearchInput" class="form-control" placeholder="Escribe para buscar libro..." autocomplete="off" required>
                <input type="hidden" name="book_id" id="selectedBookId" required>
                <div id="bookSearchResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ccc; max-height:220px; overflow-y:auto; z-index:100; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px;"></div>
            </div>
            <div class="form-group">
                <label>Alumno/a (Lector) *</label>
                <input type="text" name="lector_nombre" class="form-control" required placeholder="Ej: Juan Pérez">
            </div>
            <div class="form-group">
                <label>Turno *</label>
                <select name="turno" class="form-control" required>
                    <option value="">-- Seleccionar Turno --</option>
                    <option value="Mañana">Mañana</option>
                    <option value="Tarde">Tarde</option>
                    <option value="Noche">Noche</option>
                </select>
            </div>
            <div class="form-group">
                <label>Curso *</label>
                <input type="text" name="curso" class="form-control" required placeholder="Ej: 3ro A, 1º 2da...">
            </div>
            <div class="form-group">
                <label>Documento / DNI (Opcional)</label>
                <input type="text" name="lector_documento" class="form-control" placeholder="Ej: 40123456">
            </div>
            <button type="submit" name="issue_book" class="btn btn-primary" style="width:100%">Registrar Préstamo (1 Semana)</button>
        </form>
    </div>

    <!-- Préstamos Activos -->
    <div class="data-pane">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
            <h3>Libros Actualmente Prestados (<?php echo count($prestamos); ?>)</h3>
            <span style="font-size: 0.85rem; color: #555;">Plazo de renovación: <strong>1 semana</strong></span>
        </div>
        <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nº Inv.</th>
                    <th>Prestado</th>
                    <th>Título</th>
                    <th>Alumno/a</th>
                    <th>Curso / Turno</th>
                    <th>Vencimiento / Límite</th>
                    <th style="text-align: center; min-width: 190px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestamos as $p): ?>
                <?php 
                    // Calcular vencimiento: si no está definido en BD, calcular 7 días desde fecha_hora
                    $fecha_prestamo = !empty($p['fecha_hora']) ? $p['fecha_hora'] : date('Y-m-d');
                    $fecha_limite = !empty($p['fecha_devolucion_esperada']) 
                        ? $p['fecha_devolucion_esperada'] 
                        : date('Y-m-d', strtotime('+7 days', strtotime($fecha_prestamo)));
                    
                    $hoy_ts = strtotime(date('Y-m-d'));
                    $limite_ts = strtotime($fecha_limite);
                    $dias_restantes = (int) round(($limite_ts - $hoy_ts) / 86400);
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['isbn'] ?? 'S/N'); ?></strong></td>
                    <td style="white-space: nowrap; font-size: 0.82rem; color: #555;">
                        <?php echo date('d/m/Y', strtotime($p['fecha_hora'])); ?><br>
                        <small style="color: #888;"><?php echo date('H:i', strtotime($p['fecha_hora'])); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($p['titulo']); ?></strong>
                        <?php if (!empty($p['autor'])): ?>
                            <small style="display:block; color:#666;"><?php echo htmlspecialchars($p['autor']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($p['lector']); ?></strong>
                        <?php if (!empty($p['lector_documento'])): ?>
                            <small style="display:block; font-size: 0.75rem; color:#777;">DNI: <?php echo htmlspecialchars($p['lector_documento']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span><?php echo htmlspecialchars($p['curso'] ?? '-'); ?></span><br>
                        <span class="status-badge" style="background: #0288d1; font-size: 0.75rem; padding: 2px 6px;"><?php echo htmlspecialchars($p['turno'] ?? '-'); ?></span>
                    </td>
                    <td style="white-space: nowrap;">
                        <strong style="font-size: 0.9rem;"><?php echo date('d/m/Y', $limite_ts); ?></strong><br>
                        <?php if ($dias_restantes < 0): ?>
                            <span class="status-badge" style="background-color: #d32f2f; color: white; font-weight: bold;">
                                Vencido (<?php echo abs($dias_restantes); ?>d)
                            </span>
                        <?php elseif ($dias_restantes === 0): ?>
                            <span class="status-badge" style="background-color: #f57c00; color: white; font-weight: bold;">
                                Vence Hoy
                            </span>
                        <?php elseif ($dias_restantes <= 2): ?>
                            <span class="status-badge" style="background-color: #ff9800; color: white;">
                                Vence en <?php echo $dias_restantes; ?> días
                            </span>
                        <?php else: ?>
                            <span class="status-badge" style="background-color: #2e7d32; color: white;">
                                En término (<?php echo $dias_restantes; ?>d)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap; text-align: center;">
                        <a href="issue_books.php?renew_book=<?php echo $p['book_id']; ?>" 
                           class="btn" 
                           style="background-color: #0288d1; color: white; padding: 6px 12px; font-size: 0.82rem; margin-right: 5px; display: inline-block; text-decoration: none; border-radius: 4px;" 
                           onclick="return confirm('¿Renovar préstamo por 1 semana más para: <?php echo addslashes($p['lector']); ?>?');"
                           title="Extiende la fecha límite de devolución por 7 días">
                            ↻ Renovar (+7d)
                        </a>
                        <a href="issue_books.php?return_book=<?php echo $p['book_id']; ?>" 
                           class="btn" 
                           style="background-color: #2e7d32; color: white; padding: 6px 12px; font-size: 0.82rem; display: inline-block; text-decoration: none; border-radius: 4px;" 
                           onclick="return confirm('¿Confirmar devolución del libro: <?php echo addslashes($p['titulo']); ?>?');">
                            ✓ Devolver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prestamos)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 25px; color: #666;">No hay préstamos activos en este momento.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookSearchInput = document.getElementById('bookSearchInput');
    const bookSearchResults = document.getElementById('bookSearchResults');
    const selectedBookId = document.getElementById('selectedBookId');
    let searchTimeout;

    if (bookSearchInput) {
        bookSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                bookSearchResults.style.display = 'none';
                selectedBookId.value = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`../api/search_books_json.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        bookSearchResults.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(book => {
                                const div = document.createElement('div');
                                div.style.padding = '10px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                const autorTxt = book.autor ? ` — ${book.autor}` : '';
                                div.textContent = `${book.titulo}${autorTxt} (Inv: ${book.isbn || 'S/N'})`;
                                
                                div.addEventListener('mouseover', () => div.style.background = '#f5f5f5');
                                div.addEventListener('mouseout', () => div.style.background = 'white');
                                
                                div.addEventListener('click', () => {
                                    bookSearchInput.value = `${book.titulo} (Inv: ${book.isbn || 'S/N'})`;
                                    selectedBookId.value = book.id;
                                    bookSearchResults.style.display = 'none';
                                });
                                
                                bookSearchResults.appendChild(div);
                            });
                            bookSearchResults.style.display = 'block';
                        } else {
                            bookSearchResults.innerHTML = '<div style="padding:10px; color:#666;">No se encontraron libros disponibles.</div>';
                            bookSearchResults.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error searching books:', error);
                    });
            }, 300);
        });

        // Ocultar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (e.target !== bookSearchInput && e.target !== bookSearchResults) {
                bookSearchResults.style.display = 'none';
            }
        });
        
        // Limpiar ID si el usuario borra el texto
        bookSearchInput.addEventListener('change', function() {
            if (this.value.trim() === '') {
                selectedBookId.value = '';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
