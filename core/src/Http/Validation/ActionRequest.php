<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Validation;

use Psr\Http\Message\ServerRequestInterface;

abstract class ActionRequest implements Validator
{
    final public function result(ServerRequestInterface $request): ValidationResult
    {
        return $this->validate($this->input($request));
    }

    /** @return array<string, mixed> */
    final public function validated(ServerRequestInterface $request): array
    {
        return $this->result($request)->validated();
    }

    protected function string(array $input, string $key, bool $trim = true): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }

        $value = (string) $value;

        return $trim ? trim($value) : $value;
    }

    /** @return array<string, mixed> */
    protected function input(ServerRequestInterface $request): array
    {
        $input = $request->getParsedBody();

        return is_array($input) ? $input : [];
    }

    /** @return list<string>|null */
    protected function stringList(array $input, string $key): ?array
    {
        $value = $input[$key] ?? [];
        if (!is_array($value)) {
            return null;
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                return null;
            }
            $strings[] = $item;
        }

        return array_values(array_unique($strings));
    }
}
