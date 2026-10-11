<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Update\Services;

use Jinstan\Core\Application\Update\CoreMigrationGateway;
use Jinstan\Core\Application\Update\CoreUpdateStorage;
use Jinstan\Core\Application\Update\MaintenanceModeGateway;

class CoreUpdater
{
    public function __construct(
        private readonly MaintenanceModeGateway $maintenance,
        private readonly CoreMigrationGateway $migrations,
        private readonly CoreUpdateStorage $files,
    ) {
    }

    /**
     * `$stagedCore` 必须指向发布包中的 `core/`，其同级目录包含 `public/assets/core/`。
     */
    public function apply(string $stagedCore): void
    {
        $this->files->assertValidCore($stagedCore);

        $release = $this->files->release($stagedCore);
        $stagedAssets = $this->files->stagedAssets($stagedCore, $release);
        $this->files->verifyAssets($stagedAssets, $stagedCore);

        $core = $this->files->corePath();
        $backup = $this->files->uniqueUpdatePath('core-backup');
        $publishedAssets = false;
        $coreBackedUp = false;

        // Core 和对应前端资产作为同一个发布单元切换，避免请求命中新旧混合版本。
        $this->maintenance->enable();
        try {
            $publishedAssets = $this->files->publishAssets($stagedAssets, $stagedCore);

            $this->files->move($core, $backup, '无法备份当前 Core。');
            $coreBackedUp = true;

            $this->files->move($stagedCore, $core, '无法替换 Core。');

            $this->migrations->migrate($core . '/src/Database/Migrations');
            $installedRelease = $this->files->release($core);
            $installedAssets = $this->files->assetReleasePath($installedRelease);
            $this->files->verifyAssets($installedAssets, $core);
            if (!$this->files->healthy($core)) {
                throw new \RuntimeException('Health Check 失败。');
            }

            $this->maintenance->disable();
        } catch (\Throwable $exception) {
            try {
                if ($coreBackedUp && $this->files->directoryExists($backup)) {
                    if ($this->files->directoryExists($core)) {
                        $this->files->move(
                            $core,
                            $this->files->uniqueUpdatePath('failed-core'),
                            '无法隔离失败的 Core。',
                        );
                    }
                    $this->files->move($backup, $core, '无法恢复原 Core。');
                }
                if ($publishedAssets) {
                    $this->files->removeDirectory($this->files->assetReleasePath($release));
                }
            } finally {
                // 即使回滚文件操作自身失败，也必须解除维护模式，避免站点永久不可用。
                $this->maintenance->disable();
            }

            throw $exception;
        }
    }
}
