<?php

namespace App\Modules\Requests\Support;

/**
 * Translated messages the request wizard (JS) needs, in the active locale.
 * Passed to the page as data so the script never hardcodes language.
 */
final class WizardLabels
{
    /**
     * @return array<string, string>
     */
    public static function wizard(): array
    {
        return [
            'invalid' => __('ui.errors.validation_failed'),
            'network' => __('ui.errors.network'),
            'session' => __('ui.errors.session_expired'),
            'rateLimited' => __('ui.errors.rate_limited'),
            'generic' => __('ui.errors.generic'),
            'slotFull' => __('requests.errors.slot_full'),
            'itemUnavailable' => __('requests.errors.item_unavailable'),
            'contactRequired' => __('requests.errors.contact_required'),
            'draftExpired' => __('requests.errors.draft_expired'),
            'draftChanged' => __('requests.errors.draft_changed'),
            'chooseSlot' => __('requests.choose_slot'),
            'emptyCart' => __('requests.cart_empty'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function thread(): array
    {
        return [
            'network' => __('ui.errors.network'),
            'generic' => __('ui.errors.generic'),
            'closed' => __('ui.errors.request_closed'),
            'rateLimited' => __('ui.errors.rate_limited'),
            'versionConflict' => __('ui.errors.version_conflict'),
            'invalidTransition' => __('requests.errors.invalid_transition'),
            'reasonRequired' => __('requests.errors.reason_required'),
            'cancelled' => __('requests.cancelled_notice'),
        ];
    }
}
