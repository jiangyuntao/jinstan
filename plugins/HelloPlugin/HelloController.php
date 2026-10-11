<?php

declare(strict_types=1);

namespace Jinstan\Plugins\HelloPlugin;

use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

final readonly class HelloController
{
    public function __construct(
        private GreetingService $greetings,
        private ResponseFactory $responses,
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->responses->text($this->greetings->message());
    }

}
