<?php

namespace App\Modules\Assistant\Evidence;

use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Menu\Data\MenuEvidence;

/**
 * The sources one AI turn may cite: dishes and info revisions that were
 * retrieved or returned by a tool during this turn. A model answer can only
 * point at IDs in this pool; the server builds every card from canonical data.
 */
final class EvidencePool
{
    /** @var array<int, MenuEvidence> item id => evidence */
    private array $menu = [];

    /** @var array<int, KnowledgeEvidence> revision id => evidence */
    private array $info = [];

    private int $unverifiedExcluded = 0;

    public function addMenu(MenuEvidence $evidence): void
    {
        $this->menu[$evidence->itemId] ??= $evidence;
    }

    public function addInfo(KnowledgeEvidence $evidence): void
    {
        $this->info[$evidence->revisionId] ??= $evidence;
    }

    public function noteUnverifiedExcluded(int $count): void
    {
        $this->unverifiedExcluded = max($this->unverifiedExcluded, $count);
    }

    public function merge(self $other): void
    {
        foreach ($other->menu as $evidence) {
            $this->addMenu($evidence);
        }
        foreach ($other->info as $evidence) {
            $this->addInfo($evidence);
        }
        $this->noteUnverifiedExcluded($other->unverifiedExcluded);
    }

    /**
     * @return list<MenuEvidence>
     */
    public function menu(): array
    {
        return array_values($this->menu);
    }

    /**
     * @return list<KnowledgeEvidence>
     */
    public function info(): array
    {
        return array_values($this->info);
    }

    public function unverifiedExcludedCount(): int
    {
        return $this->unverifiedExcluded;
    }

    /**
     * @return list<int>
     */
    public function menuIds(): array
    {
        return array_keys($this->menu);
    }

    /**
     * @return list<int>
     */
    public function revisionIds(): array
    {
        return array_keys($this->info);
    }

    public function hasMenu(int $itemId): bool
    {
        return isset($this->menu[$itemId]);
    }

    public function isEmpty(): bool
    {
        return $this->menu === [] && $this->info === [];
    }

    /**
     * Bounded copy for the prompt and the final cards (dishes first as given).
     */
    public function limited(int $maxMenu, int $maxInfo): self
    {
        $copy = new self;
        $copy->menu = array_slice($this->menu, 0, $maxMenu, true);
        $copy->info = array_slice($this->info, 0, $maxInfo, true);
        $copy->unverifiedExcluded = $this->unverifiedExcluded;

        return $copy;
    }

    /**
     * Only the cited items, in the order cited.
     *
     * @param  list<int>  $menuIds
     * @param  list<int>  $revisionIds
     */
    public function only(array $menuIds, array $revisionIds): self
    {
        $copy = new self;
        foreach ($menuIds as $id) {
            if (isset($this->menu[$id])) {
                $copy->menu[$id] = $this->menu[$id];
            }
        }
        foreach ($revisionIds as $id) {
            if (isset($this->info[$id])) {
                $copy->info[$id] = $this->info[$id];
            }
        }
        $copy->unverifiedExcluded = $this->unverifiedExcluded;

        return $copy;
    }
}
