<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Settings;

use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Settings\SettingsRepositoryContract;

final class SettingsRepository implements SettingsRepositoryContract
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->connection->fetchOne('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);

        return $value === false ? $default : $value;
    }

    public function set(string $key, string $value): void
    {
        if ($this->get($key) === null) {
            $this->connection->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);

            return;
        }

        $this->connection->update('settings', ['setting_value' => $value], ['setting_key' => $key]);
    }

    public function delete(string $key): void
    {
        $this->connection->delete('settings', ['setting_key' => $key]);
    }

    /** @return array<string, string> */
    public function custom(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'custom.%' ORDER BY setting_key",
        );
        $values = [];
        foreach ($rows as $row) {
            $values[substr((string) $row['setting_key'], 7)] = (string) $row['setting_value'];
        }

        return $values;
    }

    /** @param array<string, string> $values */
    public function replaceCustom(array $values): void
    {
        foreach ($this->custom() as $key => $_value) {
            if (!array_key_exists($key, $values)) {
                $this->connection->delete('settings', ['setting_key' => 'custom.' . $key]);
            }
        }
        foreach ($values as $key => $value) {
            $this->set('custom.' . $key, $value);
        }
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $customValues
     */
    public function saveSystem(array $values, array $customValues): void
    {
        $this->connection->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
            $this->replaceCustom($customValues);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
