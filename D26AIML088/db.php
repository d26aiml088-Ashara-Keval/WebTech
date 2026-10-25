<?php
declare(strict_types=1);

function studentHubDatabaseSettings(): array
{
    $host = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
    $port = getenv('STUDENTHUB_DB_PORT') ?: '3306';
    $name = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub';
    $user = getenv('STUDENTHUB_DB_USER');
    $password = getenv('STUDENTHUB_DB_PASSWORD');

    if ($user === false || $user === '') {
        throw new RuntimeException('STUDENTHUB_DB_USER must be configured.');
    }

    if ($password === false) {
        throw new RuntimeException('STUDENTHUB_DB_PASSWORD must be configured. It may be empty for a local XAMPP account.');
    }

    $validatedPort = filter_var($port, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);

    if ($validatedPort === false) {
        throw new RuntimeException('STUDENTHUB_DB_PORT must be a valid TCP port.');
    }

    return [
        'host' => $host,
        'port' => $validatedPort,
        'name' => $name,
        'user' => $user,
        'password' => $password,
    ];
}

function createPdoConnection(): PDO
{
    $settings = studentHubDatabaseSettings();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $settings['host'],
        $settings['port'],
        $settings['name']
    );

    return new PDO($dsn, $settings['user'], $settings['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function createMysqliConnection(): mysqli
{
    if (!class_exists(mysqli::class)) {
        throw new RuntimeException('The PHP mysqli extension must be enabled.');
    }

    $settings = studentHubDatabaseSettings();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $connection = new mysqli(
        $settings['host'],
        $settings['user'],
        $settings['password'],
        $settings['name'],
        $settings['port']
    );
    $connection->set_charset('utf8mb4');

    return $connection;
}
