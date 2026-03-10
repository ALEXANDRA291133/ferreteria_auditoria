<?php
// db_connect.php - Compatible con Railway y Neon

// Intentar obtener la conexión de Railway (variable DATABASE_URL)
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Estamos en Railway (o en un entorno con DATABASE_URL)
    $conn = pg_connect($database_url);
} else {
    // Estamos en local o en Render con variables sueltas
    $host = getenv('DB_HOST') ?: "ep-late-mode-acfuh4o4-pooler.sa-east-1.aws.neon.tech";
    $port = getenv('DB_PORT') ?: "5432";
    $dbname = getenv('DB_NAME') ?: "neondb";
    $user = getenv('DB_USER') ?: "neondb_owner";
    $password = getenv('DB_PASS') ?: "npg_SiAp6agJO1Qs";
    
    $conn_str = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";
    $conn = pg_connect($conn_str);
}

// Verificar conexión
if (!$conn) {
    error_log("Error de conexión a la base de datos: " . pg_last_error());
    die("Error de conexión a la base de datos. Por favor, intente más tarde.");
}

pg_query($conn, "SET NAMES 'UTF8'");
?>
