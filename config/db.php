<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = getenv('CARVASILVA_DB_HOST') ?: '127.0.0.1';
        $port = getenv('CARVASILVA_DB_PORT') ?: '3306';
        $dbName = getenv('CARVASILVA_DB_NAME') ?: 'carvasilva';
        $username = getenv('CARVASILVA_DB_USER') ?: 'root';
        $password = getenv('CARVASILVA_DB_PASS');

        if ($password === false) {
            $password = '';
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Falha ao conectar ao banco de dados.', 0, $exception);
        }

        return self::$connection;
    }
}

function db(): PDO
{
    return Database::getConnection();
}
