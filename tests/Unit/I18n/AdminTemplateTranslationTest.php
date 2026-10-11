<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\I18n;

use Jinstan\Core\Infrastructure\Filesystem\I18n\PhpTranslationCatalogLoader;
use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\SwitchField;
use Jinstan\Core\I18n\Translator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\TestUrlGenerator;
use Latte\Engine;
use PHPUnit\Framework\TestCase;

final class AdminTemplateTranslationTest extends TestCase
{
    private string $cache;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/jinstan-admin-i18n-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cache)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cache, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->cache);
    }

    public function testEveryAdminTemplateCompiles(): void
    {
        $engine = new Engine();
        $engine->addFunction('t', static fn (string $key, array $parameters = []): string => $key);
        $engine->addFunction('route', static fn (string $name, array $parameters = []): string => '#');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/core/resources/views/admin'));
        foreach ($files as $file) {
            if ($file->getExtension() === 'jst') {
                self::assertNotSame('', $engine->compile($file->getPathname()));
            }
        }
    }

    public function testFormsTranslateStaticTextAndPreserveDynamicNames(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['zh-CN', 'ko'] as $locale) {
            $translator = new Translator(
                $root . '/core/lang/admin',
                static fn (): string => $locale,
                loader: new PhpTranslationCatalogLoader(),
            );
            $views = new AdminViewRenderer($root . '/core/resources/views/admin', $this->cache, TestUrlGenerator::adminViews(), null, $translator);
            // 名称故意与翻译键相同，并包含 HTML，防止误翻译用户数据或绕过转义。
            $name = '标题 <script>alert("x")</script>';
            $html = $views->render('taxonomy-form', $name, [
                'heading' => $name, 'isTerm' => true, 'editing' => true, 'deleting' => true,
                'group' => ['label' => $name], 'values' => ['name' => $name, 'content_count' => 7],
                'error' => null, 'backUrl' => '/admin/taxonomies', 'locale' => 'zh-CN',
                'locales' => ['zh-CN' => '简体中文'], 'sourceReference' => null,
            ]);
            self::assertStringContainsString('<html lang="' . $locale . '">', $html);
            self::assertStringContainsString(htmlspecialchars($translator->translate('taxonomy.delete_title', ['name' => $name]), ENT_NOQUOTES), $html);
            self::assertStringContainsString($translator->translate('taxonomy.delete_term_impact', ['count' => 7]), $html);
            self::assertStringContainsString($translator->translate('taxonomy.confirm_term'), $html);
            self::assertStringNotContainsString('<script>alert("x")</script>', $html);

            $form = (new AdminContentForm(TestUrlGenerator::contentAdmin()))->data(new ContentTypeDefinition('example', $name, [
                'visible' => new FieldDefinition('visible', $name, new SwitchField()),
            ], 'content-detail', '/{slug}', true), new CsrfToken());
            $form['locale'] = 'zh-CN';
            $form['locales'] = ['zh-CN' => '简体中文'];
            $html = $views->render('content-form', $name, [
                'pageHeading' => $name,
                'error' => null,
                'form' => $form,
                'sourceReference' => null,
            ]);
            self::assertStringContainsString($translator->translate('content.parent_help'), $html);
            self::assertStringContainsString('data-on="' . $translator->translate('fields.switch_on') . '"', $html);
            self::assertStringContainsString('data-off="' . $translator->translate('fields.switch_off') . '"', $html);
            preg_match('/const taxonomyMessages = (\{.*?\});/s', $html, $script);
            self::assertNotEmpty($script);
            foreach (['searching', 'search_limit', 'search_default', 'search_empty', 'search_failed'] as $key) {
                self::assertStringContainsString(json_encode($translator->translate('taxonomy.' . $key), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $script[1]);
            }
            $login = $views->render('login', '');
            self::assertStringContainsString('<html lang="' . $locale . '">', $login);
            self::assertStringContainsString($translator->translate('管理员登录'), $login);
        }
    }
}
