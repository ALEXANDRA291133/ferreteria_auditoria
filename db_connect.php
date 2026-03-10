<?php
// db_connect.php - Versión para Netlify (casi igual a la que ya tienes)

// Intentar obtener la variable de entorno de Netlify
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Estamos en Netlify - usar la URL completa
    $conn = pg_connect($database_url);
} else {
    // Estamos en local - usar tus variables manuales
    $host = "ep-late-mode-acfuh4o4-pooler.sa-east-1.aws.neon.tech";
    $port = "5432";
    $dbname = "neondb";
    $user = "neondb_owner";
    $password = "npg_SiAp6agJO1Qs";
    
    $conn_str = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";
    $conn = pg_connect($conn_str);
}

if (!$conn) {
    die("Error de conexión a la base de datos");
}

pg_query($conn, "SET NAMES 'UTF8'");
?>