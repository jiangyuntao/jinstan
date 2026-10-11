<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Settings\Services\SaveSystemSettings;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;

final class SaveSystemSettingsTest extends TestCase
{
    public function testValidatedSettingsAreMappedAndSavedTogether(): void
    {
        $settings = $this->settings();
        $command = new SaveSystemSettings($settings);

        $command->execute($this->validated(), new Uri('HTTPS://example.test:8443/admin/settings/system'));

        self::assertSame('新站点', $settings->get('site.name'));
        self::assertSame('https://example.test:8443', $settings->get('site.url'));
        self::assertSame('25', $settings->get('content.per_page'));
        self::assertSame("/admin\n/install", $settings->get('routing.protected_slugs'));
        self::assertSame('42', $settings->get('site.homepage_id'));
        self::assertSame('index,follow', $settings->get('seo.robots'));
        self::assertSame('https://example.test/social', $settings->get('site.sns.example'));
        self::assertSame(['company.name' => '示例公司'], $settings->custom());
    }

    public function testInvalidRequestOriginKeepsSavedSiteUrlWithoutTrailingSlash(): void
    {
        $settings = $this->settings();
        $settings->set('site.url', 'https://saved.example/');

        (new SaveSystemSettings($settings))->execute($this->validated(), new Uri('/admin/settings/system'));

        self::assertSame('https://saved.example', $settings->get('site.url'));
    }

    private function settings(): SettingsRepository
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');

        return new SettingsRepository($connection);
    }

    /** @return array<string, mixed> */
    private function validated(): array
    {
        return [
            'site_name' => '新站点',
            'site_tagline' => '标语',
            'site_description' => '描述',
            'contact_email' => 'hello@example.test',
            'site_footer' => '页脚',
            'frontend_locale' => 'zh-CN',
            'admin_locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'theme' => 'default',
            'predefined_pages' => [
                'site.homepage_id' => '42',
                'site.not_found_page_id' => '0',
            ],
            'date_format' => 'Y-m-d',
            'content_per_page' => 25,
            'content_sort' => 'newest',
            'default_content_status' => 'draft',
            'seo_values' => ['seo.robots' => 'index,follow'],
            'sns_values' => ['site.sns.example' => 'https://example.test/social'],
            'protected_slugs' => ['/admin', '/install'],
            'custom_values' => ['company.name' => '示例公司'],
            'active_tab' => 'site',
        ];
    }
}
