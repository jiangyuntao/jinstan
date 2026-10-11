<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Content;

use Jinstan\Core\Application\Content\ContentUploadInput;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class ContentUploadRequestTest extends TestCase
{
    public function testItCollectsNestedFilesAndIgnoresEmptyUploadSlots(): void
    {
        $first = new UploadedFile('first', 5, UPLOAD_ERR_OK, 'first.png');
        $second = new UploadedFile('second', 6, UPLOAD_ERR_OK, 'second.png');
        $empty = new UploadedFile('', 0, UPLOAD_ERR_NO_FILE);

        self::assertSame([$first, $second], (new ContentUploadInput())->files([$first, [$empty, $second]], true, '图片'));
    }

    public function testItRejectsInvalidUploadStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('上传文件格式无效。');

        (new ContentUploadInput())->files(['unexpected'], true, '图片');
    }

    public function testItRejectsUploadError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('上传文件失败。');

        (new ContentUploadInput())->files(new UploadedFile('', 0, UPLOAD_ERR_INI_SIZE), true, '图片');
    }

    public function testItRejectsMultipleFilesForSingleField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('字段“图片”只能上传一个文件。');

        (new ContentUploadInput())->files([
            new UploadedFile('first', 5, UPLOAD_ERR_OK, 'first.png'),
            new UploadedFile('second', 6, UPLOAD_ERR_OK, 'second.png'),
        ], false, '图片');
    }
}
