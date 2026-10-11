<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Field;

use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Field\BooleanField;
use Jinstan\Core\Field\CheckboxGroupField;
use Jinstan\Core\Field\ColorField;
use Jinstan\Core\Field\DateField;
use Jinstan\Core\Field\DateTimeField;
use Jinstan\Core\Field\EmailField;
use Jinstan\Core\Field\FieldDefinition;
use Jinstan\Core\Field\FileField;
use Jinstan\Core\Field\FilesField;
use Jinstan\Core\Field\ImageField;
use Jinstan\Core\Field\ImagesField;
use Jinstan\Core\Field\JsonField;
use Jinstan\Core\Field\MonthField;
use Jinstan\Core\Field\NumberField;
use Jinstan\Core\Field\PhoneField;
use Jinstan\Core\Field\RadioField;
use Jinstan\Core\Field\RangeField;
use Jinstan\Core\Field\SelectField;
use Jinstan\Core\Field\SearchableSelectField;
use Jinstan\Core\Field\TextareaField;
use Jinstan\Core\Field\TextField;
use Jinstan\Core\Field\TagsField;
use Jinstan\Core\Field\TimeField;
use Jinstan\Core\Field\UrlField;
use Jinstan\Core\Field\WeekField;
use Jinstan\Core\Field\SwitchField;
use Jinstan\Core\Field\SkuField;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class AdminFieldRenderingTest extends TestCase
{
    public function testAllCoreFieldsRenderThroughLatteTemplates(): void
    {
        $type = new ContentTypeDefinition('example', '示例', [
            'text' => new FieldDefinition('text', '文本', new TextField()),
            'sku' => new FieldDefinition('sku', 'SKU', new SkuField(), required: true),
            'body' => new FieldDefinition('body', '正文', new TextareaField()),
            'price' => new FieldDefinition('price', '价格', new NumberField()),
            'enabled' => new FieldDefinition('enabled', '启用', new BooleanField()),
            'email' => new FieldDefinition('email', '邮箱', new EmailField()),
            'url' => new FieldDefinition('url', '网址', new UrlField()),
            'date' => new FieldDefinition('date', '日期', new DateField()),
            'datetime' => new FieldDefinition('datetime', '时间', new DateTimeField()),
            'select' => new FieldDefinition('select', '下拉', new SelectField(['one' => '选项一'])),
            'searchable' => new FieldDefinition('searchable', '可搜索下拉', new SearchableSelectField(['en' => 'English', 'zh-CN' => '简体中文'])),
            'radio' => new FieldDefinition('radio', '单选', new RadioField(['one' => '选项一'])),
            'checks' => new FieldDefinition('checks', '多选', new CheckboxGroupField(['one' => '选项一'])),
            'phone' => new FieldDefinition('phone', '电话', new PhoneField()),
            'color' => new FieldDefinition('color', '颜色', new ColorField()),
            'time' => new FieldDefinition('time', '时间', new TimeField()),
            'month' => new FieldDefinition('month', '月份', new MonthField()),
            'week' => new FieldDefinition('week', '周', new WeekField()),
            'range' => new FieldDefinition('range', '评分', new RangeField(1, 5), default: 3),
            'tags' => new FieldDefinition('tags', '标签', new TagsField()),
            'json' => new FieldDefinition('json', '配置', new JsonField()),
            'visible' => new FieldDefinition('visible', '前台显示', new SwitchField()),
            'attachment' => new FieldDefinition('attachment', '附件', new FileField()),
            'attachments' => new FieldDefinition('attachments', '多个附件', new FilesField()),
            'image' => new FieldDefinition('image', '图片', new ImageField()),
            'images' => new FieldDefinition('images', '多张图片', new ImagesField()),
        ], 'content-detail', '/example/{slug}', true);
        $imagePath = 'ab/cd/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';
        $form = (new AdminContentForm(TestUrlGenerator::contentAdmin()))->data($type, new CsrfToken(), ['parent_id' => 12, 'data' => ['checks' => ['one'], 'image' => $imagePath]], null, [12 => '上级内容']);
        $cache = sys_get_temp_dir() . '/jinstan-admin-fields-' . bin2hex(random_bytes(4));
        $form['locale'] = 'zh-CN';
        $form['locales'] = ['zh-CN' => '简体中文'];
        $html = (new AdminViewRenderer(dirname(__DIR__, 3) . '/core/resources/views/admin', $cache, TestUrlGenerator::adminViews()))->render('content-form', '示例', [
            'pageHeading' => '示例',
            'error' => null,
            'form' => $form,
            'sourceReference' => null,
        ]);

        foreach (['text', 'number', 'checkbox', 'email', 'url', 'date', 'datetime-local', 'radio', 'tel', 'color', 'time', 'month', 'week', 'range'] as $control) {
            self::assertStringContainsString('type="' . $control . '"', $html);
        }
        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString('搜索选项', $html);
        self::assertStringContainsString('name="data[tags]"', $html);
        self::assertStringContainsString("'data[sku][' + index + '][title]'", $html);
        self::assertStringContainsString('添加 SKU', $html);
        self::assertStringContainsString('autocapitalize="characters"', $html);
        self::assertStringContainsString('maxlength="100"', $html);
        self::assertStringContainsString('name="data[json]"', $html);
        self::assertStringContainsString('name="data[visible]"', $html);
        self::assertStringContainsString('peer-checked:bg-blue-600', $html);
        self::assertSame(4, substr_count($html, 'type="file"'));
        self::assertStringContainsString('\/admin\/media\/' . str_replace('/', '\/', $imagePath), $html);
        self::assertStringContainsString('向左旋转', $html);
        self::assertStringContainsString('name="remove_media[image]"', $html);
        self::assertStringContainsString('name="manage_media[images]"', $html);
        self::assertStringContainsString('添加图片', $html);
        self::assertStringContainsString('media_order[images]', $html);
        self::assertStringContainsString('裁剪图片', $html);
        self::assertStringContainsString('拖动四边或控制点可继续调整大小', $html);
        self::assertStringContainsString('draggable="true"', $html);
        self::assertStringContainsString('dragOver(index)', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('name="data[checks][]"', $html);
        self::assertStringContainsString('name="parent_id"', $html);
        self::assertStringContainsString('上级内容', $html);
        self::assertStringNotContainsString('<input class="mt-1 block w-full rounded border p-2"', $html);
    }

    public function testCollectionAndJsonFieldsNormalizeSafely(): void
    {
        self::assertSame(['news', 'product'], (new TagsField())->normalize('news, product, news'));
        self::assertSame(['enabled' => true], (new JsonField())->normalize('{"enabled": true}'));
        self::assertFalse((new JsonField())->valid('{invalid}'));
        self::assertFalse((new PhoneField())->valid('<script>'));
        self::assertTrue((new SwitchField())->normalize('1'));
        self::assertFalse((new SwitchField())->normalize('0'));
        $file = new FileField();
        $files = new FilesField();
        self::assertSame(['ab/cd/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png'], $files->normalize(['ab/cd/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png']));
        self::assertFalse($file->valid(3));
        self::assertFalse($files->valid(['7']));
        self::assertFalse((new ImageField())->allowsMultiple());
        self::assertTrue((new ImagesField())->imagesOnly());
    }

    public function testSkuFieldNormalizesAndValidatesVariantRows(): void
    {
        $field = new SkuField();

        $rows = [['title' => ' 标准版 ', 'code' => ' jst-product_001 ', 'stock' => '12', 'price' => '19.95']];
        self::assertSame([['title' => '标准版', 'code' => 'JST-PRODUCT_001', 'stock' => 12, 'price' => 19.95]], $field->normalize($rows));
        self::assertTrue($field->valid($rows));
        self::assertFalse($field->valid([['title' => '标准版', 'code' => 'BAD CODE', 'stock' => 1, 'price' => 10]]));
        self::assertFalse($field->valid([['title' => '标准版', 'code' => 'ONE', 'stock' => -1, 'price' => 10]]));
        self::assertFalse($field->valid([
            ['title' => '一', 'code' => 'SAME', 'stock' => 1, 'price' => 10],
            ['title' => '二', 'code' => 'same', 'stock' => 1, 'price' => 20],
        ]));
    }

}
