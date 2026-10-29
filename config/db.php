<?php
declare(strict_types=1);

function envValue(string $name, ?string $default = null): ?string
{
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
    return ($value === false || $value === '') ? $default : (string) $value;
}

function getDatabase(): PDO
{
    $host = envValue('DB_HOST');
    $user = envValue('DB_USER');
    $password = envValue('DB_PASSWORD');
    if (!$host || !$user || !$password) {
        throw new RuntimeException('Database environment variables are missing.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        envValue('DB_PORT', '3306'),
        envValue('DB_NAME', 'defaultdb')
    );
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $caPath = dirname(__DIR__) . '/certs/ca.pem';
    if (str_contains($host, 'aivencloud.com') && !is_file($caPath)) {
        throw new RuntimeException('Place the Aiven CA certificate at certs/ca.pem.');
    }
    if (is_file($caPath)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    return new PDO($dsn, $user, $password, $options);
}
