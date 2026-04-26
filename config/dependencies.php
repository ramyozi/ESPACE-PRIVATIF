<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\SothisController;
use App\Database\Connection;
use App\Middleware\CsrfMiddleware;
use App\Security\CsrfTokenManager;
use App\Repositories\AuditLogRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\OtpRepository;
use App\Repositories\OutboxRepository;
use App\Repositories\SignatureRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\DocumentService;
use App\Services\MailService;
use App\Services\OtpService;
use App\Services\SignatureService;
use App\Services\SothisGateway;
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

        // Securite : gestionnaire de token CSRF en session + middleware
        CsrfTokenManager::class => fn () => new CsrfTokenManager(),
        CsrfMiddleware::class => fn (ContainerInterface $c) => new CsrfMiddleware($c->get(CsrfTokenManager::class)),

        // Controleur Auth (besoin d'acces au CsrfTokenManager pour l'exposer)
        AuthController::class => fn (ContainerInterface $c) => new AuthController(
            $c->get(\App\Services\AuthService::class),
            $c->get(\App\Repositories\UserRepository::class),
            $c->get(CsrfTokenManager::class),
        ),

        // Repositories
        UserRepository::class => fn (ContainerInterface $c) => new UserRepository($c->get(Connection::class)),
        DocumentRepository::class => fn (ContainerInterface $c) => new DocumentRepository($c->get(Connection::class)),
        AuditLogRepository::class => fn (ContainerInterface $c) => new AuditLogRepository($c->get(Connection::class)),
        OtpRepository::class => fn (ContainerInterface $c) => new OtpRepository($c->get(Connection::class)),
        OutboxRepository::class => fn (ContainerInterface $c) => new OutboxRepository($c->get(Connection::class)),
        SignatureRepository::class => fn (ContainerInterface $c) => new SignatureRepository($c->get(Connection::class)),

        // Services
        AuthService::class => fn (ContainerInterface $c) => new AuthService(
            $c->get(UserRepository::class),
            $c->get(LoggerInterface::class),
        ),
        AuditService::class => fn (ContainerInterface $c) => new AuditService($c->get(AuditLogRepository::class)),
        MailService::class => fn (ContainerInterface $c) => new MailService(
            $c->get(Connection::class),
            $c->get('settings')['mail'],
            $c->get(LoggerInterface::class),
        ),
        OtpService::class => fn (ContainerInterface $c) => new OtpService(
            $c->get(OtpRepository::class),
            $c->get(MailService::class),
            $c->get('settings')['otp'],
        ),
        DocumentService::class => fn (ContainerInterface $c) => new DocumentService(
            $c->get(DocumentRepository::class),
            $c->get(AuditService::class),
        ),
        SothisGateway::class => fn (ContainerInterface $c) => new SothisGateway(
            $c->get(OutboxRepository::class),
            $c->get(LoggerInterface::class),
        ),
        SignatureService::class => fn (ContainerInterface $c) => new SignatureService(
            $c->get(SignatureRepository::class),
            $c->get(DocumentRepository::class),
            $c->get(DocumentService::class),
            $c->get(OtpService::class),
            $c->get(MailService::class),
            $c->get(SothisGateway::class),
            $c->get(AuditService::class),
            $c->get(LoggerInterface::class),
        ),

        // Controleur s2s SOTHIS : auth par cle API statique (.env)
        SothisController::class => fn (ContainerInterface $c) => new SothisController(
            $c->get(DocumentRepository::class),
            $c->get(DocumentService::class),
            $c->get(UserRepository::class),
            $c->get(MailService::class),
            $c->get(AuditService::class),
            $c->get(LoggerInterface::class),
            (string) ($c->get('settings')['sothis']['apiKey'] ?? ''),
        ),

    ]);
};
