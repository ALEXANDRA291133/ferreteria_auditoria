<?php
session_start();
include_once 'db_connect.php';

// Verificar si se proporcionó un ID de venta
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ventas.php");
    exit();
}

$venta_id = intval($_GET['id']);
$message = '';

// --- PROCESAR ANULACIÓN DE VENTA ---
if (isset($_POST['anular_venta'])) {
    // Iniciar transacción
    pg_query($conn, "BEGIN");
    
    try {
        // Verificar que la venta existe y no está anulada
        $check_query = "SELECT estado FROM ventas WHERE id_venta = $venta_id";
        $check_result = pg_query($conn, $check_query);
        $venta_actual = pg_fetch_assoc($check_result);
        
        if ($venta_actual['estado'] == 'anulada') {
            throw new Exception("La venta ya está anulada");
        }
        
        // Obtener productos de la venta para devolver stock
        $productos_query = "SELECT id_producto, cantidad FROM detalle_venta WHERE id_venta = $venta_id";
        $productos_result = pg_query($conn, $productos_query);
        
        while ($item = pg_fetch_assoc($productos_result)) {
            // Devolver stock a productos
            $update_stock = "UPDATE productos SET stock_actual = stock_actual + {$item['cantidad']} 
                            WHERE id_producto = {$item['id_producto']}";
            pg_query($conn, $update_stock);
        }
        
        // Cambiar estado de la venta a anulada
        $update_venta = "UPDATE ventas SET estado = 'anulada' WHERE id_venta = $venta_id";
        pg_query($conn, $update_venta);
        
        pg_query($conn, "COMMIT");
        $message = '<div class="alert alert-success">✅ Venta anulada exitosamente. Stock devuelto.</div>';
        
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        $message = '<div class="alert alert-danger">❌ Error al anular: ' . $e->getMessage() . '</div>';
    }
}

// Obtener información de la venta
$venta_query = "
    SELECT 
        v.*,
        c.nombre as cliente_nombre,
        c.telefono as cliente_telefono,
        c.email as cliente_email,
        u.nombre_usuario as vendedor
    FROM ventas v
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
    LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
    WHERE v.id_venta = $venta_id
";

$venta_result = pg_query($conn, $venta_query);
$venta = pg_fetch_assoc($venta_result);

if (!$venta) {
    header("Location: ventas.php?error=venta_no_encontrada");
    exit();
}

// Obtener detalles de la venta
$detalle_query = "
    SELECT 
        dv.*,
        p.nombre as producto_nombre,
        p.codigo_barras
    FROM detalle_venta dv
    JOIN productos p ON dv.id_producto = p.id_producto
    WHERE dv.id_venta = $venta_id
";

$detalle_result = pg_query($conn, $detalle_query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Venta #<?php echo $venta_id; ?> - Ferretería</title>
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
    <!-- Navbar para venta_detalle.php -->
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
                <i class="fas fa-receipt"></i> 
                Detalle de Venta #<?php echo $venta_id; ?>
            </h1>
            <div>
                <a href="ventas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Ventas
                </a>
                <button onclick="window.print()" class="btn btn-info">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Información de la venta -->
        <div class="row">
            <div class="col-md-6">
                <div class="card info-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Información de Venta
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">ID Venta:</th>
                                <td><span class="badge bg-primary"><?php echo $venta['id_venta']; ?></span></td>
                            </tr>
                            <tr>
                                <th>Fecha y Hora:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_hora'])); ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    $estado_texto = '';
                                    
                                    if ($venta['estado'] == 'completada') {
                                        $estado_class = 'success';
                                        $estado_texto = 'COMPLETADA';
                                    } elseif ($venta['estado'] == 'anulada') {
                                        $estado_class = 'danger';
                                        $estado_texto = 'ANULADA';
                                    } elseif ($venta['estado'] == 'pendiente') {
                                        $estado_class = 'warning';
                                        $estado_texto = 'PENDIENTE';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $estado_class; ?> estado-badge">
                                        <?php echo $estado_texto; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Forma de Pago:</th>
                                <td><?php echo ucfirst($venta['forma_pago']); ?></td>
                            </tr>
                            <tr>
                                <th>Vendedor:</th>
                                <td><?php echo htmlspecialchars($venta['vendedor'] ?? 'Sistema'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card info-card">
                    <div class="card-header">
                        <i class="fas fa-user"></i> Información del Cliente
                    </div>
                    <div class="card-body">
                        <?php if ($venta['id_cliente']): ?>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Nombre:</th>
                                    <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                                </tr>
                                <tr>
                                    <th>Teléfono:</th>
                                    <td><?php echo htmlspecialchars($venta['cliente_telefono'] ?? 'No registrado'); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($venta['cliente_email'] ?? 'No registrado'); ?></td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p class="text-muted text-center my-3">
                                <i class="fas fa-user-slash fa-2x mb-2"></i><br>
                                Cliente General (sin registro)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos vendidos -->
        <div class="card info-card mt-3">
            <div class="card-header">
                <i class="fas fa-boxes"></i> Productos Vendidos
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            while ($item = pg_fetch_assoc($detalle_result)): 
                                $subtotal_item = $item['cantidad'] * $item['precio_unitario'];
                                $subtotal += $subtotal_item;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['codigo_barras']); ?></td>
                                <td><?php echo htmlspecialchars($item['producto_nombre']); ?></td>
                                <td class="text-center"><?php echo $item['cantidad']; ?></td>
                                <td class="text-end">$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format($subtotal_item, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Subtotal:</th>
                                <th class="text-end">$<?php echo number_format($subtotal, 2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">IVA (13%):</th>
                                <th class="text-end">$<?php echo number_format($venta['impuesto'], 2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end fs-5">TOTAL:</th>
                                <th class="text-end fs-5 text-primary">$<?php echo number_format($venta['total'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Botones de acción (solo si no está anulada) -->
        <?php if ($venta['estado'] != 'anulada'): ?>
        <div class="mt-4 no-print">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalAnular">
                <i class="fas fa-ban"></i> Anular Venta (devolver stock)
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
                        <p><strong>¿Estás seguro de anular la venta #<?php echo $venta_id; ?>?</strong></p>
                        <p>Esta acción:</p>
                        <ul>
                            <li>✔️ Devolverá el stock de todos los productos</li>
                            <li>✔️ Marcará la venta como ANULADA</li>
                            <li class="text-danger"><i class="fas fa-exclamation-circle"></i> Esta acción no se puede deshacer</li>
                        </ul>
                        <div class="alert alert-warning">
                            <strong>Productos afectados:</strong>
                            <?php 
                            pg_result_seek($detalle_result, 0);
                            while ($item = pg_fetch_assoc($detalle_result)) {
                                echo '<br>- ' . htmlspecialchars($item['producto_nombre']) . ' (x' . $item['cantidad'] . ')';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="POST">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="anular_venta" class="btn btn-danger">
                                <i class="fas fa-check"></i> Sí, Anular Venta
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
