<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Foundation\Application;

interface ServiceProvider
{
    public function register(Application $application): void;

    public function boot(Application $application): void;
}
