<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Modules\Experience\Enums\SceneCode;
use App\Support\Ui\ComponentStyles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Template previews for local/testing. Synthetic data only: no database
 * reads, accounts, or business writes.
 */
class PreviewController extends Controller
{
    /** Synthetic text used to prove that previews escape content. */
    public const UNSAFE_SAMPLE = '<script>alert("preview")</script> <b>bold?</b>';

    public function gallery(Request $request): View
    {
        // Demonstrates server-side validation output through FieldState.
        ViewFactory::share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'preview_error_email' => [__('ui.dev.errors.email')],
            'preview_error_subject' => [__('ui.dev.errors.subject')],
        ])));

        return view('dev.gallery', [
            'rows' => $this->rows($request),
            'unsafeSample' => self::UNSAFE_SAMPLE,
            'icons' => ComponentStyles::ICONS,
        ]);
    }

    public function admin(Request $request): View
    {
        return view('dev.admin', [
            'navItems' => $this->adminNavigation(),
            'rows' => $this->rows($request),
            'unsafeSample' => self::UNSAFE_SAMPLE,
        ]);
    }

    public function user(): View
    {
        return view('dev.user', [
            'navItems' => $this->slideNavigation(),
            'unsafeSample' => self::UNSAFE_SAMPLE,
        ]);
    }

    public function guest(): View
    {
        return view('dev.guest');
    }

    /**
     * @return list<array{label: string, href: ?string, icon: string, active?: bool}>
     */
    private function adminNavigation(): array
    {
        return [
            ['label' => __('ui.nav.overview'), 'href' => route('dev.admin'), 'icon' => 'squares-2x2', 'active' => true],
            ['label' => __('ui.nav.accounts'), 'href' => null, 'icon' => 'users'],
            ['label' => __('ui.nav.menu'), 'href' => null, 'icon' => 'cake'],
            ['label' => __('ui.nav.info'), 'href' => null, 'icon' => 'book-open'],
            ['label' => __('ui.nav.requests'), 'href' => null, 'icon' => 'clipboard-document-list'],
            ['label' => __('ui.nav.settings'), 'href' => null, 'icon' => 'cog-6-tooth'],
        ];
    }

    /**
     * Slide navigation of the guest presenter, with synthetic (non-clickable) targets.
     *
     * @return list<array{code: string, label: string, tab: string, href: ?string, icon: string, image: ?string, active: bool, accent: string}>
     */
    private function slideNavigation(): array
    {
        return array_map(fn (SceneCode $scene) => [
            'code' => $scene->value,
            'label' => $scene->label(),
            'tab' => $scene->tabLabel(),
            'href' => $scene === SceneCode::Welcome ? route('dev.user') : null,
            'icon' => $scene->icon(),
            'image' => null,
            'active' => $scene === SceneCode::Welcome,
            'accent' => $scene->accent(),
        ], SceneCode::cases());
    }

    private function rows(Request $request): LengthAwarePaginator
    {
        $tones = ['success', 'warning', 'neutral', 'info'];
        $categories = ['Starters', 'Mains', 'Desserts', 'Drinks'];
        $rows = [];

        for ($i = 1; $i <= 23; $i++) {
            $rows[] = [
                'name' => sprintf('Sample Dish %02d', $i),
                'category' => $categories[$i % 4],
                'tone' => $tones[$i % 4],
                'updated' => now()->subDays($i)->timezone(config('restaurant.timezone'))->format('Y-m-d'),
            ];
        }

        $perPage = 5;
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')],
        );
    }
}
