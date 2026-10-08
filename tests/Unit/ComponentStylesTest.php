<?php

namespace Tests\Unit;

use App\Support\Ui\ComponentStyles;
use InvalidArgumentException;
use Tests\TestCase;

class ComponentStylesTest extends TestCase
{
    public function test_defaults_resolve_without_arguments(): void
    {
        $this->assertStringContainsString('bg-primary', ComponentStyles::button());
        $this->assertStringContainsString('h-10', ComponentStyles::button());
        $this->assertStringContainsString('bg-surface-muted', ComponentStyles::badge());
    }

    public function test_unknown_values_throw_in_testing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComponentStyles::badge('purple');
    }

    public function test_unknown_values_fall_back_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertSame(ComponentStyles::badge('neutral'), ComponentStyles::badge('purple'));
        $this->assertSame('heroicon-o-information-circle', ComponentStyles::icon('not-whitelisted'));
    }

    public function test_icon_names_are_whitelisted(): void
    {
        $this->assertSame('heroicon-o-home', ComponentStyles::icon('home'));
    }

    public function test_mappings_only_use_semantic_color_tokens(): void
    {
        $palette = '/\b(?:bg|text|border|ring)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b|\b(?:bg|text)-(?:white|black)\b/';

        $reflection = new \ReflectionClass(ComponentStyles::class);
        $all = json_encode($reflection->getConstants());

        $this->assertDoesNotMatchRegularExpression($palette, $all);
    }
}
