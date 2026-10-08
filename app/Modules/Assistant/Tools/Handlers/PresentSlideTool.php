<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\MenuCategory;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Conversation\Support\TurnCards;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Menu\MenuRetriever;
use Illuminate\Validation\Rule;

/**
 * Offers a button that opens a slide (optionally a dish or a menu category).
 * It changes nothing and the deck never moves by itself: the guest decides.
 * Paths are built by the server from fixed routes, never from model text.
 */
class PresentSlideTool extends BaseTool
{
    public function __construct(private readonly MenuRetriever $menu) {}

    public function name(): string
    {
        return 'present_slide';
    }

    protected function description(): string
    {
        return 'Offer the guest a button to open a slide of the app (menu, picks, reservation, order, bookings, info). Optionally focus a dish (menu_item_id) or a menu category. It only adds a button; the guest decides whether to open it.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slide' => ['type' => 'string', 'enum' => SceneCode::values()],
                'focus' => [
                    'type' => 'object',
                    'properties' => [
                        'menu_item_id' => ['type' => 'integer', 'minimum' => 1],
                        'category' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['slide'],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'slide' => ['required', 'string', Rule::in(SceneCode::values())],
            'focus' => ['sometimes', 'array:menu_item_id,category'],
            'focus.menu_item_id' => ['sometimes', 'integer:strict', 'min:1'],
            'focus.category' => ['sometimes', 'string', Rule::in(MenuCategory::query()->active()->pluck('code')->all())],
        ]);
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $scene = SceneCode::from($arguments['slide']);
        $actions = [TurnCards::openScene($scene)];

        $category = $arguments['focus']['category'] ?? null;
        if ($category !== null && $scene === SceneCode::Menu) {
            $actions = [['type' => 'open_scene', 'scene' => $scene->value, 'label' => $scene->label(), 'path' => route('experience.menu', ['category' => $category], absolute: false)]];
        }

        $itemId = $arguments['focus']['menu_item_id'] ?? null;
        if ($itemId !== null) {
            $item = $this->menu->findEvidence($itemId, $context->locale);
            if ($item === null) {
                return ToolResult::error(ToolResult::NOT_FOUND, ['error' => 'dish_not_found']);
            }
            $actions[] = TurnCards::openMenuItem($item);
        }

        return new ToolResult(
            ToolResult::OK,
            ['offered_slide' => $scene->label(), 'note' => 'A button was added for the guest. The slide does not open by itself.'],
            actions: $actions,
            reference: ['slide' => $scene->value],
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['slide' => $arguments['slide'] ?? null, 'focus' => array_keys($arguments['focus'] ?? [])];
    }
}
