<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Wrapper PDO simple. On expose une seule methode pdo() pour les repositories.
 * Lazy : la connexion n'est etablie qu'a la premiere utilisation.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
            $this->config['name']
        );

        $this->pdo = new PDO(
            $dsn,
            $this->config['user'],
            $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]
        );

        return $this->pdo;
    }
}
