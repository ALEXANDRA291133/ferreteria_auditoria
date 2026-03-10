<?php
session_start();
include_once 'db_connect.php';


// Ahora puedes usar $conn para todas las consultas
$result = pg_query($conn, "SELECT * FROM productos");
while ($row = pg_fetch_assoc($result)) {
    // procesar datos
}
// Obtener productos con información de categoría
$productos = pg_query($conn, "
    SELECT p.*, c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
    ORDER BY 
        CASE 
            WHEN p.stock_actual <= 0 THEN 1
            WHEN p.stock_actual < p.stock_minimo THEN 2
            ELSE 3
        END,
        p.nombre
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Auditoría de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-warehouse"></i> Ferretería - Inventario</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="productos.php">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="movimientos.php">Movimientos</a></li>
                    <li class="nav-item"><a class="nav-link" href="stock_critico.php">Stock Crítico</a></li>
                    <li class="nav-item"><a class="nav-link" href="ventas.php">Ventas/Compras</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">Productos en Inventario</h1>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaProductos" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Código Barras</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Estado</th>
                                <th>Precio Compra</th>
                                <th>Precio Venta</th>
                                <th>Valor Inventario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = pg_fetch_assoc($productos)): 
                                $estado_class = '';
                                $estado_text = '';
                                if ($p['stock_actual'] <= 0) {
                                    $estado_class = 'danger';
                                    $estado_text = 'SIN STOCK';
                                } elseif ($p['stock_actual'] < $p['stock_minimo']) {
                                    $estado_class = 'warning';
                                    $estado_text = 'CRÍTICO';
                                } else {
                                    $estado_class = 'success';
                                    $estado_text = 'NORMAL';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['codigo_barras']); ?></td>
                                <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($p['categoria_nombre']); ?></td>
                                <td class="text-center fw-bold"><?php echo $p['stock_actual']; ?></td>
                                <td class="text-center"><?php echo $p['stock_minimo']; ?></td>
                                <td><span class="badge bg-<?php echo $estado_class; ?>"><?php echo $estado_text; ?></span></td>
                                <td>$<?php echo number_format($p['precio_compra'], 2); ?></td>
                                <td>$<?php echo number_format($p['precio_venta'], 2); ?></td>
                                <td>$<?php echo number_format($p['stock_actual'] * $p['precio_compra'], 2); ?></td>
                                <td>
                                    <a href="producto_detalle.php?id=<?php echo $p['id_producto']; ?>" class="btn btn-info btn-sm">
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaProductos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[3, 'asc']] // Ordenar por stock actual
            });
        });
    </script>
</body>
</html>
<?php pg_close($conn); ?>