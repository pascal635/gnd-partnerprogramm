<?php

namespace App\Models;

use App\Enums\PartnerStatus;
use App\Enums\PartnerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    // Input is always assembled explicitly by trusted code / Filament forms.
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'partner_type' => PartnerType::class,
            'status' => PartnerStatus::class,
            'iban_encrypted' => 'encrypted',
            'article_verified_at' => 'datetime',
        ];
    }

    /** First name for greetings; falls back to the legacy contact_person. */
    public function firstName(): string
    {
        if (filled($this->first_name)) {
            return $this->first_name;
        }

        $name = trim((string) $this->contact_person);

        return $name === '' ? '' : explode(' ', $name)[0];
    }

    /** Full contact name for tables; falls back to legacy field / company. */
    public function displayName(): string
    {
        $name = trim(implode(' ', array_filter([$this->first_name, $this->last_name])));

        return $name !== '' ? $name : (string) ($this->contact_person ?: $this->company_name);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<VoucherCode, $this> */
    public function voucherCodes(): HasMany
    {
        return $this->hasMany(VoucherCode::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
