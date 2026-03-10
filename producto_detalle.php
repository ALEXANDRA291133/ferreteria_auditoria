<?php
session_start();
include_once 'db_connect.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: productos.php");
    exit();
}

$producto_id = intval($_GET['id']);

// PRIMERO: Verificar que el producto existe
$query_check = "SELECT * FROM productos WHERE id_producto = $producto_id";
$result_check = pg_query($conn, $query_check);

if (!$result_check || pg_num_rows($result_check) == 0) {
    header("Location: productos.php?error=producto_no_encontrado");
    exit();
}

// CORREGIDO: Usando SOLO las columnas que existen en tu tabla
$query = "
    SELECT 
        p.id_producto,
        p.codigo_barras,
        p.nombre,
        p.descripcion,
        p.id_categoria,
        p.precio_compra,
        p.precio_venta,
        p.stock_actual,
        p.stock_minimo,
        p.unidad_medida,
        p.activo,
        c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
    WHERE p.id_producto = $producto_id
";

$result = pg_query($conn, $query);

if (!$result) {
    die("Error en la consulta: " . pg_last_error($conn));
}

$producto = pg_fetch_assoc($result);

if (!$producto) {
    header("Location: productos.php?error=producto_no_encontrado");
    exit();
}

// VALIDAR CAMPOS
$campos = [
    'codigo_barras' => 'S/C',
    'nombre' => 'Producto sin nombre',
    'categoria_nombre' => 'Sin categoría',
    'descripcion' => 'Sin descripción',
    'precio_compra' => 0,
    'precio_venta' => 0,
    'stock_actual' => 0,
    'stock_minimo' => 0,
    'unidad_medida' => 'Unidad',
    'id_producto' => $producto_id
];

foreach ($campos as $campo => $default) {
    if (!isset($producto[$campo]) || $producto[$campo] === null || $producto[$campo] === '') {
        $producto[$campo] = $default;
    }
}

// Obtener movimientos
$query_mov = "
    SELECT m.*, u.nombre_usuario as usuario
    FROM movimientos_inventario m
    LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
    WHERE m.id_producto = $producto_id
    ORDER BY m.fecha_hora DESC
    LIMIT 20
";

$movimientos = pg_query($conn, $query_mov);

// Obtener historial de stock crítico
$query_hist = "
    SELECT * FROM historico_stockcritico
    WHERE id_producto = $producto_id
    ORDER BY fecha_deteccion DESC
";

