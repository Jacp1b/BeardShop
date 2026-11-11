<?php
declare(strict_types=1);

function conexion(): PDO
{
    $host = getenv('BEARDSHOP_DB_HOST') ?: '127.0.0.1';
    $port = getenv('BEARDSHOP_DB_PORT') ?: '3306';
    $database = getenv('BEARDSHOP_DB_NAME') ?: 'db_barberia';
    $username = getenv('BEARDSHOP_DB_USER') ?: 'root';
    $password = getenv('BEARDSHOP_DB_PASSWORD') ?: '';

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '-05:00'");

    return $pdo;
}
