<?php

namespace App\Support\Deals;

/**
 * People read Deal screens in their own language. The older Commitment/Fulfillment kernel speaks English in its refusals;
 * this maps the ones a person can meet from the Work and Money tabs to a localized sentence, and anything else to a
 * plain localized "that could not be done" (the English reason is reported for developers, never shown).
 */
final class DealErrors
{
    private const KNOWN = [
        'Fulfillment quantity exceeds the remaining Commitment quantity.' => 'quantity_exceeds',
        'Accepting this Fulfillment would exceed the Commitment quantity.' => 'quantity_exceeds',
        'The confirmed quantity cannot exceed what was reported.' => 'confirm_exceeds',
        'Rejecting or requesting clarification requires a review note.' => 'review_note',
        'Fulfillment actual end cannot precede actual start.' => 'period_order',
        'Fulfillment actual start/end must be provided together.' => 'period_pair',
        'Fulfillment may attach at most twenty Assets and twenty evidence references.' => 'evidence_limit',
        'This Occurrence already has an active Fulfillment record.' => 'occurrence_taken',
        'Time-based Fulfillment requires a completed Occurrence with actual start/end.' => 'occurrence_not_done',
        'This media type is not supported.' => 'media_type',
    ];

    public static function localize(string $message): string
    {
        if (isset(self::KNOWN[$message])) {
            return (string) __('deals.errors.'.self::KNOWN[$message]);
        }

        // A message the Deal actions already wrote through the translations is shown as it is; any other English is not.
        return self::isLocalized($message) ? $message : (string) __('deals.errors.generic');
    }

    private static function isLocalized(string $message): bool
    {
        foreach ((array) __('deals.errors') as $text) {
            if ($text === $message) {
                return true;
            }
        }

        return false;
    }
}
