<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Navigation\Services;

use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Navigation\NavigationWriteRepository;
use Jinstan\Core\Settings\SettingsReadRepository;

final class NavigationWriter
{
    public function __construct(
        private readonly NavigationWriteRepository $navigation,
        private readonly SettingsReadRepository $settings,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function createMenu(array $input): void
    {
        $handle = $this->text($input, 'handle');
        $name = $this->text($input, 'name');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $handle) !== 1 || $name === '') {
            throw new \InvalidArgumentException('请输入菜单名称和由小写字母、数字、连字符组成的标识。');
        }

        $this->navigation->createMenu($handle, $name);
    }

    /** @param array<string, mixed> $input */
    public function createItem(array $input): void
    {
        $this->saveItem(null, $input);
    }

    /** @param array<string, mixed> $input */
    public function updateItem(int $id, array $input): void
    {
        $this->saveItem($id, $input);
    }

    public function deleteItem(int $id): void
    {
        $this->navigation->deleteItem($id);
    }

    /** @param array<string, mixed> $input */
    public function reorderItems(array $input): void
    {
        $handle = $this->text($input, 'menu');
        $order = $input['order'] ?? [];
        if ($handle === '' || !is_array($order) || count($order) > 1000) {
            throw new \InvalidArgumentException('菜单排序数据无效。');
        }

        $itemIds = [];
        foreach ($order as $itemId) {
            if (filter_var($itemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new \InvalidArgumentException('菜单排序项目无效。');
            }
            $itemIds[] = (int) $itemId;
        }
        $this->navigation->reorder($handle, $itemIds);
    }

    /** @param array<string, mixed> $input */
    private function saveItem(?int $id, array $input): void
    {
        $handle = $this->text($input, 'menu');
        $label = $this->text($input, 'label');
        $url = $this->text($input, 'url');
        if ($label === '' || $url === '') {
            throw new \InvalidArgumentException('菜单项名称和链接不能为空。');
        }

        $arguments = [
            ContentLocales::requested($input, $this->settings),
            $label,
            $url,
            $this->text($input, 'target'),
            $this->position($input),
            $this->parentId($input),
        ];
        if ($id === null) {
            $this->navigation->createItem($handle, ...$arguments);

            return;
        }

        $this->navigation->updateItem($id, ...$arguments);
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    /** @param array<string, mixed> $input */
    private function position(array $input): int
    {
        $position = filter_var($input['position'] ?? 0, FILTER_VALIDATE_INT);

        return $position === false ? 0 : max(0, $position);
    }

    /** @param array<string, mixed> $input */
    private function parentId(array $input): ?int
    {
        $value = $input['parent_id'] ?? null;

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            ? null
            : (int) $value;
    }
}
