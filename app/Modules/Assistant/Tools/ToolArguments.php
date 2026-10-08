<?php

namespace App\Modules\Assistant\Tools;

use Illuminate\Support\Facades\Validator;

/**
 * Strict argument validation shared by all tools: a JSON object with only
 * the declared keys, then Laravel rules (use integer:strict for integers so
 * "5" is not accepted as 5).
 */
final class ToolArguments
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $rules  Keys are the only allowed argument names.
     * @return array<string, mixed>
     *
     * @throws ToolArgumentException
     */
    public static function validate(array $arguments, array $rules): array
    {
        $unknown = array_diff(array_keys($arguments), array_keys($rules));
        if ($unknown !== []) {
            $fields = [];
            foreach (array_slice($unknown, 0, 5) as $key) {
                $fields[mb_substr((string) $key, 0, 40)] = ['unknown argument'];
            }

            throw new ToolArgumentException($fields);
        }

        $validator = Validator::make($arguments, $rules);
        if ($validator->fails()) {
            throw new ToolArgumentException($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * Decodes the provider's arguments string; it must be a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws ToolArgumentException
     */
    public static function decode(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true, 8);
        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new ToolArgumentException(['arguments' => ['must be a JSON object']]);
        }

        return $decoded;
    }
}
