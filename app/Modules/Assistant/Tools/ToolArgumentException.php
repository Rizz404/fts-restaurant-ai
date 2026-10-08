<?php

namespace App\Modules\Assistant\Tools;

use RuntimeException;

/**
 * Invalid tool arguments. Fields are returned to the model as data so it can
 * correct the call; they never contain provider bodies or secrets.
 */
final class ToolArgumentException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $fields
     */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('Invalid tool arguments.');
    }
}
