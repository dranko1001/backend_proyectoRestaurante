<?php

declare(strict_types=1);

/**
 * Importa el dump `restaurante.sql` en la BD configurada por .env
 * (pensado para entornos locales tipo phpMyAdmin/MariaDB).
 */

$root = dirname(__DIR__);
$sqlPath = dirname($root) . DIRECTORY_SEPARATOR . 'restaurante.sql';

if (! file_exists($sqlPath)) {
    fwrite(STDERR, "No existe el archivo: {$sqlPath}\n");
    exit(1);
}

// Cargar .env muy simple (sin dependencias).
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (! str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, "\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv("{$k}={$v}");
        }
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: 'restaurante';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

if (! extension_loaded('mysqli')) {
    fwrite(STDERR, "Extensión mysqli no disponible en PHP.\n");
    exit(1);
}

$mysqli = @new mysqli($host, $user, $pass, $db, (int) $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "No se pudo conectar a MySQL: {$mysqli->connect_error}\n");
    exit(1);
}

$mysqli->set_charset('utf8mb4');

$sql = file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer el dump.\n");
    exit(1);
}

// Quitar comentarios "-- ..." y bloques /*! ... */ típicos del dump.
$lines = preg_split("/\r\n|\n|\r/", $sql) ?: [];
$filtered = [];
foreach ($lines as $ln) {
    $t = ltrim($ln);
    if (str_starts_with($t, '--')) continue;
    $filtered[] = $ln;
}
$sql = implode("\n", $filtered);

// Remover bloques tipo /*!40101 ... */; (son opcionales).
$sql = preg_replace('/\/\*![\s\S]*?\*\//', '', $sql) ?? $sql;

// Ejecutar sentencia por sentencia para tolerar ';' vacíos.
$statements = preg_split("/;\s*\n/", $sql) ?: [];
$count = 0;

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;

    // mysql/mariadb no acepta ';' al final en query()
    if (! $mysqli->query($stmt)) {
        $preview = substr($stmt, 0, 180);
        fwrite(STDERR, "Error importando SQL (preview): {$preview}\n");
        fwrite(STDERR, "MySQL error: {$mysqli->error}\n");
        exit(1);
    }
    $count++;
}

fwrite(STDOUT, "OK: dump importado en '{$db}'. Sentencias ejecutadas: {$count}\n");

