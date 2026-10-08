<?php

namespace App\Modules\Conversation\Support;

use App\Models\KnowledgeRevision;
use App\Models\MenuItem;
use App\Models\TurnEvidence;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use App\Modules\Menu\Data\MenuEvidence;

/**
 * Classifies past turns by whether their sources are still valid:
 *
 * - "ok": every dish is still published and unchanged (price, availability,
 *   allergen/diet data, text) and every info revision is still visible;
 * - "stale": a dish changed, or an info revision was replaced but its
 *   article is still readable;
 * - "unavailable": a dish is no longer published, or an info article is no
 *   longer readable.
 *
 * Stale and unavailable turns are excluded from prompts; unavailable turns
 * are replaced by a standard notice in the history.
 */
final class EvidenceAvailability
{
    /**
     * @param  list<int>  $turnIds
     * @return array<int, 'ok'|'stale'|'unavailable'> turn id => availability (only turns with evidence)
     */
    public static function forTurns(array $turnIds): array
    {
        if ($turnIds === []) {
            return [];
        }

        $evidence = TurnEvidence::query()->whereIn('turn_id', $turnIds)->get();
        if ($evidence->isEmpty()) {
            return [];
        }

        $menuRows = $evidence->where('evidence_type', TurnEvidence::MENU_ITEM);
        $infoRows = $evidence->where('evidence_type', TurnEvidence::INFO_REVISION);

        $items = MenuItem::query()->published()
            ->whereIn('menu_items.id', $menuRows->pluck('evidence_id')->unique()->all())
            ->with(['translations', 'category.translations', 'allergens', 'dietaryTags'])
            ->get()
            ->keyBy('id');

        $revisionIds = $infoRows->pluck('evidence_id')->unique()->values()->all();
        $visible = VisibleRevisions::query()->whereIn('knowledge_revisions.id', $revisionIds)->pluck('knowledge_revisions.id')->map(fn ($id) => (int) $id)->all();
        $articleOf = KnowledgeRevision::query()->whereIn('id', $revisionIds)->pluck('article_id', 'id');
        $readableArticles = VisibleRevisions::query()
            ->whereIn('knowledge_revisions.article_id', $articleOf->values()->unique()->all())
            ->pluck('knowledge_revisions.article_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $result = [];
        foreach ($evidence->groupBy('turn_id') as $turnId => $rows) {
            $state = 'ok';

            foreach ($rows as $row) {
                if ($row->evidence_type === TurnEvidence::MENU_ITEM) {
                    $item = $items->get((int) $row->evidence_id);
                    if ($item === null) {
                        $state = 'unavailable';
                        break;
                    }
                    if (! hash_equals($row->excerpt_hash, MenuEvidence::fromItem($item, $row->locale)->fingerprint())) {
                        $state = 'stale';
                    }

                    continue;
                }

                if (in_array((int) $row->evidence_id, $visible, true)) {
                    continue;
                }
                if (! in_array((int) ($articleOf[$row->evidence_id] ?? 0), $readableArticles, true)) {
                    $state = 'unavailable';
                    break;
                }
                $state = 'stale';
            }

            $result[(int) $turnId] = $state;
        }

        return $result;
    }
}
