<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Foundation;

use Jinstan\Core\Bootstrap\Bootstrap;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Http\Application as HttpApplication;
use Jinstan\Core\Providers\ServiceProvider;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testBootstrapExposesFoundationApplicationAndHttpHandler(): void
    {
        $application = Bootstrap::application(dirname(__DIR__, 3));

        self::assertTrue($application->isBooted());
        self::assertInstanceOf(HttpApplication::class, $application->container()->get(HttpApplication::class));
    }

    public function testProvidersRegisterBeforeTheyBoot(): void
    {
        $events = new ProviderEvents();
        $application = new Application('/tmp/jinstan-foundation', 'testing');

        $application->register(new RecordingProvider($events, 'first'));
        $application->register(new SecondRecordingProvider($events));

        self::assertSame(['register:first', 'register:second'], $events->all());
        self::assertFalse($application->isBooted());

        $application->boot();

        self::assertSame(
            ['register:first', 'register:second', 'boot:first', 'boot:second'],
            $events->all(),
        );
        self::assertTrue($application->isBooted());
    }

    public function testProviderAndBootRegistrationAreIdempotent(): void
    {
        $events = new ProviderEvents();
        $application = new Application('/tmp/jinstan-foundation', 'testing');

        $application->register(new RecordingProvider($events, 'foundation'));
        $application->register(new RecordingProvider($events, 'duplicate'));
        $application->boot();
        $application->boot();

        self::assertSame(['register:foundation', 'boot:foundation'], $events->all());
    }

    public function testProviderCannotBeRegisteredAfterBoot(): void
    {
        $application = new Application('/tmp/jinstan-foundation', 'testing');
        $application->boot();

        $this->expectException(LogicException::class);
        $application->register(new RecordingProvider(new ProviderEvents(), 'late'));
    }

    public function testApplicationExposesProjectPathEnvironmentAndItself(): void
    {
        $application = new Application('/var/www/jinstan', 'testing');

        self::assertSame('/var/www/jinstan/config/app.php', $application->projectPath('/config/app.php'));
        self::assertSame('testing', $application->environment());
        self::assertTrue($application->isEnvironment('local', 'testing'));
        self::assertSame($application, $application->container()->get(Application::class));
    }
}

final class ProviderEvents
{
    /** @var list<string> */
    private array $events = [];

    public function add(string $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->events;
    }
}

class RecordingProvider implements ServiceProvider
{
    public function __construct(
        private readonly ProviderEvents $events,
        private readonly string $name,
    ) {
    }

    public function register(Application $application): void
    {
        $this->events->add('register:' . $this->name);
    }

    public function boot(Application $application): void
    {
        $this->events->add('boot:' . $this->name);
    }
}

final class SecondRecordingProvider extends RecordingProvider
{
    public function __construct(ProviderEvents $events)
    {
        parent::__construct($events, 'second');
    }
}
