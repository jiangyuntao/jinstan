<?php

declare(strict_types=1);

use Jinstan\Core\Bootstrap\Bootstrap;
use Jinstan\Core\Http\Application as HttpApplication;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__) . '/core/vendor/autoload.php';

$factory = new Psr17Factory();
$request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();
$application = Bootstrap::application(dirname(__DIR__));
$response = $application->container()->get(HttpApplication::class)->handle($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}

echo $response->getBody();
