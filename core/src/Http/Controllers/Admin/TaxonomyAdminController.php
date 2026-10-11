<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyFormQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyIndexQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTermListQuery;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\Application\Taxonomy\Services\ReorderTaxonomyTerms;
use Jinstan\Core\Application\Taxonomy\Services\SubmitTaxonomyForm;
use Jinstan\Core\View\Presenters\Admin\TaxonomyFormPresenter;
use Jinstan\Core\View\Presenters\Admin\TaxonomyListPresenter;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TaxonomyAdminController
{
    public function __construct(
        private readonly CsrfToken $csrf,
        private readonly SettingsReadRepository $settings,
        private readonly TaxonomyTermListQuery $termListQuery,
        private readonly TaxonomyTranslationResolver $translations,
        private readonly ReorderTaxonomyTerms $reorderTaxonomyTerms,
        private readonly SubmitTaxonomyForm $submitTaxonomyForm,
        private readonly TaxonomyFormQuery $formQuery,
        private readonly TaxonomyFormPresenter $formPresenter,
        private readonly TaxonomyIndexQuery $indexQuery,
        private readonly TaxonomyListPresenter $listPresenter,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $query = $request->getQueryParams();
        $handle = $this->text($query, 'taxonomy');
        if ($handle === '') {
            $view = $this->listPresenter->index($this->indexQuery->execute());

            return $this->render($view);
        }
        try {
            $data = $this->termListQuery->execute($handle, $query);
        } catch (\OutOfBoundsException) {
            return $this->responses->text('', 404);
        }
        $view = $this->listPresenter->terms($handle, $data);

        return $this->render($view);
    }

    public function taxonomyForm(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->form($request, $params, false);
    }

    public function termForm(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->form($request, $params, true);
    }

    private function form(ServerRequestInterface $request, array $params, bool $isTerm): ResponseInterface
    {
        $handle = $params['handle'] ?? '';
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $editing = $isTerm ? $id !== null : $handle !== '';
        $deleting = str_ends_with($request->getUri()->getPath(), '/delete');
        $locale = ContentLocales::requested($request->getQueryParams(), $this->settings);
        try {
            $form = $this->formQuery->execute($isTerm, $handle, $id, $locale);
        } catch (\OutOfBoundsException) {
            return $this->responses->text('', 404);
        }
        $group = $form['group'];
        $values = $form['values'];
        $context = $request->getQueryParams();
        $error = null;
        if ($request->getMethod() === 'POST') {
            $input = $request->getParsedBody();
            $input = is_array($input) ? $input : [];
            try {
                $locale = ContentLocales::requested($input, $this->settings);
                $context['locale'] = $locale;
                if ($isTerm && !$deleting) {
                    $values['name'] = $this->text($input, 'name');
                    $values['slug'] = $this->text($input, 'slug');
                } elseif (!$isTerm && !$deleting) {
                    $values['label'] = $this->text($input, 'label');
                    if (!$editing) {
                        $values['handle'] = $this->text($input, 'handle');
                    }
                }
                $this->submitTaxonomyForm->execute(
                    $isTerm,
                    $editing,
                    $deleting,
                    $handle,
                    $id,
                    $locale,
                    $values,
                    $this->text($input, 'confirm') === 'yes',
                );

                return $this->responses->redirect($this->listUrl($isTerm ? $handle : '', $context));
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (\PDOException) {
                $error = '保存失败，数据可能已被修改或标识重复，请检查后重试。';
            }
        }
        $view = $this->formPresenter->present(
            $isTerm,
            $editing,
            $deleting,
            $id,
            $locale,
            $group,
            $values,
            $error,
            $this->listUrl($isTerm ? $handle : '', $context),
        );

        return $this->render($view, $error === null ? 200 : 422);
    }

    public function reorderTerms(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        try {
            $context = $this->reorderTaxonomyTerms->execute($input);
        } catch (\InvalidArgumentException | \OutOfBoundsException) {
            return $this->responses->text('分类法排序数据无效。', 422);
        }

        return $this->responses->redirect($this->listUrl($context['taxonomy'], $input));
    }

    private function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    private function page(array $input): int
    {
        return max(1, (int) (filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
    }

    private function listUrl(string $handle, array $context): string
    {
        if ($handle === '') {
            return $this->urls->route('admin.taxonomies.index');
        }
        $parameters = [
            'taxonomy' => $handle,
            'locale' => ContentLocales::requested($context, $this->settings),
            'q' => mb_substr($this->text($context, 'q'), 0, 100),
            'page' => $this->page($context),
        ];

        return $this->urls->route('admin.taxonomies.index', $parameters);
    }

    private function render(AdminPageView $view, int $status = 200): ResponseInterface
    {
        return $this->responses->html($view->render($this->views), $status);
    }
}
