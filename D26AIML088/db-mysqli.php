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

$validatedPort = filter_var($databasePort, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);

if ($validatedPort === false) {
    throw new RuntimeException('STUDENTHUB_DB_PORT must be a valid TCP port.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$connection = new mysqli(
    $databaseHost,
    $databaseUser,
    $databasePassword,
    $databaseName,
    $validatedPort
);
$connection->set_charset('utf8mb4');

return $connection;
