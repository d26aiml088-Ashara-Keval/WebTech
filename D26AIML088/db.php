<?php
declare(strict_types=1);

$databaseHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
$databasePort = getenv('STUDENTHUB_DB_PORT') ?: '3306';
$databaseName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub';
$databaseUser = getenv('STUDENTHUB_DB_USER');
$databasePassword = getenv('STUDENTHUB_DB_PASSWORD');

if ($databaseUser === false || $databaseUser === '') {
    throw new RuntimeException('STUDENTHUB_DB_USER must be configured.');
}

if ($databasePassword === false) {
    throw new RuntimeException('STUDENTHUB_DB_PASSWORD must be configured. It may be empty for a local XAMPP account.');
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $databaseHost,
    $databasePort,
    $databaseName
);

return new PDO($dsn, $databaseUser, $databasePassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
