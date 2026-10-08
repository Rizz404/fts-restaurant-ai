<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Modules\Assistant\Tools\Tool;
use App\Modules\Conversation\Support\Fingerprint;

/**
 * Shared plumbing for the restaurant tools: the provider function
 * definition is built from one description and one JSON schema, and the
 * invocation log gets a bounded view of the input (keys and a fingerprint,
 * never free text).
 */
abstract class BaseTool implements Tool
{
    abstract protected function description(): string;

    /**
     * JSON schema of the arguments (type object, properties, required).
     *
     * @return array<string, mixed>
     */
    abstract protected function parameters(): array;

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parameters() + ['additionalProperties' => false],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function loggableInput(array $arguments): array
    {
        return [
            'fields' => array_keys($arguments),
            'fingerprint' => Fingerprint::of($arguments),
        ];
    }
}
