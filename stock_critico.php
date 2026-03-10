<?php
session_start();
include_once 'db_connect.php';

// Marcar como resuelto - CORREGIDO con el nombre correcto de columna
if (isset($_GET['resolver'])) {
    $id = intval($_GET['resolver']);
    $query = "UPDATE historico_stockcritico SET estado = 'resuelto' WHERE id_registro = $id";
    $result = pg_query($conn, $query);
    
    if ($result) {
        header("Location: stock_critico.php?success=1");
    } else {
        header("Location: stock_critico.php?error=1");
    }
    exit();
}

// Obtener histórico de stock crítico - AHORA CON LOS NOMBRES CORRECTOS
$query = "
    SELECT 
        h.id_registro,
        h.id_producto,
        h.nombre_producto,
        h.stock_actual,
        h.stock_minimo,
        h.deficit,
        h.fecha_deteccion,
        h.estado,
        (SELECT fecha_hora FROM movimientos_inventario 
         WHERE id_producto = h.id_producto 
         AND tipo_movimiento = 'entrada'
         AND fecha_hora > h.fecha_deteccion
         ORDER BY fecha_hora LIMIT 1) as fecha_reabastecimiento
    FROM historico_stockcritico h
    ORDER BY 
        CASE WHEN h.estado = 'pendiente' THEN 1 ELSE 2 END,
        h.fecha_deteccion DESC
";

$historico = pg_query($conn, $query);

if (!$historico) {
    die("Error en la consulta: " . pg_last_error($conn));
}

// Guardar resultados en un array
$rows = [];
$pendientes = 0;
$resueltos = 0;

while ($row = pg_fetch_assoc($historico)) {
    $rows[] = $row;
    if ($row['estado'] == 'pendiente') {
        $pendientes++;
    } else {
        $resueltos++;
    }
}

// Obtener mensajes de éxito/error
$success_message = isset($_GET['success']) ? 'Alerta resuelta correctamente' : '';
$error_message = isset($_GET['error']) ? 'Error al resolver la alerta' : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Crítico - Ferretería</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        .card-kpi {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 10px;
        }
        .card-kpi:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .table-danger {
            background-color: rgba(254, 202, 202, 0.5);
        }
        .table-success {
            background-color: rgba(209, 250, 229, 0.5);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<!-- Navbar para stock_critico.php -->
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
                    <a class="nav-link active" href="stock_critico.php"><i class="fas fa-exclamation-triangle"></i> Stock Crítico</a>
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

<!-- Contenido principal -->
<div class="container mt-4">
    
    <h1 class="mb-4"><i class="fas fa-exclamation-triangle text-warning"></i> Historial de Stock Crítico</h1>
    
    <!-- Mensajes de éxito/error -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Resumen de KPIs -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card bg-danger text-white card-kpi h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Alertas Pendientes</h5>
                            <h2 class="mb-0 display-4"><?php echo $pendientes; ?></h2>
                            <small>Requieren atención inmediata</small>
                        </div>
                        <i class="fas fa-clock fa-4x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card bg-success text-white card-kpi h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Alertas Resueltas</h5>
                            <h2 class="mb-0 display-4"><?php echo $resueltos; ?></h2>
                            <small>Ya fueron solucionadas</small>
                        </div>
                        <i class="fas fa-check-circle fa-4x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de histórico -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history text-primary"></i> Historial de Alertas</h5>
                <a href="?refresh=1" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($rows) > 0): ?>
                <div class="table-responsive">
                    <table id="tablaStockCritico" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Déficit</th>
                                <th>Fecha Detección</th>
                                <th>Estado</th>
                                <th>Fecha Reabastecimiento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $h): ?>
                            <tr class="<?php echo $h['estado'] == 'pendiente' ? 'table-danger' : 'table-success'; ?>">
                                <td><strong><?php echo $h['id_registro']; ?></strong></td>
                                <td><?php echo htmlspecialchars($h['nombre_producto']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $h['stock_actual'] < $h['stock_minimo'] ? 'danger' : 'success'; ?>">
                                        <?php echo $h['stock_actual']; ?>
                                    </span>
                                </td>
                                <td class="text-center"><?php echo $h['stock_minimo']; ?></td>
                                <td class="text-center">
                                    <strong class="text-danger">-<?php echo $h['deficit']; ?></strong>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($h['fecha_deteccion'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $h['estado'] == 'pendiente' ? 'warning' : 'success'; ?> text-dark">
                                        <i class="fas fa-<?php echo $h['estado'] == 'pendiente' ? 'hourglass-half' : 'check'; ?>"></i>
                                        <?php echo strtoupper($h['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($h['fecha_reabastecimiento']) {
                                        echo '<span class="badge bg-info">' . date('d/m/Y H:i', strtotime($h['fecha_reabastecimiento'])) . '</span>';
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($h['estado'] == 'pendiente'): ?>
                                        <a href="?resolver=<?php echo $h['id_registro']; ?>" 
                                           class="btn btn-success btn-sm" 
                                           onclick="return confirm('¿Estás seguro de marcar esta alerta como resuelta?\n\nProducto: <?php echo addslashes($h['nombre_producto']); ?>\nDéficit: <?php echo $h['deficit']; ?> unidades')">
                                            <i class="fas fa-check"></i> Resolver
                                        </a>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Resuelto</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4>¡No hay alertas de stock crítico!</h4>
                    <p class="text-muted">Todos los productos tienen stock suficiente.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="my-5 pt-5 text-muted text-center">
        <p>&copy; <?php echo date('Y'); ?> Ferretería - Sistema de Auditoría de Inventario</p>
        <small class="text-muted">Total de registros: <?php echo count($rows); ?></small>
    </footer>
    
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializar DataTable con configuración en español
    $('#tablaStockCritico').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [[5, 'desc']], // Ordenar por fecha de detección (columna 5)
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
        columnDefs: [
            { orderable: false, targets: [8] } // Desactivar orden en columna de acciones
        ]
    });
    
    // Auto-cerrar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});
</script>

</body>
</html>

<?php
// Cerrar conexión
pg_close($conn);
?>
