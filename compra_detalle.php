<?php
session_start();
include_once 'db_connect.php';

// Verificar si se proporcionó un ID de compra
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ventas.php");
    exit();
}

$compra_id = intval($_GET['id']);
$message = '';

// --- PROCESAR ANULACIÓN DE COMPRA ---
if (isset($_POST['anular_compra'])) {
    // Iniciar transacción
    pg_query($conn, "BEGIN");
    
    try {
        // Verificar que la compra existe y no está anulada
        $check_query = "SELECT estado FROM compras WHERE id_compra = $compra_id";
        $check_result = pg_query($conn, $check_query);
        
        if (!$check_result) {
            throw new Exception("Error al verificar compra: " . pg_last_error($conn));
        }
        
        $compra_actual = pg_fetch_assoc($check_result);
        
        if (!$compra_actual) {
            throw new Exception("Compra no encontrada");
        }
        
        if ($compra_actual['estado'] == 'anulada') {
            throw new Exception("La compra ya está anulada");
        }
        
        // Obtener productos de la compra para devolver stock (restar)
        // AHORA USAMOS EL NOMBRE CORRECTO: detalle_compra
        $productos_query = "SELECT id_producto, cantidad FROM detalle_compra WHERE id_compra = $compra_id";
        $productos_result = pg_query($conn, $productos_query);
        
        if (!$productos_result) {
            throw new Exception("Error al obtener productos: " . pg_last_error($conn));
        }
        
        while ($item = pg_fetch_assoc($productos_result)) {
            // Restar stock (devolver la compra)
            $update_stock = "UPDATE productos SET stock_actual = stock_actual - {$item['cantidad']} 
                            WHERE id_producto = {$item['id_producto']}";
            $result_stock = pg_query($conn, $update_stock);
            
            if (!$result_stock) {
                throw new Exception("Error al actualizar stock: " . pg_last_error($conn));
            }
        }
        
        // Cambiar estado de la compra a anulada
        $update_compra = "UPDATE compras SET estado = 'anulada' WHERE id_compra = $compra_id";
        $result_update = pg_query($conn, $update_compra);
        
        if (!$result_update) {
            throw new Exception("Error al actualizar compra: " . pg_last_error($conn));
        }
        
        pg_query($conn, "COMMIT");
        $message = '<div class="alert alert-success">✅ Compra anulada exitosamente. Stock ajustado.</div>';
        
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        $message = '<div class="alert alert-danger">❌ Error al anular: ' . $e->getMessage() . '</div>';
    }
}

// Obtener información de la compra
$compra_query = "
    SELECT 
        c.*,
        p.nombre as proveedor_nombre,
        p.contacto as proveedor_contacto,
        p.telefono as proveedor_telefono,
        p.email as proveedor_email,
        u.nombre_usuario as usuario
    FROM compras c
    JOIN proveedores p ON c.id_proveedor = p.id_proveedor
    LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE c.id_compra = $compra_id
";

$compra_result = pg_query($conn, $compra_query);

if (!$compra_result) {
    die("Error en consulta de compra: " . pg_last_error($conn));
}

$compra = pg_fetch_assoc($compra_result);

if (!$compra) {
    header("Location: ventas.php?error=compra_no_encontrada");
    exit();
}

// Obtener detalles de la compra - AHORA CON EL NOMBRE CORRECTO
$detalle_query = "
    SELECT 
        dc.*,
        pr.nombre as producto_nombre,
        pr.codigo_barras
    FROM detalle_compra dc
    JOIN productos pr ON dc.id_producto = pr.id_producto
    WHERE dc.id_compra = $compra_id
";

$detalle_result = pg_query($conn, $detalle_query);

