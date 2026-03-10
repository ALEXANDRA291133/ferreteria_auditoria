<?php
// db_connect.php - Versión para producción en Render
$host = getenv('DB_HOST') ?: "ep-late-mode-acfuh4o4-pooler.sa-east-1.aws.neon.tech";
$port = getenv('DB_PORT') ?: "5432";
$dbname = getenv('DB_NAME') ?: "neondb";
$user = getenv('DB_USER') ?: "neondb_owner";
$password = getenv('DB_PASS') ?: "npg_SiAp6agJO1Qs";

$conn_str = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";
$conn = pg_connect($conn_str);

if (!$conn) {
    // En producción, no mostrar errores detallados
    error_log("Error de conexión a la base de datos: " . pg_last_error());
    die("Error de conexión a la base de datos. Por favor, intente más tarde.");
}

// Configurar cliente para UTF-8
pg_query($conn, "SET NAMES 'UTF8'");
?>