<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\AuditLogRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\DocumentService;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions(require __DIR__ . '/settings.php');

    $containerBuilder->addDefinitions([

        LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
            $cfg = $c->get('settings')['logger'];
            $logger = new Logger($cfg['name']);
            $dir = dirname($cfg['path']);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $logger->pushHandler(new StreamHandler($cfg['path'], $cfg['level']));
            return $logger;
        },

        Connection::class => fn (ContainerInterface $c) => new Connection($c->get('settings')['db']),

        // Repositories
        UserRepository::class => fn (ContainerInterface $c) => new UserRepository($c->get(Connection::class)),
        DocumentRepository::class => fn (ContainerInterface $c) => new DocumentRepository($c->get(Connection::class)),
        AuditLogRepository::class => fn (ContainerInterface $c) => new AuditLogRepository($c->get(Connection::class)),

        // Services
        AuthService::class => fn (ContainerInterface $c) => new AuthService(
            $c->get(UserRepository::class),
            $c->get(LoggerInterface::class),
        ),
        AuditService::class => fn (ContainerInterface $c) => new AuditService($c->get(AuditLogRepository::class)),
        DocumentService::class => fn (ContainerInterface $c) => new DocumentService(
            $c->get(DocumentRepository::class),
            $c->get(AuditService::class),
        ),

    ]);
};
