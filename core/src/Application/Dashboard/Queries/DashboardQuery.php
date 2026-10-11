<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Dashboard\Queries;

use Composer\InstalledVersions;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Application\Database\DatabaseOperations;
use Jinstan\Core\Version;

final class DashboardQuery
{
    public function __construct(
        private readonly ContentReadRepository $contents,
        private readonly DatabaseOperations $database,
        private readonly ContentTypeRegistry $types,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $contentTypes = [];
        $published = 0;
        $drafts = 0;
        foreach ($this->types->all() as $type) {
            $items = $this->contents->list($type->handle);
            $contentTypes[] = [
                'type' => $type->handle,
                'label' => $type->label,
                'value' => count($items),
            ];
            foreach ($items as $item) {
                if ($item->status === 'published') {
                    ++$published;
                } else {
                    ++$drafts;
                }
            }
        }

        return [
            'contentTypes' => $contentTypes,
            'published' => $published,
            'drafts' => $drafts,
            'phpVersion' => PHP_VERSION,
            'coreVersion' => Version::CURRENT,
            'dependencies' => $this->dependencies(),
            'database' => [
                'driver' => $this->database->driver(),
                'tables' => $this->database->tableCount(),
                'migrations' => $this->database->migrationCount(),
                'integrity' => $this->database->integrity()['value'],
            ],
        ];
    }

    /** @return list<array{label: string, value: string|null, url: string}> */
    private function dependencies(): array
    {
        $packages = [
            'latte/latte' => 'https://latte.nette.org/',
            'league/commonmark' => 'https://commonmark.thephpleague.com/',
            'nikic/fast-route' => 'https://github.com/nikic/FastRoute',
            'nyholm/psr7' => 'https://github.com/Nyholm/psr7',
            'nyholm/psr7-server' => 'https://github.com/Nyholm/psr7-server',
            'psr/container' => 'https://www.php-fig.org/psr/psr-11/',
            'psr/http-message' => 'https://www.php-fig.org/psr/psr-7/',
        ];
        $items = [];
        foreach ($packages as $package => $url) {
            $items[] = [
                'label' => $package,
                'value' => InstalledVersions::getPrettyVersion($package),
                'url' => $url,
            ];
        }
        $items[] = ['label' => 'Chart.js', 'value' => '4.5.1', 'url' => 'https://www.chartjs.org/'];

        return $items;
    }
}
