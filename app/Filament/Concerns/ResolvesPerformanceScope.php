<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared scoping for performance widgets: reads the dashboard's page filters
 * (from/until/partner_id) and restricts to the logged-in partner when the
 * viewer is a partner user (portal), or to the selected partner for staff.
 *
 * Requires the host widget to use Filament's InteractsWithPageFilters
 * (provides $this->pageFilters).
 */
trait ResolvesPerformanceScope
{
    protected function filterValue(string $key, mixed $default = null): mixed
    {
        return $this->pageFilters[$key] ?? $default;
    }

    protected function rangeFrom(): Carbon
    {
        $value = $this->filterValue('from');

        return $value ? Carbon::parse($value)->startOfDay() : now()->subDays(30)->startOfDay();
    }

    protected function rangeUntil(): Carbon
    {
        $value = $this->filterValue('until');

        return $value ? Carbon::parse($value)->endOfDay() : now()->endOfDay();
    }

    /** The partner to scope to, or null for "all partners" (staff view). */
    protected function scopePartnerId(): ?int
    {
        $user = auth()->user();

        if ($user && $user->isPartner()) {
            return $user->partner_id;
        }

        $selected = $this->filterValue('partner_id');

        return $selected ? (int) $selected : null;
    }
}
