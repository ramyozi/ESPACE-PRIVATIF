<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use Slim\App;

return function (App $app): void {
    // Sante du service (utile pour le healthcheck Docker et la verification rapide)
    $app->get('/health', [HealthController::class, 'check']);
};
