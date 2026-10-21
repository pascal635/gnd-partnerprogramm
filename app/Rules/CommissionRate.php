<?php

namespace App\Rules;

use App\Enums\CommissionKind;
use App\Support\CommissionParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Accepts "5%" (percent of the order value) or "50" (fixed euros); empty is allowed. */
class CommissionRate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (CommissionParser::parse((string) $value)['kind'] === CommissionKind::None) {
            $fail('Bitte z. B. „5%" (Prozent vom Auftragswert) oder „50" (Euro je Auftrag) eingeben.');
        }
    }
}
