<?php
// CONEXIÓN A NEON CONSOLE (PostgreSQL) - VERSIÓN CORREGIDA

// Datos de tu conexión de Neon
$host = "ep-late-mode-acfuh4o4-pooler.sa-east-1.aws.neon.tech";
$port = "5432";
$dbname = "neondb";
$user = "neondb_owner";
$password = "npg_SiAp6agJO1Qs";
$endpoint = "ep-late-mode-acfuh4o4-pooler"; // Importante: el endpoint ID

// Método 1: Con options (el que funciona con Neon)
$options = "endpoint=" . urlencode($endpoint);
$conn_str = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require options='$options'";

// Intentar conectar
$conn = pg_connect($conn_str);

// Si falla, intentar con el string completo
if (!$conn) {
    $conn_str = "postgresql://$user:$password@$host:$port/$dbname?sslmode=require&options=endpoint%3D$endpoint";
    $conn = pg_connect($conn_str);
}

// Si aún falla, intentar sin options
if (!$conn) {
    $conn_str = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";
    $conn = pg_connect($conn_str);
}

// Verificar conexión
if (!$conn) {
    $error = pg_last_error();
    die("<h2 style='color: #e53e3e;'>Error de conexión a Neon</h2>
         <p><strong>Mensaje:</strong> " . htmlspecialchars($error) . "</p>
         <p><strong>Datos utilizados:</strong></p>
         <ul>
             <li>Host: $host</li>
             <li>Puerto: $port</li>
             <li>Base de datos: $dbname</li>
             <li>Usuario: $user</li>
             <li>Endpoint: $endpoint</li>
         </ul>
         <p><strong>Verifica:</strong> Que los datos sean correctos y que tu IP esté permitida en Neon.</p>");
}

// Configurar cliente para UTF-8
pg_query($conn, "SET NAMES 'UTF8'");

// Mensaje de éxito (opcional, puedes comentarlo después)
// echo "<p style='color:green;'>✅ Conexión exitosa a Neon Console</p>";
?>
