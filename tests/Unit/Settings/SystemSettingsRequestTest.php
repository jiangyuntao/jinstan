<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Settings;

use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Http\Requests\Admin\SystemSettingsRequest;
use PHPUnit\Framework\TestCase;

final class SystemSettingsRequestTest extends TestCase
{
    public function testItNormalizesFixedSystemSettings(): void
    {
        $request = $this->request($this->settings());
        $result = $request->validate([
            'site_name' => ' Example ',
            'site_tagline' => ' Tagline ',
            'contact_email' => ' admin@example.test ',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'content_per_page' => '25',
            'content_sort' => 'title_asc',
            'default_content_status' => 'published',
            'theme' => 'default',
        ]);

        self::assertTrue($result->isValid());
        self::assertSame('Example', $result->validated()['site_name']);
        self::assertSame('Tagline', $result->validated()['site_tagline']);
        self::assertSame(25, $result->validated()['content_per_page']);
        self::assertSame('site', $result->validated()['active_tab']);
    }

    public function testItReturnsFieldErrorsAndUsesStoredContentDefaults(): void
    {
        $settings = $this->settings();
        $settings->set('content.per_page', '30');
        $settings->set('content.sort', 'oldest');
        $settings->set('content.default_status', 'published');
        $result = $this->request($settings)->validate([
            'site_name' => '',
            'contact_email' => 'invalid',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Missing/Timezone',
        ]);

        self::assertSame(
            ['site_name', 'contact_email', 'timezone'],
            array_keys($result->errors()),
        );
        self::assertSame(30, $result->normalized()['content_per_page']);
        self::assertSame('oldest', $result->normalized()['content_sort']);
        self::assertSame('published', $result->normalized()['default_content_status']);
    }

    public function testItRejectsUnknownDateFormatAndNormalizesUnknownTab(): void
    {
        $result = $this->request($this->settings())->validate([
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'invalid',
            'active_tab' => '../custom',
        ]);

        self::assertSame(['date_format'], array_keys($result->errors()));
        self::assertSame('site', $result->normalized()['active_tab']);
    }

    public function testItOnlyAcceptsEnabledFrontendAndSupportedAdminLocales(): void
    {
        $settings = $this->settings();
        $result = $this->request($settings)->validate([
            'site_name' => 'Example',
            'frontend_locale' => 'ja',
            'admin_locale' => '../ko',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
        ]);

        self::assertSame(
            ['frontend_locale', 'admin_locale'],
            array_keys($result->errors()),
        );
    }

    public function testItListsThemesAndRejectsUnknownTheme(): void
    {
        $request = $this->request($this->settings());
        self::assertArrayHasKey('default', $request->themes());

        $result = $request->validate([
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => '../missing',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
        ]);

        self::assertSame(['theme'], array_keys($result->errors()));
        self::assertSame('前台主题无效。', $result->firstError());
    }

    public function testItRejectsMalformedOrMissingPredefinedPages(): void
    {
        $result = $this->request($this->settings())->validate([
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'homepage_id' => ['1'],
            'not_found_page_id' => '999',
        ]);

        self::assertSame(
            ['homepage_id', 'not_found_page_id'],
            array_keys($result->errors()),
        );
        self::assertSame([
            'site.homepage_id' => '0',
            'site.not_found_page_id' => '999',
        ], $result->normalized()['predefined_pages']);
    }

    public function testItNormalizesAndValidatesProtectedSlugs(): void
    {
        $valid = $this->request($this->settings())->validate([
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'protected_slugs' => " /Admin/ \n/custom.path\n/custom.path/ ",
        ]);
        self::assertSame(['/admin', '/custom.path'], $valid->validated()['protected_slugs']);

        $invalid = $this->request($this->settings())->validate([
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'protected_slugs' => ['/admin'],
        ]);
        self::assertSame(['protected_slugs'], array_keys($invalid->errors()));
    }

    public function testItValidatesSeoTemplatesAndPreservesSeparatorWhitespace(): void
    {
        $input = [
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'title_separator' => ' · ',
            'title_homepage' => '{site_name}',
            'title_content' => '{title}{separator}{site_name}',
            'title_listing' => '{title}{page_suffix}{separator}{site_name}',
            'title_not_found' => '{title}{separator}{site_name}',
            'robots' => 'index,follow',
        ];
        $valid = $this->request($this->settings())->validate($input);
        self::assertSame(' · ', $valid->validated()['seo_values']['seo.title_separator']);

        $input['title_content'] = '{unsupported}';
        $invalid = $this->request($this->settings())->validate($input);
        self::assertSame(['seo'], array_keys($invalid->errors()));
        self::assertStringContainsString('{unsupported}', $invalid->firstError() ?? '');
    }

    public function testItValidatesSnsUrlsAndPreservesUnsubmittedSettings(): void
    {
        $settings = $this->settings();
        $settings->set('site.sns.facebook', 'https://example.test/old');
        $input = [
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
        ];
        $request = $this->request($settings);
        self::assertSame(
            'https://example.test/old',
            $request->validate($input)->validated()['sns_values']['site.sns.facebook'],
        );

        $input['sns'] = ['facebook' => ' https://example.test/new '];
        self::assertSame(
            'https://example.test/new',
            $request->validate($input)->validated()['sns_values']['site.sns.facebook'],
        );

        $input['sns'] = ['facebook' => ['https://example.test']];
        self::assertSame(['sns'], array_keys($request->validate($input)->errors()));

        $input['sns'] = ['facebook' => 'javascript:alert(1)'];
        self::assertSame(['sns'], array_keys($request->validate($input)->errors()));

        $input['sns'] = 'invalid';
        self::assertSame(['sns'], array_keys($request->validate($input)->errors()));
    }

    public function testItNormalizesCustomSettingsAndRejectsInvalidEntries(): void
    {
        $input = [
            'site_name' => 'Example',
            'frontend_locale' => 'en',
            'admin_locale' => 'zh-CN',
            'theme' => 'default',
            'timezone' => 'Asia/Shanghai',
            'date_format' => 'Y-m-d',
            'custom_keys' => [' support_phone ', '', 'company.name'],
            'custom_values' => ['400-000-0000', 'ignored', 'Example'],
        ];
        $request = $this->request($this->settings());
        self::assertSame([
            'company.name' => 'Example',
            'support_phone' => '400-000-0000',
        ], $request->validate($input)->validated()['custom_values']);

        $input['custom_keys'] = ['company.name', ' company.name '];
        self::assertSame(['custom_keys'], array_keys($request->validate($input)->errors()));

        $input['custom_keys'] = [['nested']];
        self::assertSame(['custom_keys'], array_keys($request->validate($input)->errors()));

        $input['custom_keys'] = ['company.name'];
        $input['custom_values'] = [['nested']];
        self::assertSame(['custom_keys'], array_keys($request->validate($input)->errors()));

        $input['custom_keys'] = array_fill(0, 101, 'key');
        self::assertSame(['custom_keys'], array_keys($request->validate($input)->errors()));
    }

    private function settings(): SettingsRepository
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate(
            $connection,
            dirname(__DIR__, 3) . '/core/src/Database/Migrations',
        );
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en zh-CN');

        return $settings;
    }

    private function request(SettingsRepository $settings): SystemSettingsRequest
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate(
            $connection,
            dirname(__DIR__, 3) . '/core/src/Database/Migrations',
        );

        return new SystemSettingsRequest(
            $settings,
            new \Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager(dirname(__DIR__, 3) . '/themes'),
            new \Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository($connection),
        );
    }
}
