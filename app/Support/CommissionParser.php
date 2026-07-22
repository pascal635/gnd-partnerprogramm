<?php

namespace App\Support;

use App\Enums\CommissionKind;

/**
 * Parse the free-text PROVISION value ("150", "10%", "10,5%", "150€", "EUR 150").
 * Never guesses: unparseable input yields CommissionKind::None.
 */
class CommissionParser
{
    /**
     * @return array{kind: CommissionKind, value: float|null}
     */
    public static function parse(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return ['kind' => CommissionKind::None, 'value' => null];
        }

        $isPercent = str_ends_with($raw, '%');

        // Strip everything except digits, comma, dot and minus; normalise comma.
        $number = preg_replace('/[^0-9,.\-]/', '', $isPercent ? rtrim($raw, '%') : $raw);
        $number = str_replace(',', '.', (string) $number);

        if ($number === '' || ! is_numeric($number)) {
            return ['kind' => CommissionKind::None, 'value' => null];
        }

        return [
            'kind' => $isPercent ? CommissionKind::Percent : CommissionKind::Fix,
            'value' => (float) $number,
        ];
    }
}
