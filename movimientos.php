<?php
session_start();
include_once 'db_connect.php';


// Ahora puedes usar $conn para todas las consultas
$result = pg_query($conn, "SELECT * FROM productos");
while ($row = pg_fetch_assoc($result)) {
    // procesar datos
}
// Filtros
$filtro_producto = isset($_GET['producto']) ? $_GET['producto'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_desde = isset($_GET['desde']) ? $_GET['desde'] : '';
$filtro_hasta = isset($_GET['hasta']) ? $_GET['hasta'] : '';

$where = array();
$params = array();

if (!empty($filtro_producto)) {
    $where[] = "p.id_producto = " . intval($filtro_producto);
}
if (!empty($filtro_tipo)) {
    $where[] = "m.tipo_movimiento = '" . pg_escape_string($filtro_tipo) . "'";
}
if (!empty($filtro_desde)) {
    $where[] = "m.fecha_hora >= '" . pg_escape_string($filtro_desde) . "'";
}
if (!empty($filtro_hasta)) {
    $where[] = "m.fecha_hora <= '" . pg_escape_string($filtro_hasta) . "'";
}

$where_clause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

// Obtener movimientos
$query = "
    SELECT m.*, p.nombre as producto, u.nombre_usuario as usuario,
           p.precio_compra, p.precio_venta
    FROM movimientos_inventario m
    JOIN productos p ON m.id_producto = p.id_producto
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    $where_clause
    ORDER BY m.fecha_hora DESC
";

$movimientos = pg_query($conn, $query);

// Obtener lista de productos para el filtro
$productos = pg_query($conn, "SELECT id_producto, nombre FROM productos WHERE activo = true ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - Auditoría de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <!-- Navbar para movimientos.php -->
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
                    <a class="nav-link active" href="movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="stock_critico.php"><i class="fas fa-exclamation-triangle"></i> Stock Crítico</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ventas.php"><i class="fas fa-shopping-cart"></i> Ventas/Compras</a>
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
        <h1 class="mb-4">Historial de Movimientos</h1>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Producto</label>
                        <select name="producto" class="form-select">
                            <option value="">Todos</option>
                            <?php while ($p = pg_fetch_assoc($productos)): ?>
                                <option value="<?php echo $p['id_producto']; ?>" <?php echo $filtro_producto == $p['id_producto'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="entrada" <?php echo $filtro_tipo == 'entrada' ? 'selected' : ''; ?>>Entrada</option>
                            <option value="salida" <?php echo $filtro_tipo == 'salida' ? 'selected' : ''; ?>>Salida</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="desde" class="form-control" value="<?php echo $filtro_desde; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="hasta" class="form-control" value="<?php echo $filtro_hasta; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="movimientos.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de movimientos -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaMovimientos" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock Anterior</th>
                                <th>Stock Posterior</th>
                                <th>Usuario</th>
                                <th>Motivo</th>
                                <th>Referencia</th>
                                <th>Valor Movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_entradas = 0;
                            $total_salidas = 0;
                            while ($m = pg_fetch_assoc($movimientos)): 
                                $valor_movimiento = $m['cantidad'] * $m['precio_compra'];
                                if ($m['tipo_movimiento'] == 'entrada') {
                                    $total_entradas += $valor_movimiento;
                                } else {
                                    $total_salidas += $valor_movimiento;
                                }
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['fecha_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($m['producto']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $m['tipo_movimiento'] == 'entrada' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($m['tipo_movimiento']); ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold"><?php echo $m['cantidad']; ?></td>
                                <td class="text-center"><?php echo $m['stock_anterior']; ?></td>
                                <td class="text-center"><?php echo $m['stock_posterior']; ?></td>
                                <td><?php echo htmlspecialchars($m['usuario']); ?></td>
                                <td><?php echo htmlspecialchars($m['motivo']); ?></td>
                                <td><?php echo htmlspecialchars($m['referencia'] ?: '-'); ?></td>
                                <td>$<?php echo number_format($valor_movimiento, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="9" class="text-end">Total Entradas:</th>
                                <th>$<?php echo number_format($total_entradas, 2); ?></th>
                            </tr>
                            <tr class="table-warning">
                                <th colspan="9" class="text-end">Total Salidas:</th>
                                <th>$<?php echo number_format($total_salidas, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaMovimientos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[0, 'desc']]
            });
        });
    </script>
</body>
</html>
<?php pg_close($conn); ?>