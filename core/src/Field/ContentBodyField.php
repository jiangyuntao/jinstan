<?php

declare(strict_types=1);

namespace Jinstan\Core\Field;

final class ContentBodyField implements FieldType
{
    public function __construct(private readonly bool $allowHtmlDocument = false)
    {
    }

    public function allowsHtmlDocument(): bool
    {
        return $this->allowHtmlDocument;
    }

    public function normalize(mixed $value): array
    {
        if (!is_array($value)) {
            return ['format' => 'markdown', 'value' => is_scalar($value) ? trim((string) $value) : ''];
        }

        $format = (string) ($value['format'] ?? 'markdown');
        $formats = $this->allowHtmlDocument ? ['markdown', 'html_document'] : ['markdown'];
        if (!in_array($format, $formats, true)) {
            throw new \InvalidArgumentException('内容格式无效。');
        }

        return ['format' => $format, 'value' => is_scalar($value['value'] ?? null) ? trim((string) $value['value']) : ''];
    }

    public function valid(mixed $value): bool
    {
        return is_scalar($value) || (is_array($value) && is_scalar($value['value'] ?? null));
    }

    public function adminView(): string
    {
        return 'fields/content-body.jst';
    }

    public function adminData(): array
    {
        return ['allowHtmlDocument' => $this->allowHtmlDocument, 'rows' => 16];
    }

    public function adminValue(mixed $value): mixed
    {
        return $this->normalize($value);
    }
}
