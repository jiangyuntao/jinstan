<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Content;

use Psr\Http\Message\UploadedFileInterface;

final class ContentUploadInput
{
    /** @return list<UploadedFileInterface> */
    public function files(mixed $value, bool $multiple, string $label): array
    {
        $files = $this->collect($value);
        if (!$multiple && count($files) > 1) {
            throw new \InvalidArgumentException(sprintf('字段“%s”只能上传一个文件。', $label));
        }

        return $files;
    }

    /** @return list<UploadedFileInterface> */
    private function collect(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if ($value instanceof UploadedFileInterface) {
            if ($value->getError() === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            if ($value->getError() !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('上传文件失败。');
            }

            return [$value];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('上传文件格式无效。');
        }

        $files = [];
        foreach ($value as $file) {
            array_push($files, ...$this->collect($file));
        }

        return $files;
    }
}
