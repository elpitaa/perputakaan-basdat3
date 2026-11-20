<?php

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'db_perpustakaan';
const DB_USER = 'perpus_app';
const DB_PASS = 'password123';

function getDatabaseConnection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    if (!extension_loaded('pdo_sqlsrv')) {
        throw new RuntimeException('Driver PDO SQL Server (pdo_sqlsrv) belum terpasang di PHP.');
    }

    $dsn = 'sqlsrv:Server=' . DB_HOST . ';Database=' . DB_NAME;

    $connection = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}

function insertAndGetId(PDO $pdo, string $sql, array $parameters = []): int
{
    $identityColumn = (string) ($parameters['_identity_column'] ?? '');
    if ($identityColumn === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identityColumn)) {
        throw new InvalidArgumentException('Kolom identity untuk INSERT tidak valid.');
    }
    $sql = preg_replace('/\s+VALUES\s+/i', ' OUTPUT INSERTED.' . $identityColumn . ' VALUES ', $sql, 1);
    if ($sql === null) {
        throw new InvalidArgumentException('SQL INSERT tidak valid.');
    }
    unset($parameters['_identity_column']);
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return (int) $statement->fetchColumn();
}
