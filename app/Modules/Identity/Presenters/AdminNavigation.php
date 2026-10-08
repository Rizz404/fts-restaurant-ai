<?php

namespace App\Modules\Identity\Presenters;

use App\Modules\Identity\ActorContext;
use Illuminate\Support\Facades\Route;

/**
 * Builds the admin sidebar from server-side abilities. Items for modules
 * that later plans deliver are shown disabled (href null) to the roles that
 * will use them, until the route exists.
 */
final class AdminNavigation
{
    /**
     * @return list<array{label: string, href: ?string, icon: string, active: bool}>
     */
    public static function items(ActorContext $actor, ?string $currentRoute): array
    {
        $is = fn (string $pattern) => $currentRoute !== null && fnmatch($pattern, $currentRoute);
        $link = fn (string $route) => Route::has($route) ? route($route) : null;
        $items = [];

        $items[] = ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard'), 'icon' => 'squares-2x2', 'active' => $is('admin.dashboard')];

        if ($actor->isAdmin()) {
            $items[] = ['label' => __('ui.nav.accounts'), 'href' => route('admin.users.index'), 'icon' => 'users', 'active' => $is('admin.users.*')];
        }

        // Admin and editor manage the menu; staff reach it to mark dishes sold out.
        if ($actor->canAccessAdminArea()) {
            $items[] = ['label' => __('ui.nav.menu'), 'href' => $link('admin.menu.index'), 'icon' => 'cake', 'active' => $is('admin.menu.*')];
        }

        if ($actor->isAdmin() || $actor->isEditor()) {
            $items[] = ['label' => __('ui.nav.info'), 'href' => $link('admin.info.index'), 'icon' => 'book-open', 'active' => $is('admin.info.*')];
        }

        if ($actor->isStaff() && $actor->teamCodes !== []) {
            $items[] = ['label' => __('ui.nav.requests'), 'href' => $link('admin.requests.index'), 'icon' => 'clipboard-document-list', 'active' => $is('admin.requests.*')];
        }

        if ($actor->isAdmin()) {
            $items[] = ['label' => __('ui.nav.operations'), 'href' => $link('admin.operations'), 'icon' => 'chart-bar', 'active' => $is('admin.operations')];
            $items[] = ['label' => __('ui.nav.settings'), 'href' => $link('admin.settings.edit'), 'icon' => 'cog-6-tooth', 'active' => $is('admin.settings.*')];
        }

        return $items;
    }
}
