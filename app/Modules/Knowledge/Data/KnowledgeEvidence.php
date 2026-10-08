<?php

namespace App\Modules\Knowledge\Data;

use Carbon\CarbonImmutable;

/**
 * One retrieved source (TECHNICAL_CONTRACTS evidence DTO). The excerpt is
 * at most 2000 characters; UI callers can shorten it with toArray(300).
 */
final class KnowledgeEvidence
{
    public const MAX_EXCERPT = 2000;

    public const UI_EXCERPT = 300;

    public function __construct(
        public readonly int $articleId,
        public readonly int $revisionId,
        public readonly int $version,
        public readonly string $locale,
        public readonly string $slug,
        public readonly string $category,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly ?CarbonImmutable $effectiveFrom,
        public readonly ?CarbonImmutable $effectiveUntil,
        public readonly string $sourcePath,
        public readonly int $score = 0,
    ) {}

    public static function excerpt(string $body, int $limit = self::MAX_EXCERPT): string
    {
        $body = trim($body);

        return mb_strlen($body) > $limit ? rtrim(mb_substr($body, 0, $limit - 1)).'…' : $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $excerptLimit = self::MAX_EXCERPT): array
    {
        return [
            'article_id' => $this->articleId,
            'revision_id' => $this->revisionId,
            'version' => $this->version,
            'locale' => $this->locale,
            'slug' => $this->slug,
            'category' => $this->category,
            'title' => $this->title,
            'excerpt' => self::excerpt($this->excerpt, $excerptLimit),
            'effective_from' => $this->effectiveFrom?->toIso8601String(),
            'effective_until' => $this->effectiveUntil?->toIso8601String(),
            'source_path' => $this->sourcePath,
        ];
    }
}
