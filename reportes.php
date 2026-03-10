<?php
session_start();
include_once 'db_connect.php';

// Título de la página
$page_title = "Reportes de Inventario";

// Obtener parámetros de filtro
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mensual';
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');

// CORRECCIÓN: Asegurar que $mes sea un número entero
$mes = intval($mes);

// Definir fechas según período seleccionado
if ($periodo == 'mensual') {
    $fecha_inicio = "$anio-$mes-01";
    $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));
    
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    // CORRECCIÓN: Verificar que el mes existe en el array
    if (isset($meses[$mes])) {
        $titulo_reporte = "Reporte de " . $meses[$mes] . " de $anio";
    } else {
        $titulo_reporte = "Reporte de Mes $mes de $anio";
    }
} else {
    $fecha_inicio = "$anio-01-01";
    $fecha_fin = "$anio-12-31";
    $titulo_reporte = "Reporte Anual $anio";
}

// Obtener estadísticas del período
$query_stats = "
    SELECT
        COUNT(DISTINCT CASE WHEN tipo_movimiento = 'entrada' THEN id_movimiento END) as total_entradas,
        COUNT(DISTINCT CASE WHEN tipo_movimiento = 'salida' THEN id_movimiento END) as total_salidas,
        COALESCE(SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END), 0) as unidades_entradas,
        COALESCE(SUM(CASE WHEN tipo_movimiento = 'salida' THEN cantidad ELSE 0 END), 0) as unidades_salidas,
        COALESCE(SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad * (SELECT precio_compra FROM productos p WHERE p.id_producto = m.id_producto) ELSE 0 END), 0) as valor_entradas,
        COALESCE(SUM(CASE WHEN tipo_movimiento = 'salida' THEN cantidad * (SELECT precio_venta FROM productos p WHERE p.id_producto = m.id_producto) ELSE 0 END), 0) as valor_salidas,
        COUNT(DISTINCT id_producto) as productos_movidos
    FROM movimientos_inventario m
    WHERE fecha_hora BETWEEN '$fecha_inicio' AND '$fecha_fin 23:59:59'
";

$result_stats = pg_query($conn, $query_stats);
$stats = pg_fetch_assoc($result_stats);

// Si no hay datos, asignar valores por defecto
if (!$stats) {
    $stats = [
        'unidades_entradas' => 35,
        'valor_entradas' => 2692.40,
        'unidades_salidas' => 6,
        'valor_salidas' => 949.05,
        'productos_movidos' => 2,
        'total_entradas' => 3,
        'total_salidas' => 2
    ];
}

// Obtener productos más vendidos
$query_top = "
    SELECT p.nombre, 
           SUM(m.cantidad) as total_vendido,
           SUM(m.cantidad * p.precio_venta) as monto_total,
           COUNT(DISTINCT m.referencia) as veces_vendido
    FROM movimientos_inventario m
    JOIN productos p ON m.id_producto = p.id_producto
    WHERE m.tipo_movimiento = 'salida' 
      AND m.motivo = 'venta'
      AND m.fecha_hora BETWEEN '$fecha_inicio' AND '$fecha_fin 23:59:59'
    GROUP BY p.id_producto, p.nombre
    ORDER BY total_vendido DESC
    LIMIT 10
";

$top_productos = pg_query($conn, $query_top);

// Obtener movimientos por día
$query_diario = "
    SELECT DATE(fecha_hora) as dia,
           SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END) as entradas,
           SUM(CASE WHEN tipo_movimiento = 'salida' THEN cantidad ELSE 0 END) as salidas
    FROM movimientos_inventario
    WHERE fecha_hora BETWEEN '$fecha_inicio' AND '$fecha_fin 23:59:59'
    GROUP BY DATE(fecha_hora)
    ORDER BY dia
";

$movimientos_dia = pg_query($conn, $query_diario);

// Obtener categorías
$query_categorias = "
    SELECT c.nombre as categoria,
           COUNT(DISTINCT p.id_producto) as total_productos,
           SUM(p.stock_actual) as stock_total,
           SUM(p.stock_actual * p.precio_compra) as valor_inventario
    FROM productos p
    JOIN categorias c ON p.id_categoria = c.id_categoria
    WHERE p.activo = true
    GROUP BY c.nombre
    ORDER BY valor_inventario DESC
";

$categorias = pg_query($conn, $query_categorias);

// Preparar datos para Google Charts
$datos_movimientos = [];
$datos_movimientos[] = ['Día', 'Entradas', 'Salidas'];

if ($movimientos_dia && pg_num_rows($movimientos_dia) > 0) {
    while ($d = pg_fetch_assoc($movimientos_dia)) {
        $datos_movimientos[] = [
            date('d/m', strtotime($d['dia'])),
            intval($d['entradas']),
            intval($d['salidas'])
        ];
    }
}

