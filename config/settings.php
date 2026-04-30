<?php

declare(strict_types=1);

/**
 * Reglages applicatifs centralises.
 * Toutes les valeurs sensibles viennent de l'environnement.
 */
return [
    'settings' => [
        'app' => [
            'env' => $_ENV['APP_ENV'] ?? 'prod',
            'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
            // URL publique du frontend (Vercel en prod). Sert a construire
            // les liens dans les emails (ex. lien reset password).
            // ATTENTION : ne PAS retomber sur APP_URL ici, qui pointe sur l'API
            // et donnerait un lien casse dans les mails. En prod, FRONTEND_URL
            // doit etre defini explicitement (env Render). Le localhost n'est
            // qu'un dernier filet pour le dev local.
            'frontendUrl' => $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173',
            'secret' => $_ENV['APP_SECRET'] ?? 'change-me',
            // Liste blanche d'origines autorisees pour CORS (separees par virgule).
            // Vide = pas de header CORS (mode local meme origine).
            'corsAllowedOrigins' => $_ENV['CORS_ALLOWED_ORIGINS'] ?? '',
        ],
        'db' => [
            // mysql en local (Docker Compose), pgsql en cloud (Supabase)
            'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'db',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'name' => $_ENV['DB_NAME'] ?? 'espace_privatif',
            'user' => $_ENV['DB_USER'] ?? 'app',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            // SSL pour PG cloud (Supabase). Valeurs typiques : require, prefer, disable.
            'sslmode' => $_ENV['DB_SSLMODE'] ?? 'require',
        ],
        'mail' => [
            // Provider unique : Resend (HTTPS API, pas de SMTP).
            'apiKey' => $_ENV['RESEND_API_KEY'] ?? '',
            'from' => $_ENV['MAIL_FROM'] ?? 'no-reply@realsoft.espace.privatif',
            'fromName' => $_ENV['MAIL_FROM_NAME'] ?? 'Espace Privatif',
        ],
        'session' => [
            'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
        ],
        'otp' => [
            'length' => (int) ($_ENV['OTP_LENGTH'] ?? 6),
            'ttl' => (int) ($_ENV['OTP_TTL_SECONDS'] ?? 300),
            'maxAttempts' => (int) ($_ENV['OTP_MAX_ATTEMPTS'] ?? 3),
        ],
        'ws' => [
            'host' => $_ENV['WS_HOST'] ?? '0.0.0.0',
            'port' => (int) ($_ENV['WS_PORT'] ?? 8081),
            'tokenSecret' => $_ENV['WS_TOKEN_SECRET'] ?? 'change-me-ws',
        ],
        'sothis' => [
            'callbackUrl' => $_ENV['SOTHIS_CALLBACK_URL'] ?? '',
            'apiKey' => $_ENV['SOTHIS_API_KEY'] ?? '',
        ],
        'logger' => [
            'name' => 'espace-privatif',
            'path' => __DIR__ . '/../var/log/app.log',
            'level' => $_ENV['APP_DEBUG'] === 'true' ? 'debug' : 'info',
        ],
    ],
];
