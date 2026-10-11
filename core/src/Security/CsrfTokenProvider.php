<?php

declare(strict_types=1);

namespace Jinstan\Core\Security;

interface CsrfTokenProvider
{
    public function value(): string;

    public function valid(?string $token): bool;
}