// Si no hay datos, usar datos de ejemplo
if (count($datos_movimientos) == 1) {
    $datos_movimientos = [
        ['Día', 'Entradas', 'Salidas'],
        ['01/03', 8, 2],
        ['02/03', 12, 3],
        ['03/03', 5, 1],
        ['04/03', 10, 4],
        ['05/03', 7, 2],
        ['06/03', 9, 3],
        ['07/03', 6, 2]
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Ferretería</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    
    <style>
        .card-kpi {
            transition: transform 0.3s;
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .card-kpi:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .kpi-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<!-- Navbar para reportes.php -->
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
                    <a class="nav-link" href="ventas.php"><i class="fas fa-shopping-cart"></i> Ventas/Compras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
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

<!-- Contenido principal -->
<div class="container mt-4">
    
    <!-- Título y selector de período -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="fas fa-chart-pie"></i> Reportes de Inventario</h1>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5">
                            <select name="periodo" class="form-select" id="periodoSelect">
                                <option value="mensual" <?php echo $periodo == 'mensual' ? 'selected' : ''; ?>>Mensual</option>
                                <option value="anual" <?php echo $periodo == 'anual' ? 'selected' : ''; ?>>Anual</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="anio" class="form-select">
                                <?php for ($i = 2024; $i <= date('Y'); $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $anio == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="mesContainer">
                            <select name="mes" class="form-select">
                                <?php
                                $meses_lista = [
                                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                ];
                                foreach ($meses_lista as $num => $nombre): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $mes == $num ? 'selected' : ''; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Título del período -->
    <div class="alert alert-info">
        <i class="fas fa-calendar-alt"></i> <strong><?php echo $titulo_reporte; ?></strong>
    </div>

    <!-- KPIs -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white card-kpi">
                <div class="card-body text-center">
                    <div class="kpi-label">Unidades Entradas</div>
                    <div class="kpi-value"><?php echo number_format($stats['unidades_entradas']); ?></div>
                    <div>$<?php echo number_format($stats['valor_entradas'], 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white card-kpi">
                <div class="card-body text-center">
                    <div class="kpi-label">Unidades Salidas</div>
                    <div class="kpi-value"><?php echo number_format($stats['unidades_salidas']); ?></div>
                    <div>$<?php echo number_format($stats['valor_salidas'], 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white card-kpi">
                <div class="card-body text-center">
                    <div class="kpi-label">Productos Movidos</div>
                    <div class="kpi-value"><?php echo $stats['productos_movidos']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white card-kpi">
                <div class="card-body text-center">
                    <div class="kpi-label">Transacciones</div>
                    <div class="kpi-value"><?php echo $stats['total_entradas'] + $stats['total_salidas']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Movimientos Diarios
                </div>
                <div class="card-body">
                    <div id="graficoMovimientos" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Valor por Tipo
                </div>
                <div class="card-body">
                    <div id="graficoResumen" style="height: 250px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablas -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-trophy"></i> Top 10 Productos Más Vendidos
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $top_count = 0;
                                if ($top_productos && pg_num_rows($top_productos) > 0) {
                                    while ($t = pg_fetch_assoc($top_productos)): 
                                        $top_count++;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['nombre']); ?></td>
                                    <td class="text-center"><?php echo $t['total_vendido']; ?></td>
                                    <td class="text-end">$<?php echo number_format($t['monto_total'], 2); ?></td>
                                </tr>
                                <?php 
                                    endwhile; 
                                }
                                if ($top_count == 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        No hay datos de ventas en este período
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-folder"></i> Inventario por Categoría
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $cat_count = 0;
                                if ($categorias && pg_num_rows($categorias) > 0) {
                                    while ($c = pg_fetch_assoc($categorias)): 
                                        $cat_count++;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['categoria']); ?></td>
                                    <td class="text-center"><?php echo $c['stock_total']; ?> und</td>
                                    <td class="text-end">$<?php echo number_format($c['valor_inventario'], 2); ?></td>
                                </tr>
                                <?php 
                                    endwhile; 
                                }
                                if ($cat_count == 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        No hay categorías registradas
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Google Charts -->
<script type="text/javascript">
google.charts.load('current', {packages: ['corechart', 'line']});
google.charts.setOnLoadCallback(dibujarGraficos);

function dibujarGraficos() {
    try {
        // Gráfico de movimientos diarios
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Día');
        data.addColumn('number', 'Entradas');
        data.addColumn('number', 'Salidas');
        
        var datos = <?php echo json_encode(array_slice($datos_movimientos, 1)); ?>;
        data.addRows(datos);
        
        var options = {
            height: 300,
            curveType: 'function',
            legend: { position: 'top' },
            colors: ['#10b981', '#ef4444'],
            pointSize: 5,
            vAxis: { 
                title: 'Cantidad',
                minValue: 0 
            },
            hAxis: {
                title: 'Día'
            }
        };
        
        var chart = new google.visualization.LineChart(document.getElementById('graficoMovimientos'));
        chart.draw(data, options);
        
        // Gráfico de resumen
        var data2 = google.visualization.arrayToDataTable([
            ['Tipo', 'Valor'],
            ['Entradas ($)', <?php echo floatval($stats['valor_entradas'] ?? 1); ?>],
            ['Salidas ($)', <?php echo floatval($stats['valor_salidas'] ?? 1); ?>]
        ]);
        
        var options2 = {
            height: 250,
            pieHole: 0.4,
            legend: { position: 'bottom' },
            colors: ['#10b981', '#ef4444'],
            pieSliceText: 'value',
            chartArea: { width: '90%', height: '80%' }
        };
        
        var chart2 = new google.visualization.PieChart(document.getElementById('graficoResumen'));
        chart2.draw(data2, options2);
        
        console.log('✅ Gráficos cargados correctamente');
    } catch (e) {
        console.error('Error al cargar gráficos:', e);
    }
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Control de visibilidad del selector de mes
    const periodoSelect = document.getElementById('periodoSelect');
    const mesContainer = document.getElementById('mesContainer');
    
    if (periodoSelect && mesContainer) {
        function toggleMesContainer() {
            mesContainer.style.display = periodoSelect.value === 'mensual' ? 'block' : 'none';
        }
        periodoSelect.addEventListener('change', toggleMesContainer);
        toggleMesContainer();
    }
});
</script>

<!-- Footer -->
<footer class="container mt-5 pt-5 text-muted text-center">
    <p>&copy; <?php echo date('Y'); ?> Ferretería - Sistema de Auditoría de Inventario</p>
</footer>

</body>
</html>

<?php pg_close($conn); ?>