$historico = pg_query($conn, $query_hist);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Producto - <?php echo htmlspecialchars($producto['nombre']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
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
        .status-badge {
            font-size: 1rem;
            padding: 8px 15px;
        }
        .valor-destacado {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .no-image-placeholder {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<!-- Navbar para producto_detalle.php -->
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
                    <a class="nav-link active" href="productos.php"><i class="fas fa-box"></i> Productos</a>
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
    
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-box-open"></i> 
            Detalle del Producto
        </h1>
        <a href="productos.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Productos
        </a>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">Producto no encontrado</div>
    <?php endif; ?>

    <!-- Información del producto -->
    <div class="row">
        <div class="col-md-12">
            <!-- Información General -->
            <div class="card info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Información General
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">ID Producto:</th>
                                    <td><span class="badge bg-secondary"><?php echo $producto['id_producto']; ?></span></td>
                                </tr>
                                <tr>
                                    <th>Código de Barras:</th>
                                    <td><strong><?php echo htmlspecialchars($producto['codigo_barras']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Nombre:</th>
                                    <td><h4><?php echo htmlspecialchars($producto['nombre']); ?></h4></td>
                                </tr>
                                <tr>
                                    <th>Categoría:</th>
                                    <td><?php echo htmlspecialchars($producto['categoria_nombre']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Descripción:</th>
                                    <td><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Unidad de Medida:</th>
                                    <td><?php echo htmlspecialchars($producto['unidad_medida']); ?></td>
                                </tr>
                                <tr>
                                    <th>Activo:</th>
                                    <td>
                                        <?php if ($producto['activo'] == 't' || $producto['activo'] === true): ?>
                                            <span class="badge bg-success">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock y Precios -->
            <div class="card info-card">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i> Stock y Precios
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Stock Actual:</th>
                                    <td>
                                        <span class="badge <?php 
                                            $stock = intval($producto['stock_actual']);
                                            $minimo = intval($producto['stock_minimo']);
                                            
                                            if ($stock <= 0) {
                                                echo 'bg-danger';
                                            } elseif ($stock < $minimo) {
                                                echo 'bg-warning';
                                            } else {
                                                echo 'bg-success';
                                            }
                                        ?> status-badge">
                                            <?php echo $stock; ?> <?php echo $producto['unidad_medida']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Stock Mínimo:</th>
                                    <td><?php echo $minimo; ?> <?php echo $producto['unidad_medida']; ?></td>
                                </tr>
                                <tr>
                                    <th>Estado:</th>
                                    <td>
                                        <?php 
                                        if ($stock <= 0) {
                                            echo '<span class="badge bg-danger">SIN STOCK</span>';
                                        } elseif ($stock < $minimo) {
                                            echo '<span class="badge bg-warning">CRÍTICO</span>';
                                        } else {
                                            echo '<span class="badge bg-success">NORMAL</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Precio Compra:</th>
                                    <td class="valor-destacado">$<?php echo number_format(floatval($producto['precio_compra']), 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Precio Venta:</th>
                                    <td class="valor-destacado">$<?php echo number_format(floatval($producto['precio_venta']), 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Valor Inventario:</th>
                                    <td class="valor-destacado text-primary">
                                        $<?php echo number_format($stock * floatval($producto['precio_compra']), 2); ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos movimientos -->
    <div class="card info-card mt-4">
        <div class="card-header">
            <i class="fas fa-history"></i> Últimos Movimientos
        </div>
        <div class="card-body">
            <?php if ($movimientos && pg_num_rows($movimientos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock Anterior</th>
                                <th>Stock Posterior</th>
                                <th>Usuario</th>
                                <th>Motivo</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($mov = pg_fetch_assoc($movimientos)): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_hora'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $mov['tipo_movimiento'] == 'entrada' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($mov['tipo_movimiento']); ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold"><?php echo $mov['cantidad']; ?></td>
                                <td class="text-center"><?php echo $mov['stock_anterior']; ?></td>
                                <td class="text-center"><?php echo $mov['stock_posterior']; ?></td>
                                <td><?php echo htmlspecialchars($mov['usuario'] ?? 'Sistema'); ?></td>
                                <td><?php echo htmlspecialchars($mov['motivo']); ?></td>
                                <td><?php echo htmlspecialchars($mov['referencia'] ?? '-'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay movimientos registrados para este producto</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historial de stock crítico -->
    <div class="card info-card mt-4">
        <div class="card-header">
            <i class="fas fa-exclamation-triangle"></i> Historial de Alertas de Stock
        </div>
        <div class="card-body">
            <?php if ($historico && pg_num_rows($historico) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha Detección</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Déficit</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($hist = pg_fetch_assoc($historico)): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($hist['fecha_deteccion'])); ?></td>
                                <td class="text-center"><?php echo $hist['stock_actual']; ?></td>
                                <td class="text-center"><?php echo $hist['stock_minimo']; ?></td>
                                <td class="text-center text-danger fw-bold"><?php echo $hist['deficit']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $hist['estado'] == 'pendiente' ? 'warning' : 'success'; ?>">
                                        <?php echo strtoupper($hist['estado']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="text-muted">No hay alertas de stock crítico para este producto</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="mt-4 mb-5">
        <a href="productos.php?edit=<?php echo $producto['id_producto']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Editar Producto
        </a>
        <a href="movimientos.php?producto=<?php echo $producto['id_producto']; ?>" class="btn btn-info">
            <i class="fas fa-exchange-alt"></i> Ver Todos los Movimientos
        </a>
        <a href="stock_critico.php" class="btn btn-secondary">
            <i class="fas fa-exclamation-circle"></i> Ver Alertas
        </a>
    </div>

</div>

<!-- Footer -->
<footer class="container mt-5 pt-5 text-muted text-center">
    <p>&copy; <?php echo date('Y'); ?> Ferretería - Sistema de Auditoría de Inventario</p>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php pg_close($conn); ?>
