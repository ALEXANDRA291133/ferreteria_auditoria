<?php
session_start();
include_once 'db_connect.php';

$message = '';

// --- PROCESAR NUEVO CLIENTE (versión AJAX) ---
if (isset($_POST['registrar_cliente']) && isset($_GET['ajax'])) {
    $nombre = pg_escape_string($conn, $_POST['nombre']);
    $telefono = pg_escape_string($conn, $_POST['telefono'] ?? '');
    $email = pg_escape_string($conn, $_POST['email'] ?? '');
    $direccion = pg_escape_string($conn, $_POST['direccion'] ?? '');
    
    $query = "INSERT INTO clientes (nombre, telefono, email, direccion, fecha_registro) 
              VALUES ('$nombre', '$telefono', '$email', '$direccion', NOW()) RETURNING id_cliente";
    
    $result = pg_query($conn, $query);
    
    if ($result) {
        $row = pg_fetch_assoc($result);
        echo json_encode(['success' => true, 'id' => $row['id_cliente'], 'nombre' => $nombre]);
    } else {
        echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
    }
    exit();
}

// --- PROCESAR NUEVA VENTA ---
if (isset($_POST['registrar_venta'])) {
    $cliente_id = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 'NULL';
    $usuario_id = $_SESSION['user_id'] ?? 1;
    $forma_pago = $_POST['forma_pago'];
    $productos = $_POST['producto_id'];
    $cantidades = $_POST['cantidad'];
    $precios = $_POST['precio_unitario'];
    
    $subtotal = 0;
    $items = [];
    
    for ($i = 0; $i < count($productos); $i++) {
        if (!empty($productos[$i]) && $cantidades[$i] > 0) {
            $subtotal_item = $cantidades[$i] * $precios[$i];
            $subtotal += $subtotal_item;
            $items[] = [
                'producto_id' => $productos[$i],
                'cantidad' => $cantidades[$i],
                'precio' => $precios[$i]
            ];
        }
    }
    
    if (empty($items)) {
        $message = '<div class="alert alert-warning">⚠️ Debe agregar al menos un producto</div>';
    } else {
        $impuesto = $subtotal * 0.13;
        $total = $subtotal + $impuesto;
        
        pg_query($conn, "BEGIN");
        
        try {
            $query_venta = "INSERT INTO ventas (fecha_hora, id_cliente, id_usuario, subtotal, impuesto, total, forma_pago, estado) 
                            VALUES (NOW(), $cliente_id, $usuario_id, $subtotal, $impuesto, $total, '$forma_pago', 'completada') RETURNING id_venta";
            
            $result_venta = pg_query($conn, $query_venta);
            
            if (!$result_venta) {
                throw new Exception("Error al insertar venta: " . pg_last_error($conn));
            }
            
            $row_venta = pg_fetch_assoc($result_venta);
            $id_venta = $row_venta['id_venta'];
            
            foreach ($items as $item) {
                $query_detalle = "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario) 
                                  VALUES ($id_venta, {$item['producto_id']}, {$item['cantidad']}, {$item['precio']})";
                
                $result_detalle = pg_query($conn, $query_detalle);
                
                if (!$result_detalle) {
                    throw new Exception("Error al insertar detalle: " . pg_last_error($conn));
                }
                
                $query_stock = "UPDATE productos SET stock_actual = stock_actual - {$item['cantidad']} 
                                WHERE id_producto = {$item['producto_id']}";
                
                $result_stock = pg_query($conn, $query_stock);
                
                if (!$result_stock) {
                    throw new Exception("Error al actualizar stock: " . pg_last_error($conn));
                }
            }
            
            pg_query($conn, "COMMIT");
            $message = '<div class="alert alert-success">✅ Venta #' . $id_venta . ' registrada exitosamente</div>';
            
        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $message = '<div class="alert alert-danger">❌ Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- PROCESAR NUEVA COMPRA ---
if (isset($_POST['registrar_compra'])) {
    $proveedor_id = intval($_POST['proveedor_id']);
    $usuario_id = $_SESSION['user_id'] ?? 1;
    $productos = $_POST['prod_compra_id'];
    $cantidades = $_POST['cantidad_compra'];
    $costos = $_POST['costo_unitario'];
    
    $total = 0;
    $items = [];
    
    for ($i = 0; $i < count($productos); $i++) {
        if (!empty($productos[$i]) && $cantidades[$i] > 0) {
            $subtotal_item = $cantidades[$i] * $costos[$i];
            $total += $subtotal_item;
            $items[] = [
                'producto_id' => $productos[$i],
                'cantidad' => $cantidades[$i],
                'costo' => $costos[$i]
            ];
        }
    }
    
    if (empty($items)) {
        $message = '<div class="alert alert-warning">⚠️ Debe agregar al menos un producto</div>';
    } else {
        pg_query($conn, "BEGIN");
        
        try {
            $query_compra = "INSERT INTO compras (fecha_hora, id_proveedor, id_usuario, total, estado) 
                             VALUES (NOW(), $proveedor_id, $usuario_id, $total, 'completada') RETURNING id_compra";
            
            $result_compra = pg_query($conn, $query_compra);
            
            if (!$result_compra) {
                throw new Exception("Error al insertar compra: " . pg_last_error($conn));
            }
            
            $row_compra = pg_fetch_assoc($result_compra);
            $id_compra = $row_compra['id_compra'];
            
            foreach ($items as $item) {
                $query_detalle = "INSERT INTO detalle_compra (id_compra, id_producto, cantidad, costo_unitario) 
                                  VALUES ($id_compra, {$item['producto_id']}, {$item['cantidad']}, {$item['costo']})";
                
                $result_detalle = pg_query($conn, $query_detalle);
                
                if (!$result_detalle) {
                    throw new Exception("Error al insertar detalle de compra: " . pg_last_error($conn));
                }
                
                $query_stock = "UPDATE productos SET stock_actual = stock_actual + {$item['cantidad']} 
                                WHERE id_producto = {$item['producto_id']}";
                
                $result_stock = pg_query($conn, $query_stock);
                
                if (!$result_stock) {
                    throw new Exception("Error al actualizar stock: " . pg_last_error($conn));
                }
            }
            
            pg_query($conn, "COMMIT");
            $message = '<div class="alert alert-success">✅ Compra #' . $id_compra . ' registrada exitosamente</div>';
            
        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $message = '<div class="alert alert-danger">❌ Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Obtener datos para selects
$clientes = pg_query($conn, "SELECT id_cliente, nombre FROM clientes ORDER BY nombre");
$proveedores = pg_query($conn, "SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre");
$productos = pg_query($conn, "SELECT id_producto, nombre, precio_venta, precio_compra, stock_actual FROM productos WHERE activo = true ORDER BY nombre");

// Obtener listados
$ventas_recientes = pg_query($conn, "
    SELECT v.*, c.nombre as cliente 
    FROM ventas v 
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente 
    ORDER BY v.fecha_hora DESC 
    LIMIT 20
");

$compras_recientes = pg_query($conn, "
    SELECT c.*, p.nombre as proveedor 
    FROM compras c 
    JOIN proveedores p ON c.id_proveedor = p.id_proveedor 
    ORDER BY c.fecha_hora DESC 
    LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas y Compras - Ferretería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<!-- Navbar -->
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
    <h1 class="mb-4"><i class="fas fa-cash-register"></i> Registro de Ventas y Compras</h1>
    
    <?php echo $message; ?>
    
    <!-- Pestañas -->
    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ventas-tab" data-bs-toggle="tab" data-bs-target="#ventas" type="button" role="tab">
                <i class="fas fa-shopping-cart text-success"></i> Registrar Venta
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="compras-tab" data-bs-toggle="tab" data-bs-target="#compras" type="button" role="tab">
                <i class="fas fa-truck text-primary"></i> Registrar Compra
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="listados-tab" data-bs-toggle="tab" data-bs-target="#listados" type="button" role="tab">
                <i class="fas fa-list"></i> Listados Recientes
            </button>
        </li>
    </ul>
    
    <!-- Contenido de pestañas -->
    <div class="tab-content" id="myTabContent">
        
        <!-- PESTAÑA DE VENTAS -->
        <div class="tab-pane fade show active" id="ventas" role="tabpanel">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Nueva Venta</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="formVenta">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Cliente (opcional)</label>
                                <div class="input-group">
                                    <select name="cliente_id" class="form-select" id="clienteSelect">
                                        <option value="">-- Cliente General --</option>
                                        <?php 
                                        pg_result_seek($clientes, 0);
                                        while ($c = pg_fetch_assoc($clientes)): 
                                        ?>
                                            <option value="<?php echo $c['id_cliente']; ?>">
                                                <?php echo htmlspecialchars($c['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
                                        <i class="fas fa-plus"></i> Nuevo
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Forma de Pago</label>
                                <select name="forma_pago" class="form-select" required>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="credito">Crédito</option>
                                </select>
                            </div>
                        </div>
                        
                        <h6 class="mt-3">Productos</h6>
                        <div id="items-venta">
                            <div class="row mb-2 item-row">
                                <div class="col-md-5">
                                    <select name="producto_id[]" class="form-select producto-select" required>
                                        <option value="">Seleccionar producto</option>
                                        <?php 
                                        pg_result_seek($productos, 0); 
                                        while ($p = pg_fetch_assoc($productos)): 
                                        ?>
                                            <option value="<?php echo $p['id_producto']; ?>" 
                                                    data-precio="<?php echo $p['precio_venta']; ?>"
                                                    data-stock="<?php echo $p['stock_actual']; ?>">
                                                <?php echo htmlspecialchars($p['nombre']); ?> 
                                                (Stock: <?php echo $p['stock_actual']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="cantidad[]" class="form-control cantidad" placeholder="Cantidad" min="1" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="precio_unitario[]" class="form-control precio" step="0.01" readonly>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control subtotal" readonly placeholder="Subtotal">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm eliminar-item"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-secondary btn-sm mb-3" id="agregar-item-venta">
                            <i class="fas fa-plus"></i> Agregar otro producto
                        </button>
                        
                        <div class="row mt-3">
                            <div class="col-md-6 offset-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Subtotal:</th>
                                        <td class="text-end" id="subtotal">$0.00</td>
                                    </tr>
                                    <tr>
                                        <th>IVA (13%):</th>
                                        <td class="text-end" id="impuesto">$0.00</td>
                                    </tr>
                                    <tr>
                                        <th>TOTAL:</th>
                                        <td class="text-end fw-bold fs-5" id="total">$0.00</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <button type="submit" name="registrar_venta" class="btn btn-success">
                            <i class="fas fa-save"></i> Registrar Venta
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- PESTAÑA DE COMPRAS -->
        <div class="tab-pane fade" id="compras" role="tabpanel">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-truck"></i> Nueva Compra a Proveedor</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="formCompra">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Proveedor</label>
                                <select name="proveedor_id" class="form-select" required>
                                    <option value="">Seleccionar proveedor</option>
                                    <?php while ($p = pg_fetch_assoc($proveedores)): ?>
                                        <option value="<?php echo $p['id_proveedor']; ?>">
                                            <?php echo htmlspecialchars($p['nombre']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <h6 class="mt-3">Productos</h6>
                        <div id="items-compra">
                            <div class="row mb-2 item-row">
                                <div class="col-md-5">
                                    <select name="prod_compra_id[]" class="form-select producto-select" required>
                                        <option value="">Seleccionar producto</option>
                                        <?php 
                                        pg_result_seek($productos, 0); 
                                        while ($p = pg_fetch_assoc($productos)): 
                                        ?>
                                            <option value="<?php echo $p['id_producto']; ?>" 
                                                    data-costo="<?php echo $p['precio_compra']; ?>">
                                                <?php echo htmlspecialchars($p['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="cantidad_compra[]" class="form-control cantidad" placeholder="Cantidad" min="1" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="costo_unitario[]" class="form-control costo" step="0.01" readonly>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control subtotal" readonly placeholder="Subtotal">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm eliminar-item"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-secondary btn-sm mb-3" id="agregar-item-compra">
                            <i class="fas fa-plus"></i> Agregar otro producto
                        </button>
                        
                        <div class="row mt-3">
                            <div class="col-md-6 offset-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>TOTAL COMPRA:</th>
                                        <td class="text-end fw-bold fs-5" id="total-compra">$0.00</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <button type="submit" name="registrar_compra" class="btn btn-primary">
                            <i class="fas fa-save"></i> Registrar Compra
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- PESTAÑA DE LISTADOS -->
        <div class="tab-pane fade" id="listados" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Últimas Ventas</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        pg_result_seek($ventas_recientes, 0);
                                        while ($v = pg_fetch_assoc($ventas_recientes)): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $v['id_venta']; ?></strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($v['fecha_hora'])); ?></td>
                                            <td><?php echo htmlspecialchars($v['cliente'] ?? 'General'); ?></td>
                                            <td class="text-end fw-bold">$<?php echo number_format($v['total'], 2); ?></td>
                                            <td class="text-center">
                                                <a href="venta_detalle.php?id=<?php echo $v['id_venta']; ?>" 
                                                   class="btn btn-info btn-sm" 
                                                   title="Ver detalle de la venta">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-truck"></i> Últimas Compras</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th>Total</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        pg_result_seek($compras_recientes, 0);
                                        while ($c = pg_fetch_assoc($compras_recientes)): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $c['id_compra']; ?></strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_hora'])); ?></td>
                                            <td><?php echo htmlspecialchars($c['proveedor']); ?></td>
                                            <td class="text-end fw-bold">$<?php echo number_format($c['total'], 2); ?></td>
                                            <td class="text-center">
                                                <a href="compra_detalle.php?id=<?php echo $c['id_compra']; ?>" 
                                                   class="btn btn-info btn-sm" 
                                                   title="Ver detalle de la compra">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($c['estado'] != 'anulada'): ?>
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="if(confirm('¿Anular esta compra?\\nEsta acción ajustará el stock.')) location.href='anular_compra.php?id=<?php echo $c['id_compra']; ?>'"
                                                        title="Anular compra">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Nuevo Cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Registrar Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoCliente">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" name="nombre" class="form-control" required 
                               placeholder="Ej: Juan Pérez">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" 
                               placeholder="Ej: 7654321">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Ej: cliente@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" 
                               placeholder="Ej: Calle Principal #123">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    
    // --- FUNCIONES PARA VENTAS ---
    function actualizarVenta() {
        let subtotal = 0;
        $('#items-venta .item-row').each(function() {
            const cantidad = $(this).find('.cantidad').val() || 0;
            const precio = $(this).find('.precio').val() || 0;
            const subtotalItem = cantidad * precio;
            $(this).find('.subtotal').val('$' + subtotalItem.toFixed(2));
            subtotal += subtotalItem;
        });
        
        const impuesto = subtotal * 0.13;
        const total = subtotal + impuesto;
        
        $('#subtotal').text('$' + subtotal.toFixed(2));
        $('#impuesto').text('$' + impuesto.toFixed(2));
        $('#total').text('$' + total.toFixed(2));
    }
    
    $('#agregar-item-venta').click(function() {
        const newRow = $('#items-venta .item-row:first').clone();
        newRow.find('input').val('');
        newRow.find('select').val('');
        $('#items-venta').append(newRow);
    });
    
    $(document).on('change', '#items-venta .producto-select', function() {
        const precio = $(this).find('option:selected').data('precio');
        $(this).closest('.item-row').find('.precio').val(precio);
        actualizarVenta();
    });
    
    $(document).on('input', '#items-venta .cantidad', actualizarVenta);
    
    $(document).on('click', '#items-venta .eliminar-item', function() {
        if ($('#items-venta .item-row').length > 1) {
            $(this).closest('.item-row').remove();
            actualizarVenta();
        }
    });
    
    // --- FUNCIONES PARA COMPRAS ---
    function actualizarCompra() {
        let total = 0;
        $('#items-compra .item-row').each(function() {
            const cantidad = $(this).find('.cantidad').val() || 0;
            const costo = $(this).find('.costo').val() || 0;
            const subtotalItem = cantidad * costo;
            $(this).find('.subtotal').val('$' + subtotalItem.toFixed(2));
            total += subtotalItem;
        });
        $('#total-compra').text('$' + total.toFixed(2));
    }
    
    $('#agregar-item-compra').click(function() {
        const newRow = $('#items-compra .item-row:first').clone();
        newRow.find('input').val('');
        newRow.find('select').val('');
        $('#items-compra').append(newRow);
    });
    
    $(document).on('change', '#items-compra .producto-select', function() {
        const costo = $(this).find('option:selected').data('costo');
        $(this).closest('.item-row').find('.costo').val(costo);
        actualizarCompra();
    });
    
    $(document).on('input', '#items-compra .cantidad', actualizarCompra);
    
    $(document).on('click', '#items-compra .eliminar-item', function() {
        if ($('#items-compra .item-row').length > 1) {
            $(this).closest('.item-row').remove();
            actualizarCompra();
        }
    });
    
    // --- NUEVO CLIENTE CON AJAX ---
    $('#formNuevoCliente').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            type: 'POST',
            url: 'ventas.php?ajax=1',
            data: $(this).serialize() + '&registrar_cliente=1',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#clienteSelect').append('<option value="' + response.id + '" selected>' + response.nombre + '</option>');
                    $('#modalNuevoCliente').modal('hide');
                    $('#formNuevoCliente')[0].reset();
                    alert('✅ Cliente registrado exitosamente');
                } else {
                    alert('❌ Error: ' + response.error);
                }
            },
            error: function() {
                alert('❌ Error de conexión');
            }
        });
    });
});
</script>

</body>
</html>
<?php pg_close($conn); ?>