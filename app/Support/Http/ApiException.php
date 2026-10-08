<?php

namespace App\Support\Http;

use RuntimeException;

/**
 * Domain-level error rendered as the shared JSON error envelope.
 *
 * The code must have a translation in ui.errors.<code>; messages are never
 * taken from provider bodies or other untrusted sources.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $fields
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $fields = [],
    ) {
        parent::__construct($errorCode);
    }
}
