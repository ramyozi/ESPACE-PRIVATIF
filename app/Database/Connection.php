<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * Wrapper PDO simple. On expose une seule methode pdo() pour les repositories.
 * Lazy : la connexion n'est etablie qu'a la premiere utilisation.
 *
 * Supporte deux drivers PDO :
 *  - mysql  : utilise en local via Docker Compose (MySQL 8)
 *  - pgsql  : utilise en cloud via Supabase PostgreSQL
 *
 * Le choix se fait via la cle "driver" du config (par defaut "mysql"
 * pour rester compatible avec l'environnement local existant).
 */
final class Connection
{
    private ?PDO $pdo = null;

    /**
     * @param array{
     *   driver?:string, host:string, port:int, name:string,
     *   user:string, password:string
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = strtolower($this->config['driver'] ?? 'mysql');

        // On compose le DSN selon le driver. On ne fait rien d'exotique :
        // chaque driver a sa propre syntaxe de DSN documentee par PHP.
        switch ($driver) {
            case 'pgsql':
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['name'],
                );
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                break;

            case 'mysql':
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['name'],
                );
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
                ];
                break;

            default:
                throw new RuntimeException("Driver BDD non supporte : {$driver}");
        }

        $this->pdo = new PDO(
            $dsn,
            $this->config['user'],
            $this->config['password'],
            $options,
        );

        // Pour PostgreSQL, on aligne le fuseau de la session sur UTC pour
        // rester coherent avec MySQL (les DATETIME sont normalises).
        if ($driver === 'pgsql') {
            $this->pdo->exec("SET TIME ZONE 'UTC'");
        }

        return $this->pdo;
    }

    /**
     * Expose le driver actif pour les rares endroits ou la syntaxe
     * d'un INSERT ... ON CONFLICT / ON DUPLICATE KEY differe.
     */
    public function driver(): string
    {
        return strtolower($this->config['driver'] ?? 'mysql');
    }
}
