<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ViewException;
use Tests\TestCase;

class UiComponentContractTest extends TestCase
{
    /** Matches the boolean disabled attribute, not disabled: utility classes. */
    private const DISABLED_ATTRIBUTE = '/\sdisabled(?=[\s>])/';

    public function test_button_defaults_to_type_button(): void
    {
        $html = (string) $this->blade('<x-ui.button>Go</x-ui.button>');

        $this->assertStringContainsString('type="button"', $html);
        $this->assertDoesNotMatchRegularExpression(self::DISABLED_ATTRIBUTE, $html);
    }

    public function test_button_accepts_submit_and_rejects_unknown_types(): void
    {
        $this->blade('<x-ui.button type="submit">Go</x-ui.button>')->assertSee('type="submit"', false);
        $this->blade('<x-ui.button type="javascript">Go</x-ui.button>')->assertSee('type="button"', false);
    }

    public function test_disabled_button_is_disabled(): void
    {
        $this->assertMatchesRegularExpression(self::DISABLED_ATTRIBUTE, (string) $this->blade('<x-ui.button disabled>Go</x-ui.button>'));
    }

    public function test_loading_button_is_disabled_busy_and_keeps_its_label(): void
    {
        $view = $this->blade('<x-ui.button type="submit" loading>Saving</x-ui.button>');

        $this->assertMatchesRegularExpression(self::DISABLED_ATTRIBUTE, (string) $view);
        $view
            ->assertSee('aria-busy="true"', false)
            ->assertSee('animate-spin', false)
            ->assertSee('Saving');
    }

    public function test_link_button_shares_button_styles(): void
    {
        $button = (string) $this->blade('<x-ui.button variant="danger">X</x-ui.button>');
        $link = (string) $this->blade('<x-ui.link-button href="/x" variant="danger">X</x-ui.link-button>');

        $this->assertStringContainsString('href="/x"', $link);
        $this->assertStringContainsString('bg-danger', $button);
        $this->assertStringContainsString('bg-danger', $link);
    }

    public function test_disabled_link_button_is_not_a_link(): void
    {
        $this->blade('<x-ui.link-button href="/x" disabled>X</x-ui.link-button>')
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('href=', false);
    }

    public function test_unknown_variant_fails_loudly_in_testing(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unknown button variant "rainbow"');

        $this->blade('<x-ui.button variant="rainbow">X</x-ui.button>');
    }

    public function test_password_input_never_renders_old_value(): void
    {
        $this->withOldInput(['password' => 'secret-value', 'email' => 'person@example.test']);

        $this->blade('<x-ui.input name="password" id="p" type="password" />')
            ->assertDontSee('secret-value');
        $this->blade('<x-ui.input name="email" id="e" type="email" />')
            ->assertSee('value="person@example.test"', false);
    }

    public function test_field_links_label_help_and_error_to_control(): void
    {
        $this->shareErrors(['email' => ['Email is invalid.']]);

        $this->blade(
            '<x-ui.field name="email" id="f-email" label="Email" help="Work address">'.
            '<x-ui.input name="email" id="f-email" type="email" />'.
            '</x-ui.field>'
        )
            ->assertSee('for="f-email"', false)
            ->assertSee('id="f-email-help"', false)
            ->assertSee('id="f-email-error"', false)
            ->assertSee('aria-describedby="f-email-help f-email-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Email is invalid.');
    }

    public function test_field_without_error_is_not_invalid(): void
    {
        $this->blade(
            '<x-ui.field name="name" id="f-name" label="Name">'.
            '<x-ui.input name="name" id="f-name" />'.
            '</x-ui.field>'
        )
            ->assertDontSee('aria-invalid', false)
            ->assertDontSee('aria-describedby', false);
    }

    public function test_array_field_names_map_to_error_bag_keys(): void
    {
        $this->shareErrors(['items.0.title' => ['Title is required.']]);

        $this->blade(
            '<x-ui.field name="items[0][title]" id="item-0-title" label="Title">'.
            '<x-ui.textarea name="items[0][title]" id="item-0-title" />'.
            '</x-ui.field>'
        )
            ->assertSee('aria-describedby="item-0-title-error"', false)
            ->assertSee('Title is required.');
    }

