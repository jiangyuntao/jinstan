<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PluginPublicApiBoundaryTest extends TestCase
{
    private const FORBIDDEN_CORE_TYPES = [
        'Jinstan\\Core\\Support\\Container;',
        'Jinstan\\Core\\Database\\Connection;',
        'Jinstan\\Core\\Content\\ContentRepository;',
        'Jinstan\\Core\\Settings\\SettingsRepository;',
        'Jinstan\\Core\\Component\\ComponentRegistry;',
        'Jinstan\\Core\\Content\\ContentTypeRegistry;',
        'Jinstan\\Core\\Field\\FieldTypeRegistry;',
        'Jinstan\\Core\\Event\\EventDispatcher;',
        'Jinstan\\Core\\I18n\\TranslationRegistry;',
        'Jinstan\\Core\\View\\ViewNamespaceRegistry;',
        'Jinstan\\Core\\Routing\\Router;',
        'Jinstan\\Core\\Security\\CsrfToken;',
    ];

    public function testOfficialPluginsDoNotDependOnCoreInfrastructureImplementations(): void
    {
        $root = dirname(__DIR__, 2) . '/plugins';
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            foreach (self::FORBIDDEN_CORE_TYPES as $type) {
                if (str_contains($source, $type)) {
                    $violations[] = str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()) . ': ' . $type;
                }
            }
        }

        self::assertSame([], $violations, 'Official Plugins must only use documented Core Public API contracts.');
    }
}
