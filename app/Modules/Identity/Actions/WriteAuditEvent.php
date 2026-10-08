<?php

namespace App\Modules\Identity\Actions;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Appends an audit record. Sensitive keys are removed from metadata so a
 * caller mistake cannot persist passwords, tokens, or secrets.
 */
class WriteAuditEvent
{
    private const SENSITIVE_KEY = '/pass(word)?|token|secret|credential|api[_-]?key/i';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(?User $actor, string $action, Model $subject, array $metadata = []): AuditEvent
    {
        return AuditEvent::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => (string) $subject->getKey(),
            'metadata' => $this->scrub($metadata) ?: null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrub(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }
}
