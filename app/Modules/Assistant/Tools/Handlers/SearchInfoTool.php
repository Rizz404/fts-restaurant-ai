<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Assistant\Evidence\EvidencePool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Identity\Guest\GuestContext;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\KnowledgeRetriever;
use Illuminate\Validation\Rule;

/**
 * Searches the published restaurant info (hours, location, policies, FAQ).
 * Only the approved published revision is returned; a draft is never visible.
 */
class SearchInfoTool extends BaseTool
{
    public function __construct(private readonly KnowledgeRetriever $knowledge) {}

    public function name(): string
    {
        return 'search_info';
    }

    protected function description(): string
    {
        return 'Search the official restaurant information (opening hours, location, policies, FAQ). Use it for any question about the restaurant itself.';
    }

    protected function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                'category' => ['type' => 'string', 'enum' => KnowledgeCategory::values()],
            ],
            'required' => ['query'],
        ];
    }

    public function validate(GuestContext $guest, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'query' => ['required', 'string', 'min:1', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(KnowledgeCategory::values())],
        ]);
    }

    public function execute(GuestContext $guest, array $arguments, ToolContext $context): ToolResult
    {
        $limit = (int) config('assistant.evidence.max_info_items');
        $items = $this->knowledge->search($context->locale, $arguments['query'], $arguments['category'] ?? null, $limit);

        $pool = new EvidencePool;
        foreach ($items as $item) {
            $pool->addInfo($item);
        }

        return new ToolResult(
            ToolResult::OK,
            [
                'count' => count($items),
                'articles' => array_map(fn (KnowledgeEvidence $item) => ModelView::article($item, 1200), $items),
            ],
            evidence: $pool,
            reference: ['count' => count($items)],
        );
    }

    public function loggableInput(array $arguments): array
    {
        return parent::loggableInput($arguments) + ['category' => $arguments['category'] ?? null];
    }
}
