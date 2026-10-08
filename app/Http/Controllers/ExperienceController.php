<?php

namespace App\Http\Controllers;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Queries\BuildScene;
use Illuminate\Contracts\View\View;

/**
 * Public deck slides. Each slide is rendered from SceneData built server-side;
 * the guest needs no login and no guest session row for page views.
 * Slides with domain content (menu, info, reservation, order, bookings) have
 * their own controllers that extend this one's scene() helper.
 */
class ExperienceController extends Controller
{
    public function __construct(protected readonly BuildScene $buildScene) {}

    public function welcome(): View
    {
        return $this->scene(SceneCode::Welcome, 'experience.welcome');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{menu_item_id?: ?int, request_public_id?: ?string}  $context
     */
    protected function scene(SceneCode $scene, string $view, array $data = [], array $context = []): View
    {
        return view($view, ['scene' => $this->buildScene->handle($scene, $context)] + $data);
    }
}
