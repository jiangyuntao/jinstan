<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\Application\Content\TemplateCatalogReader;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Application\Content\Services\ContentTypeDeletionService;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\Routing\Router;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContentTypeAdminController
{
    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly EditableContentTypes $editable,
        private readonly CsrfToken $csrf,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
        private readonly TaxonomyReadRepository $taxonomy,
        private readonly TemplateCatalogReader $templates,
        private readonly Router $router,
        private readonly ContentTypeDeletionService $deletion,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $items = array_map(function ($type): array {
            return $this->editable->export($type) + [
                'deletable' => $this->deletion->deletable($type->handle),
            ];
        }, $this->types->all());
        return $this->page('content-types', $this->views->translate('内容类型'), ['items' => $items, 'definition' => null]);
    }

    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        try {
            $this->deletion->delete((string) ($params['handle'] ?? ''));
        } catch (\InvalidArgumentException) {
            return $this->responses->text('内容类型不能删除。', 422);
        }

        return $this->responses->redirect($this->indexUrl());
    }

    public function edit(ServerRequestInterface $request, array $params): ResponseInterface
    {
        if ($params['handle'] === 'new') {
            $definition = ['handle' => 'example', 'label' => '示例内容', 'defaultTemplate' => 'content-detail', 'urlPattern' => '/example/{slug}', 'hierarchical' => false, 'fields' => []];
        } else {
            try {
                $definition = $this->editable->export($this->types->get((string) $params['handle']));
            } catch (\Throwable) {
                return $this->responses->text('Not Found', 404);
            }
        }
        return $this->editPage($definition);
    }

    public function save(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        try {
            $input = $this->restoreProtectedFields($input);
            $definition = $this->editable->definition($input);
            DefaultContentTypes::assertProtectedFields($definition);
            $available = array_column($this->taxonomy->taxonomies(), 'label', 'handle');
            foreach ($definition->taxonomies as $handle => $settings) {
                if (!isset($available[$handle])) {
                    throw new \InvalidArgumentException('分类法词汇表不存在。');
                }
            }
            $previous = $this->types->all()[$definition->handle] ?? null;
            $this->templates->validate($definition->defaultTemplate, 'detail');
            if ($definition->listEnabled) {
                $this->templates->validate($definition->listTemplate, 'list');
            }
            foreach ([$definition->urlPattern, ...($definition->listEnabled ? [$definition->listPath] : [])] as $path) {
                $oldPaths = $previous === null ? [] : [$previous->urlPattern, ...($previous->listEnabled ? [$previous->listPath] : [])];
                if (!in_array($path, $oldPaths, true) && $this->router->hasRoute('GET', $path)) {
                    throw new \InvalidArgumentException('前台路径已被占用：' . $path);
                }
            }
            $updated = clone $this->types;
            $updated->register($definition);
            \Jinstan\Core\Content\ContentUrls::validateTypes($updated);
            $all = [];
            foreach ($this->types->all() as $type) {
                $all[$type->handle] = $this->editable->export($type);
            }
            $all[$definition->handle] = $this->editable->export($definition);
            $this->editable->save(array_values($all));
        } catch (\Throwable $exception) {
            return $this->editPage($input, SafeErrorMessage::from($exception, '保存内容类型失败，请稍后重试。'), 422);
        }
        return $this->responses->redirect($this->indexUrl());
    }

    private function page(string $view, string $title, array $data, int $status = 200): ResponseInterface
    {
        return $this->responses->html($this->views->render($view, $title, [
            'csrfToken' => $this->csrf->value(),
        ] + $data), $status);
    }

    private function indexUrl(): string
    {
        return $this->urls->route('admin.content-types.index');
    }

    /** @param array<string, mixed> $definition */
    private function editPage(array $definition, ?string $error = null, int $status = 200): ResponseInterface
    {
        $definition += [
            'handle' => '',
            'label' => '',
            'defaultTemplate' => 'content-detail',
            'urlPattern' => '/{slug}',
            'hierarchical' => false,
            'fields' => [],
        ];
        $fields = $definition['fields'] ?? [];
        if (!is_array($fields)) {
            $fields = [];
        }
        $protectedNames = DefaultContentTypes::protectedFieldNames()[(string) $definition['handle']] ?? [];
        $fields = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => !is_array($field)
                || !in_array((string) ($field['name'] ?? ''), $protectedNames, true),
        ));

        return $this->page('content-types', $this->views->translate('编辑内容类型'), [
            'items' => [],
            'definition' => $definition,
            'fieldsJson' => json_encode(array_values($fields), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'error' => $error,
            'detailTemplates' => $this->templates->files('detail'),
            'listTemplates' => $this->templates->files('list'),
            'taxonomyGroups' => $this->taxonomy->taxonomies(),
            'hasProtectedFields' => $protectedNames !== [],
        ], $status);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function restoreProtectedFields(array $input): array
    {
        $handle = (string) ($input['handle'] ?? '');
        $protectedNames = DefaultContentTypes::protectedFieldNames()[$handle] ?? [];
        if ($protectedNames === []) {
            return $input;
        }

        $submittedFields = $input['fields'] ?? [];
        if (!is_array($submittedFields)) {
            return $input;
        }
        foreach ($submittedFields as $field) {
            if (is_array($field) && in_array((string) ($field['name'] ?? ''), $protectedNames, true)) {
                throw new \InvalidArgumentException('内置字段不能通过内容类型表单修改。');
            }
        }

        $current = $this->editable->export($this->types->get($handle));
        $protectedFields = array_values(array_filter(
            $current['fields'],
            static fn (array $field): bool => in_array((string) $field['name'], $protectedNames, true),
        ));
        $input['fields'] = [...$protectedFields, ...$submittedFields];

        return $input;
    }
}
