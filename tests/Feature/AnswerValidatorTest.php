<?php

namespace Tests\Feature;

use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Prompt\AnswerValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnswerValidatorTest extends TestCase
{
    private function answer(string $json): ChatCompletionResult
    {
        return new ChatCompletionResult(text: $json, toolCalls: [], finishReason: 'stop');
    }

    public function test_a_quoted_id_given_to_this_turn_is_accepted_as_an_integer(): void
    {
        $result = (new AnswerValidator)->validate($this->answer('{"text": "Buka 10.00–22.00.", "source_menu_item_ids": [], "source_revision_ids": ["1"]}'), [], [1, 3]);

        $this->assertTrue($result['ok']);
        $this->assertSame([1], $result['source_revision_ids']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIds(): array
    {
        return [
            'quoted id not given to this turn' => ['["9"]'],
            'non-numeric string' => ['["one"]'],
            'leading zero' => ['["01"]'],
            'float' => ['[1.0]'],
            'negative' => ['["-1"]'],
        ];
    }

    #[DataProvider('invalidIds')]
    public function test_ids_that_are_not_given_to_this_turn_are_still_rejected(string $ids): void
    {
        $result = (new AnswerValidator)->validate($this->answer('{"text": "x", "source_menu_item_ids": [], "source_revision_ids": '.$ids.'}'), [], [1, 3]);

        $this->assertSame(['ok' => false, 'code' => AnswerValidator::INVALID_SOURCE], $result);
    }
}