    public function test_attributes_are_forwarded_to_controls(): void
    {
        $this->blade('<x-ui.input name="email" id="e" autocomplete="email" maxlength="80" data-test="x" x-model="email" class="mt-2" />')
            ->assertSee('autocomplete="email"', false)
            ->assertSee('maxlength="80"', false)
            ->assertSee('data-test="x"', false)
            ->assertSee('x-model="email"', false)
            ->assertSee('mt-2', false)
            ->assertSee('form-input', false);
    }

    public function test_select_marks_selected_option(): void
    {
        $this->blade('<x-ui.select name="team" id="t" value="it" :options="[\'hr\' => \'HR\', \'it\' => \'IT\']" />')
            ->assertSee('<option value="it" selected', false)
            ->assertDontSee('<option value="hr" selected', false);
    }

    public function test_text_props_and_slots_are_escaped(): void
    {
        $unsafe = '<img src=x onerror=alert(1)>';

        $this->blade('<x-ui.card :title="$t">{{ $t }}</x-ui.card>', ['t' => $unsafe])
            ->assertSee(e($unsafe), false)
            ->assertDontSee($unsafe, false);

        $this->blade('<x-ui.field name="n" id="n" :label="$t" :help="$t"><x-ui.input name="n" id="n" :value="$t" /></x-ui.field>', ['t' => $unsafe])
            ->assertDontSee($unsafe, false);

        $this->blade('<x-ui.icon name="x-mark" :label="$t" />', ['t' => '"><script>'])
            ->assertDontSee('"><script>', false);
    }

    public function test_icon_is_decorative_unless_labelled(): void
    {
        $this->blade('<x-ui.icon name="home" />')->assertSee('aria-hidden="true"', false);
        $this->blade('<x-ui.icon name="home" label="Home" />')
            ->assertSee('role="img"', false)
            ->assertSee('aria-label="Home"', false);
    }

    public function test_alert_uses_status_role_unless_alert_requested(): void
    {
        $this->blade('<x-ui.alert>Info</x-ui.alert>')->assertSee('role="status"', false);
        $this->blade('<x-ui.alert tone="danger" role="alert">Error</x-ui.alert>')->assertSee('role="alert"', false);
    }

    public function test_modal_is_named_trapped_and_cloaked(): void
    {
        $this->blade('<x-ui.modal name="demo" title="Demo">Body</x-ui.modal>')
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-labelledby="modal-demo-title"', false)
            ->assertSee('x-trap.inert.noscroll="open"', false)
            ->assertSee('x-cloak', false);
    }

    public function test_tabs_render_aria_relationships(): void
    {
        $this->blade(
            '<x-ui.tabs id="t" :items="[[\'id\' => \'a\', \'label\' => \'A\'], [\'id\' => \'b\', \'label\' => \'B\']]">'.
            '<x-ui.tab-panel name="a" tabs-id="t">Panel A</x-ui.tab-panel>'.
            '<x-ui.tab-panel name="b" tabs-id="t">Panel B</x-ui.tab-panel>'.
            '</x-ui.tabs>'
        )
            ->assertSee('role="tablist"', false)
            ->assertSee('id="t-tab-a"', false)
            ->assertSee('aria-controls="t-panel-a"', false)
            ->assertSee('aria-labelledby="t-tab-b"', false)
            ->assertSee('aria-selected="true"', false);
    }

    /**
     * @param  array<string, string>  $input
     */
    private function withOldInput(array $input): void
    {
        $session = $this->app['session.store'];
        $session->flashInput($input);
        $this->app['request']->setLaravelSession($session);
    }

    /**
     * @param  array<string, list<string>>  $messages
     */
    private function shareErrors(array $messages): void
    {
        View::share('errors', (new ViewErrorBag)->put('default', new MessageBag($messages)));
    }
}
