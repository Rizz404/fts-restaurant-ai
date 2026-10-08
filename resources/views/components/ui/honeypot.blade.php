{{--
    Hidden anti-bot field for guest forms. Humans never see or fill it; RejectHoneypot answers a filled field with a
    plain 422. No props besides the field name; visually hidden but not display:none so simple bots still fill it.
--}}
@props([
    'name' => \App\Http\Middleware\RejectHoneypot::FIELD,
])

<div class="sr-only" aria-hidden="true">
    <label>
        {{ __('ui.honeypot_label') }}
        <input type="text" name="{{ $name }}" value="" tabindex="-1" autocomplete="off" data-honeypot>
    </label>
</div>