if (!$detalle_result) {
    die("Error en consulta de detalles: " . pg_last_error($conn) . "<br>Query: " . $detalle_query);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Compra #<?php echo $compra_id; ?> - Ferretería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .estado-badge {
            font-size: 1rem;
            padding: 8px 15px;
        }
        .info-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .info-card .card-header {
            background-color: #f8f9fa;
            border-bottom: none;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <!-- Navbar para compra_detalle.php -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-warehouse"></i> Ferretería - Inventario
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="productos.php"><i class="fas fa-box"></i> Productos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="stock_critico.php"><i class="fas fa-exclamation-triangle"></i> Stock Crítico</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="ventas.php"><i class="fas fa-shopping-cart"></i> Ventas/Compras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                </li>
                
                <!-- Menú de usuario -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Usuario'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-id-card"></i> Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
    <div class="container mt-4">
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1>
                <i class="fas fa-truck"></i> 
                Detalle de Compra #<?php echo $compra_id; ?>
            </h1>
            <div>
                <a href="ventas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Ventas/Compras
                </a>
                <button onclick="window.print()" class="btn btn-info">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Información de la compra -->
        <div class="row">
            <div class="col-md-6">
                <div class="card info-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Información de Compra
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">ID Compra:</th>
                                <td><span class="badge bg-primary"><?php echo $compra['id_compra']; ?></span></td>
                            </tr>
                            <tr>
                                <th>Fecha y Hora:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($compra['fecha_hora'])); ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    $estado_texto = '';
                                    
                                    if ($compra['estado'] == 'completada') {
                                        $estado_class = 'success';
                                        $estado_texto = 'COMPLETADA';
                                    } elseif ($compra['estado'] == 'anulada') {
                                        $estado_class = 'danger';
                                        $estado_texto = 'ANULADA';
                                    } else {
                                        $estado_class = 'secondary';
                                        $estado_texto = strtoupper($compra['estado']);
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $estado_class; ?> estado-badge">
                                        <?php echo $estado_texto; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Registrado por:</th>
                                <td><?php echo htmlspecialchars($compra['usuario'] ?? 'Sistema'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card info-card">
                    <div class="card-header">
                        <i class="fas fa-building"></i> Información del Proveedor
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Proveedor:</th>
                                <td><strong><?php echo htmlspecialchars($compra['proveedor_nombre']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Contacto:</th>
                                <td><?php echo htmlspecialchars($compra['proveedor_contacto'] ?? 'No especificado'); ?></td>
                            </tr>
                            <tr>
                                <th>Teléfono:</th>
                                <td><?php echo htmlspecialchars($compra['proveedor_telefono'] ?? 'No registrado'); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($compra['proveedor_email'] ?? 'No registrado'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos comprados -->
        <div class="card info-card mt-3">
            <div class="card-header">
                <i class="fas fa-boxes"></i> Productos Comprados
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Costo Unit.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = 0;
                            if ($detalle_result && pg_num_rows($detalle_result) > 0) {
                                while ($item = pg_fetch_assoc($detalle_result)): 
                                    $subtotal_item = $item['cantidad'] * $item['costo_unitario'];
                                    $total += $subtotal_item;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['codigo_barras']); ?></td>
                                <td><?php echo htmlspecialchars($item['producto_nombre']); ?></td>
                                <td class="text-center"><?php echo $item['cantidad']; ?></td>
                                <td class="text-end">$<?php echo number_format($item['costo_unitario'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format($subtotal_item, 2); ?></td>
                            </tr>
                            <?php 
                                endwhile; 
                            } else {
                                echo "<tr><td colspan='5' class='text-center text-muted'>No hay productos en esta compra</td></tr>";
                            }
                            ?>
                        </tbody>
                        <?php if ($detalle_result && pg_num_rows($detalle_result) > 0): ?>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end fs-5">TOTAL:</th>
                                <th class="text-end fs-5 text-primary">$<?php echo number_format($total, 2); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Botones de acción (solo si no está anulada) -->
        <?php if ($compra['estado'] != 'anulada'): ?>
        <div class="mt-4 no-print">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalAnular">
                <i class="fas fa-ban"></i> Anular Compra (ajustar stock)
            </button>
        </div>
        <?php endif; ?>

        <!-- Modal de confirmación para anular -->
        <div class="modal fade" id="modalAnular" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar Anulación</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>¿Estás seguro de anular la compra #<?php echo $compra_id; ?>?</strong></p>
                        <p>Esta acción:</p>
                        <ul>
                            <li>✔️ Restará del stock los productos de esta compra</li>
                            <li>✔️ Marcará la compra como ANULADA</li>
                            <li class="text-danger"><i class="fas fa-exclamation-circle"></i> Esta acción no se puede deshacer</li>
                        </ul>
                        <?php 
                        // Volver a obtener los productos para mostrarlos en el modal
                        if ($detalle_result && pg_num_rows($detalle_result) > 0) {
                            pg_result_seek($detalle_result, 0);
                            echo '<div class="alert alert-warning"><strong>Productos afectados:</strong>';
                            while ($item = pg_fetch_assoc($detalle_result)) {
                                echo '<br>- ' . htmlspecialchars($item['producto_nombre']) . ' (x' . $item['cantidad'] . ')';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="modal-footer">
                        <form method="POST">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="anular_compra" class="btn btn-danger">
                                <i class="fas fa-check"></i> Sí, Anular Compra
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php pg_close($conn); ?>
