<?php
// Incluir verificación de sesión (CORREGIDO: verificar_sesion.php)
require_once 'verificar_sesion.php';
include_once 'db_connect.php';

// El resto de tu código PHP existente
$username = $_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Usuario';

// Obtener KPIs principales (CORREGIDO: nombres de tablas y columnas)
$query_kpis = "
    SELECT
        (SELECT COUNT(*) FROM productos WHERE activo = true) as total_productos,
        (SELECT COUNT(*) FROM productos WHERE stock_actual = 0 AND activo = true) as productos_agotados,
        (SELECT COUNT(*) FROM productos WHERE stock_actual < stock_minimo AND activo = true) as stock_critico,
        (SELECT COALESCE(SUM(stock_actual * precio_compra), 0) FROM productos WHERE activo = true) as valor_inventario,
        (SELECT COUNT(*) FROM historico_stockcritico WHERE estado = 'pendiente') as alertas_pendientes,
        (SELECT COUNT(*) FROM movimientos_inventario WHERE fecha_hora > NOW() - INTERVAL '7 days') as movimientos_7dias
";

$result_kpis = pg_query($conn, $query_kpis);
$kpis = pg_fetch_assoc($result_kpis);

// Obtener últimas alertas de stock crítico
$alertas = pg_query($conn, "
    SELECT * FROM historico_stockcritico 
    WHERE estado = 'pendiente' 
    ORDER BY fecha_deteccion DESC 
    LIMIT 5
");

// Obtener últimos movimientos
$movimientos = pg_query($conn, "
    SELECT m.*, p.nombre as producto, u.nombre_usuario as usuario
    FROM movimientos_inventario m
    JOIN productos p ON m.id_producto = p.id_producto
    LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
    ORDER BY m.fecha_hora DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ferretería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .card-kpi {
            transition: transform 0.3s;
            border: none;
            border-radius: 10px;
        }
        .card-kpi:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .bg-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .bg-success { background: linear-gradient(135deg, #10b981, #059669); }
        .bg-warning { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .bg-info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .bg-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
    </style>
</head>
<body>

<!-- Navbar CORREGIDO -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-warehouse"></i> Ferretería - Auditoría de Inventario
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="productos.php">
                        <i class="fas fa-box"></i> Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="movimientos.php">
                        <i class="fas fa-exchange-alt"></i> Movimientos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="stock_critico.php">
                        <i class="fas fa-exclamation-triangle"></i> Stock Crítico
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ventas.php">
                        <i class="fas fa-shopping-cart"></i> Ventas/Compras
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reportes.php">
                        <i class="fas fa-chart-bar"></i> Reportes
                    </a>
                </li>
                
                <!-- Menú de usuario -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
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

<!-- Contenido principal -->
<div class="container mt-4">
    <h1 class="mb-4">Dashboard de Inventario</h1>
    
    <!-- KPIs -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white card-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Productos Totales</h6>
                            <h2 class="mb-0"><?php echo $kpis['total_productos'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-box fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-danger text-white card-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Stock Crítico</h6>
                            <h2 class="mb-0"><?php echo $kpis['stock_critico'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-dark card-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Alertas Pendientes</h6>
                            <h2 class="mb-0"><?php echo $kpis['alertas_pendientes'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-bell fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card bg-success text-white card-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Valor del Inventario</h6>
                            <h2 class="mb-0">$<?php echo number_format($kpis['valor_inventario'] ?? 0, 2); ?></h2>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card bg-info text-white card-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Movimientos (7 días)</h6>
                            <h2 class="mb-0"><?php echo $kpis['movimientos_7dias'] ?? 0; ?></h2>
                        </div>
                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Alertas de Stock Crítico -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle"></i> Alertas de Stock Crítico Pendientes</h5>
                </div>
                <div class="card-body">
                    <?php if ($alertas && pg_num_rows($alertas) > 0): ?>
                        <div class="list-group">
                            <?php while ($alerta = pg_fetch_assoc($alertas)): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-danger"><?php echo htmlspecialchars($alerta['nombre_producto']); ?></h6>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($alerta['fecha_deteccion'])); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        Stock actual: <strong class="text-danger"><?php echo $alerta['stock_actual']; ?></strong> | 
                                        Mínimo: <?php echo $alerta['stock_minimo']; ?> | 
                                        Déficit: <strong class="text-danger"><?php echo $alerta['deficit']; ?></strong>
                                    </p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No hay alertas de stock crítico pendientes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Últimos Movimientos -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Últimos Movimientos</h5>
                </div>
                <div class="card-body">
                    <?php if ($movimientos && pg_num_rows($movimientos) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Tipo</th>
                                        <th>Cantidad</th>
                                        <th>Usuario</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($mov = pg_fetch_assoc($movimientos)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mov['producto']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $mov['tipo_movimiento'] == 'entrada' ? 'success' : 'warning'; ?>">
                                                    <?php echo $mov['tipo_movimiento']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $mov['cantidad']; ?></td>
                                            <td><?php echo htmlspecialchars($mov['usuario'] ?? 'Sistema'); ?></td>
                                            <td><?php echo date('d/m H:i', strtotime($mov['fecha_hora'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No hay movimientos recientes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
// Cerrar conexión
pg_close($conn);
?>
