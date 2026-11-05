<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['user_role'] ?? '', ['Administrador', 'Bibliotecario'])) {
    header('Location: dashboard.php');
    exit;
}

// -------------------------------------------------------------
// 1. Verificación suave de la columna responsable en transactions
// -------------------------------------------------------------
try {
    $pdo->query("SELECT responsable FROM transactions LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN responsable VARCHAR(255) NULL AFTER curso");
    } catch (Exception $e2) {}
}

// -------------------------------------------------------------
// 2. Procesar Préstamo con Límite de 1 Semana (7 Días)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $lector_nombre = trim($_POST['lector_nombre'] ?? '');
    $lector_documento = trim($_POST['lector_documento'] ?? '');
    $turno = $_POST['turno'] ?? null;
    $curso = trim($_POST['curso'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $librarian_id = $_SESSION['user_id'];
    
    // Fecha límite por defecto: 1 semana a partir de hoy (7 días)
    $fecha_devolucion_esperada = date('Y-m-d', strtotime('+7 days'));
    
    // Recolectar libros solicitados
    $libros_pedidos = [];
    if (!empty($_POST['selected_books']) && is_array($_POST['selected_books'])) {
        foreach ($_POST['selected_books'] as $b_id => $data) {
            $b_id = (int)$b_id;
            $cant = max(1, (int)($data['cantidad'] ?? 1));
            if ($b_id > 0) {
                $libros_pedidos[] = ['id' => $b_id, 'cantidad' => $cant];
            }
        }
    } elseif (!empty($_POST['book_id'])) {
        $single_id = (int)$_POST['book_id'];
        $single_cant = max(1, (int)($_POST['cantidad'] ?? 1));
        if ($single_id > 0) {
            $libros_pedidos[] = ['id' => $single_id, 'cantidad' => $single_cant];
        }
    }

    $total_libros = 0;
    foreach ($libros_pedidos as $lp) {
        $total_libros += $lp['cantidad'];
    }

    if (empty($libros_pedidos)) {
        $_SESSION['error_msg'] = "Debes seleccionar al menos un libro disponible para prestar.";
        header('Location: issue_books.php');
        exit;
    }

    // Regla de negocio: si lleva más de 1 libro/copia, es obligatorio el responsable
    if ($total_libros > 1 && empty($responsable)) {
        $_SESSION['error_msg'] = "Para préstamos de más de 1 libro o copia, es obligatorio especificar el Docente o Adulto Responsable.";
        header('Location: issue_books.php');
        exit;
    }

    if ($lector_nombre !== '') {
        $pdo->beginTransaction();
        try {
            $prestados_count = 0;

            foreach ($libros_pedidos as $item) {
                $base_id = $item['id'];
                $cant_solicitada = $item['cantidad'];

                // Validar libro base
                $stmt_base = $pdo->prepare("SELECT id, titulo, estado FROM books WHERE id = ? FOR UPDATE");
                $stmt_base->execute([$base_id]);
                $libro_base = $stmt_base->fetch();
                if (!$libro_base) {
                    throw new Exception("El libro seleccionado con ID #{$base_id} no existe.");
                }
                if ($libro_base['estado'] !== 'Disponible') {
                    throw new Exception("El ejemplar '{$libro_base['titulo']}' ya no se encuentra disponible.");
                }

                $ids_a_prestar = [$base_id];

                // Si pidió más de 1 copia del mismo título, buscar ejemplares hermanos disponibles
                if ($cant_solicitada > 1) {
                    $stmt_extra = $pdo->prepare("
                        SELECT id FROM books 
                        WHERE titulo = ? AND estado = 'Disponible' AND id != ? 
                        LIMIT ? FOR UPDATE
                    ");
                    $stmt_extra->execute([$libro_base['titulo'], $base_id, $cant_solicitada - 1]);
                    $extras = $stmt_extra->fetchAll(PDO::FETCH_COLUMN);

                    if (count($extras) < ($cant_solicitada - 1)) {
                        $disp = count($extras) + 1;
                        throw new Exception("No hay suficientes copias disponibles de '{$libro_base['titulo']}'. Solicitadas: {$cant_solicitada}, Disponibles: {$disp}.");
                    }

                    foreach ($extras as $extra_id) {
                        $ids_a_prestar[] = (int)$extra_id;
                    }
                }

                // Registrar transacción y actualizar estado a Prestado para cada copia
                foreach ($ids_a_prestar as $bid) {
                    $stmt_trans = $pdo->prepare("
                        INSERT INTO transactions (book_id, user_id, lector_nombre, lector_documento, turno, curso, responsable, librarian_id, accion, fecha_devolucion_esperada) 
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 'Prestamo', ?)
                    ");
                    $stmt_trans->execute([
                        $bid, 
                        $lector_nombre, 
                        $lector_documento, 
                        $turno, 
                        $curso, 
                        $responsable ?: null, 
                        $librarian_id, 
                        $fecha_devolucion_esperada
                    ]);

                    $stmt_bk_upd = $pdo->prepare("UPDATE books SET estado = 'Prestado' WHERE id = ?");
                    $stmt_bk_upd->execute([$bid]);

                    $prestados_count++;
                }
            }

            $pdo->commit();

            $info_resp = !empty($responsable) ? " (Responsable: {$responsable})" : "";
            $_SESSION['success_msg'] = "Préstamo registrado exitosamente: {$prestados_count} ejemplar(es) a {$lector_nombre}{$info_resp}. Fecha límite: " . date('d/m/Y', strtotime($fecha_devolucion_esperada)) . " (1 semana).";
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

        // Encontrar quién tenía el libro y recuperar turno, curso y responsable
        $stmt_user = $pdo->prepare("SELECT id, user_id, lector_nombre, lector_documento, turno, curso, responsable FROM transactions WHERE book_id = ? AND accion = 'Prestamo' ORDER BY id DESC LIMIT 1");
        $stmt_user->execute([$book_id]);
        $last_trans = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        // Registrar devolución con fecha_devolucion_real
        $stmt = $pdo->prepare("INSERT INTO transactions (book_id, user_id, lector_nombre, lector_documento, turno, curso, responsable, librarian_id, accion, fecha_devolucion_real) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Devolucion', NOW())");
        $stmt->execute([
            $book_id, 
            $last_trans['user_id'] ?? null, 
            $last_trans['lector_nombre'] ?? null, 
            $last_trans['lector_documento'] ?? null, 
            $last_trans['turno'] ?? null, 
            $last_trans['curso'] ?? null, 
            $last_trans['responsable'] ?? null,
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
           t.lector_documento, t.turno, t.curso, t.responsable, t.fecha_hora,
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

// Obtener historial completo de logs / transacciones (hasta 250 registros más recientes)
$query_logs = "
    SELECT t.id, t.accion, t.fecha_hora, t.fecha_devolucion_esperada, t.fecha_devolucion_real,
           COALESCE(t.lector_nombre, u.nombre, 'No especificado') as lector,
           t.lector_documento, t.turno, t.curso, t.responsable,
           b.id as book_id, b.titulo, b.isbn,
           a.name as autor,
           lib.nombre as bibliotecario
    FROM transactions t
    LEFT JOIN books b ON t.book_id = b.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users lib ON t.librarian_id = lib.id
    ORDER BY t.fecha_hora DESC, t.id DESC
    LIMIT 250
";
$logs_movimientos = $pdo->query($query_logs)->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Préstamos Biblioteca</h2>
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

        <form method="POST" id="formIssueBook">
            <!-- Buscador predictivo de libros -->
            <div class="form-group" style="position: relative; margin-bottom: 12px;">
                <label><strong>Buscar y Agregar Libro (Título, Autor o Nº Inv.) *</strong></label>
                <input type="text" id="bookSearchInput" class="form-control" placeholder="Escribe el nombre del libro o inventario para agregar..." autocomplete="off">
                <div id="bookSearchResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ccc; max-height:220px; overflow-y:auto; z-index:100; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px;"></div>
            </div>

            <!-- Lista interactiva de libros seleccionados -->
            <div id="selectedBooksContainer" style="margin-bottom: 18px;">
                <div id="selectedBooksList" style="display: flex; flex-direction: column; gap: 8px;">
                    <div id="noBooksSelectedNotice" style="padding: 12px 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; color: #64748b; font-size: 0.88rem;">
                        🔍 Busca y selecciona uno o más libros arriba para agregarlos a este préstamo.
                    </div>
                </div>
            </div>

            <div class="form-grid-fields">
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

                <!-- Campo dinámico Responsable: Aparece automáticamente si lleva más de 1 libro/copia -->
                <div class="form-group" id="divResponsable" style="display: none; background: #fff8e1; border: 1px solid #ffe082; padding: 10px 14px; border-radius: 6px;">
                    <label style="color: #b78103; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                        <span>⚠️</span> Docente / Adulto Responsable *
                    </label>
                    <input type="text" name="responsable" id="inputResponsable" class="form-control" placeholder="Ej: Prof. Mariana Fernández">
                    <small style="color: #795548; font-size: 0.8rem; display: block; margin-top: 4px;">
                        Obligatorio porque se retiran <strong><span id="txtCantLibros">2</span> libros/copias</strong>.
                    </small>
                </div>
            </div>

            <div style="margin-top: 15px;">
                <button type="submit" name="issue_book" id="btnSubmitLoan" class="btn btn-primary" style="padding: 10px 24px;">
                    Registrar Préstamo (<span id="btnTxtCant">0 libros</span> - 1 Semana)
                </button>
            </div>
        </form>
    </div>

    <!-- Contenedor de Datos: Pestañas de Préstamos Activos y Logs -->
    <div class="data-pane">
        <!-- Barra de Pestañas -->
        <div class="tabs-bar">
            <button type="button" class="tab-btn active" id="tabActivosBtn" onclick="switchLoanTab('activos')">
                <span>📌</span> Préstamos Activos (<?php echo count($prestamos); ?>)
            </button>
            <button type="button" class="tab-btn" id="tabLogsBtn" onclick="switchLoanTab('logs')">
                <span>📜</span> Logs / Historial de Movimientos (<?php echo count($logs_movimientos); ?>)
            </button>
        </div>

        <!-- Pestaña 1: Préstamos Activos -->
        <div id="paneActivos">
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
                            <?php if (!empty($p['responsable'])): ?>
                                <small style="display:inline-block; font-size: 0.76rem; color:#0288d1; background:#e0f2fe; padding:1px 6px; border-radius:3px; margin-top:3px;">
                                    <strong>Resp:</strong> <?php echo htmlspecialchars($p['responsable']); ?>
                                </small>
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

        <!-- Pestaña 2: Logs / Historial de Movimientos -->
        <div id="paneLogs" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                <h3>Registro de Auditoría y Movimientos</h3>
                <span style="font-size: 0.85rem; color: #555;">Mostrando los últimos <strong><?php echo count($logs_movimientos); ?></strong> movimientos</span>
            </div>
            <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha / Hora</th>
                        <th>Acción</th>
                        <th>Nº Inv.</th>
                        <th>Libro</th>
                        <th>Alumno / Lector</th>
                        <th>Curso / Turno</th>
                        <th>Detalle Plazo / Devolución</th>
                        <th>Registrado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs_movimientos as $log): ?>
                    <tr>
                        <td style="white-space: nowrap; font-size: 0.82rem; color: #555;">
                            <?php echo date('d/m/Y', strtotime($log['fecha_hora'])); ?><br>
                            <small style="color: #888;"><?php echo date('H:i', strtotime($log['fecha_hora'])); ?> hs</small>
                        </td>
                        <td style="white-space: nowrap;">
                            <?php if ($log['accion'] === 'Prestamo'): ?>
                                <span class="status-badge" style="background-color: #0288d1; color: white;">Préstamo</span>
                            <?php elseif ($log['accion'] === 'Devolucion'): ?>
                                <span class="status-badge" style="background-color: #2e7d32; color: white;">Devuelto</span>
                            <?php else: ?>
                                <span class="status-badge" style="background-color: #64748b; color: white;"><?php echo htmlspecialchars($log['accion']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($log['isbn'] ?? 'S/N'); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($log['titulo'] ?? '[Libro]'); ?></strong>
                            <?php if (!empty($log['autor'])): ?>
                                <small style="display:block; color:#666;"><?php echo htmlspecialchars($log['autor']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($log['lector']); ?></strong>
                            <?php if (!empty($log['lector_documento'])): ?>
                                <small style="display:block; font-size: 0.75rem; color:#777;">DNI: <?php echo htmlspecialchars($log['lector_documento']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($log['responsable'])): ?>
                                <small style="display:inline-block; font-size: 0.76rem; color:#0288d1; background:#e0f2fe; padding:1px 6px; border-radius:3px; margin-top:3px;">
                                    <strong>Resp:</strong> <?php echo htmlspecialchars($log['responsable']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span><?php echo htmlspecialchars($log['curso'] ?? '-'); ?></span><br>
                            <?php if (!empty($log['turno'])): ?>
                                <span class="status-badge" style="background: #e2e8f0; color: #334155; font-size: 0.75rem; padding: 1px 5px;"><?php echo htmlspecialchars($log['turno']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.82rem; white-space: nowrap;">
                            <?php if ($log['accion'] === 'Devolucion'): ?>
                                <span style="color: #2e7d32; font-weight: 600;">
                                    ✓ Entregado: <?php echo !empty($log['fecha_devolucion_real']) ? date('d/m/Y H:i', strtotime($log['fecha_devolucion_real'])) : date('d/m/Y', strtotime($log['fecha_hora'])); ?>
                                </span>
                            <?php elseif (!empty($log['fecha_devolucion_esperada'])): ?>
                                <span style="color: #64748b;">
                                    Límite: <?php echo date('d/m/Y', strtotime($log['fecha_devolucion_esperada'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.82rem; color: #475569;">
                            <?php echo htmlspecialchars($log['bibliotecario'] ?? 'Sistema'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs_movimientos)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 25px; color: #666;">No hay movimientos registrados en el historial de logs.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script>
// Estado de libros seleccionados en la transacción actual
const selectedBooks = {};

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderSelectedBooks() {
    const list = document.getElementById('selectedBooksList');
    const divResponsable = document.getElementById('divResponsable');
    const inputResponsable = document.getElementById('inputResponsable');
    const txtCantLibros = document.getElementById('txtCantLibros');
    const btnTxtCant = document.getElementById('btnTxtCant');

    if (!list) return;

    const bookIds = Object.keys(selectedBooks);

    if (bookIds.length === 0) {
        list.innerHTML = `
            <div id="noBooksSelectedNotice" style="padding: 12px 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; color: #64748b; font-size: 0.88rem;">
                🔍 Busca y selecciona uno o más libros arriba para agregarlos a este préstamo.
            </div>
        `;
        if (divResponsable) divResponsable.style.display = 'none';
        if (inputResponsable) {
            inputResponsable.required = false;
        }
        if (btnTxtCant) btnTxtCant.textContent = '0 libros';
        return;
    }

    let html = '';
    let totalEjemplares = 0;

    bookIds.forEach(id => {
        const b = selectedBooks[id];
        const cant = parseInt(b.cantidad, 10) || 1;
        totalEjemplares += cant;
        const maxDisp = b.maxCopias || 1;
        const autorTxt = b.autor ? `<span style="color:#64748b; font-size:0.82rem; display:block;">${escapeHtml(b.autor)}</span>` : '';

        html += `
            <div class="selected-book-card" style="display:flex; align-items:center; justify-content:space-between; gap:12px; background:#fff; border:1px solid #cbd5e1; border-left:4px solid var(--color-primary); border-radius:6px; padding:10px 14px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <strong style="color:#1e293b; font-size:0.95rem;">${escapeHtml(b.titulo)}</strong>
                    ${autorTxt}
                    <span style="font-size:0.78rem; color:#0288d1; font-weight:600;">Nº Inv: ${escapeHtml(b.isbn || 'S/N')}</span>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                    <label style="font-size:0.84rem; color:#475569; margin:0; font-weight:600; white-space:nowrap;">Copias:</label>
                    <input type="number" 
                           name="selected_books[${b.id}][cantidad]" 
                           value="${cant}" 
                           min="1" 
                           max="${maxDisp}" 
                           class="form-control" 
                           style="width:72px; padding:4px 8px; font-weight:bold; text-align:center;" 
                           onchange="updateBookCopies(${b.id}, this.value)"
                           oninput="updateBookCopies(${b.id}, this.value)">
                    <span style="font-size:0.8rem; color:#64748b; white-space:nowrap;">(de ${maxDisp} disp.)</span>
                    <button type="button" class="btn" style="background:#fee2e2; color:#dc2626; padding:5px 10px; font-size:0.85rem; border:none; border-radius:4px; cursor:pointer; margin-left:6px;" onclick="removeSelectedBook(${b.id})" title="Quitar este libro">✕</button>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;

    // Actualizar indicador en el botón
    if (btnTxtCant) {
        btnTxtCant.textContent = totalEjemplares === 1 ? '1 libro' : `${totalEjemplares} libros`;
    }

    // Regla de Negocio: Si lleva más de 1 libro/copia, aparece el campo Responsable y se vuelve obligatorio
    if (totalEjemplares > 1) {
        if (divResponsable) divResponsable.style.display = 'block';
        if (inputResponsable) {
            inputResponsable.required = true;
        }
        if (txtCantLibros) txtCantLibros.textContent = totalEjemplares;
    } else {
        if (divResponsable) divResponsable.style.display = 'none';
        if (inputResponsable) {
            inputResponsable.required = false;
        }
    }
}

function updateBookCopies(id, val) {
    if (selectedBooks[id]) {
        let n = parseInt(val, 10) || 1;
        const max = selectedBooks[id].maxCopias || 1;
        if (n < 1) n = 1;
        if (n > max) n = max;
        selectedBooks[id].cantidad = n;
        renderSelectedBooks();
    }
}

function removeSelectedBook(id) {
    delete selectedBooks[id];
    renderSelectedBooks();
}

document.addEventListener('DOMContentLoaded', function() {
    const bookSearchInput = document.getElementById('bookSearchInput');
    const bookSearchResults = document.getElementById('bookSearchResults');
    const formIssue = document.getElementById('formIssueBook');
    let searchTimeout;

    if (bookSearchInput) {
        bookSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                bookSearchResults.style.display = 'none';
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
                                div.style.padding = '10px 14px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                
                                const copiasDisp = parseInt(book.copias_disponibles, 10) || 1;
                                const autorTxt = book.autor ? ` — ${escapeHtml(book.autor)}` : '';
                                const copiasBadge = copiasDisp > 1 
                                    ? `<span style="float:right; background:#e0f2fe; color:#0284c7; padding:2px 8px; border-radius:12px; font-size:0.78rem; font-weight:600;">${copiasDisp} disponibles</span>` 
                                    : `<span style="float:right; background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:12px; font-size:0.78rem;">1 disponible</span>`;

                                div.innerHTML = `
                                    ${copiasBadge}
                                    <strong>${escapeHtml(book.titulo)}</strong>${autorTxt}<br>
                                    <small style="color:#64748b;">Nº Inv: ${escapeHtml(book.isbn || 'S/N')}</small>
                                `;
                                
                                div.addEventListener('mouseover', () => div.style.background = '#f8fafc');
                                div.addEventListener('mouseout', () => div.style.background = 'white');
                                
                                div.addEventListener('click', () => {
                                    if (selectedBooks[book.id]) {
                                        // Si ya está, sumar 1 copia si no supera el stock
                                        if (selectedBooks[book.id].cantidad < copiasDisp) {
                                            selectedBooks[book.id].cantidad++;
                                        }
                                    } else {
                                        selectedBooks[book.id] = {
                                            id: book.id,
                                            titulo: book.titulo,
                                            autor: book.autor,
                                            isbn: book.isbn,
                                            maxCopias: copiasDisp,
                                            cantidad: 1
                                        };
                                    }
                                    bookSearchInput.value = '';
                                    bookSearchResults.style.display = 'none';
                                    renderSelectedBooks();
                                });
                                
                                bookSearchResults.appendChild(div);
                            });
                            bookSearchResults.style.display = 'block';
                        } else {
                            bookSearchResults.innerHTML = '<div style="padding:10px 14px; color:#666;">No se encontraron libros disponibles con ese criterio.</div>';
                            bookSearchResults.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error buscando libros:', error);
                    });
            }, 250);
        });

        // Ocultar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (e.target !== bookSearchInput && e.target !== bookSearchResults) {
                bookSearchResults.style.display = 'none';
            }
        });
    }

    // Validación al enviar el formulario
    if (formIssue) {
        formIssue.addEventListener('submit', function(e) {
            const bookIds = Object.keys(selectedBooks);
            if (bookIds.length === 0) {
                e.preventDefault();
                alert('Debes buscar y seleccionar al menos un libro para registrar el préstamo.');
                if (bookSearchInput) bookSearchInput.focus();
                return false;
            }

            let total = 0;
            bookIds.forEach(id => total += (parseInt(selectedBooks[id].cantidad, 10) || 1));

            const inputResponsable = document.getElementById('inputResponsable');
            if (total > 1 && inputResponsable && !inputResponsable.value.trim()) {
                e.preventDefault();
                alert('Para préstamos de más de 1 libro/copia, es obligatorio indicar el Docente o Adulto Responsable.');
                inputResponsable.focus();
                return false;
            }
        });
    }

    // Comprobar si se solicitó la pestaña de logs por parámetro URL (?tab=logs)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'logs') {
        switchLoanTab('logs');
    }
});

// Función para alternar entre Préstamos Activos y Logs / Historial
function switchLoanTab(tabName) {
    const paneActivos = document.getElementById('paneActivos');
    const paneLogs = document.getElementById('paneLogs');
    const tabActivosBtn = document.getElementById('tabActivosBtn');
    const tabLogsBtn = document.getElementById('tabLogsBtn');

    if (!paneActivos || !paneLogs) return;

    if (tabName === 'logs') {
        paneActivos.style.display = 'none';
        paneLogs.style.display = 'block';
        if (tabActivosBtn) tabActivosBtn.classList.remove('active');
        if (tabLogsBtn) tabLogsBtn.classList.add('active');
    } else {
        paneActivos.style.display = 'block';
        paneLogs.style.display = 'none';
        if (tabActivosBtn) tabActivosBtn.classList.add('active');
        if (tabLogsBtn) tabLogsBtn.classList.remove('active');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
