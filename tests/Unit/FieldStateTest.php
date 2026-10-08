<?php

namespace Tests\Unit;

use App\Support\Ui\FieldState;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\TestCase;

class FieldStateTest extends TestCase
{
    public function test_ids_derive_from_the_given_id(): void
    {
        $state = FieldState::for('email', 'login-email', help: 'Work email');

        $this->assertSame('login-email-help', $state->helpId());
        $this->assertSame('login-email-error', $state->errorId());
        $this->assertSame('login-email-help', $state->describedBy());
        $this->assertFalse($state->invalid());
    }

    public function test_error_comes_from_error_bag(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['Invalid.', 'Second.']]));

        $state = FieldState::for('email', 'e', $errors, 'Help');

        $this->assertTrue($state->invalid());
        $this->assertSame('Invalid.', $state->error);
        $this->assertSame('e-help e-error', $state->describedBy());
    }

    public function test_explicit_error_wins_over_error_bag(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['From bag.']]));

        $this->assertSame('Explicit.', FieldState::for('email', 'e', $errors, error: 'Explicit.')->error);
    }

    public function test_no_help_and_no_error_means_no_describedby(): void
    {
        $this->assertNull(FieldState::for('name', 'n')->describedBy());
    }

    public function test_array_names_map_to_dot_keys(): void
    {
        $this->assertSame('items.0.title', FieldState::errorKey('items[0][title]'));
        $this->assertSame('tags', FieldState::errorKey('tags[]'));
        $this->assertSame('email', FieldState::errorKey('email'));
    }
}
