<?php

namespace App\Services\Attribution;

use App\Enums\AttributedVia;
use App\Models\Conversion;
use App\Models\Customer;
use App\Models\Lead;

/**
 * Lifetime attribution: a customer belongs to the partner who brought them
 * first (first-touch). Customers are recognised by email (WP form) and by
 * Pipedrive person id (Zapier, PII-free). Ownership only changes manually.
 */
class CustomerResolver
{
    public static function normalizeEmail(mixed $email): ?string
    {
        $norm = mb_strtolower(trim((string) $email));

        return $norm === '' ? null : $norm;
    }

    public static function normalizePersonId(mixed $person): ?string
    {
        // Zapier may send the plain id or Pipedrive's {value, name} object.
        if (is_array($person)) {
            $person = $person['value'] ?? $person['id'] ?? null;
        }

        $id = trim((string) $person);

        return $id === '' ? null : $id;
    }

    public function forEmail(?string $emailNorm): ?Customer
    {
        return filled($emailNorm) ? Customer::firstOrCreate(['email_norm' => $emailNorm]) : null;
    }

    public function forPersonId(?string $personId): ?Customer
    {
        return filled($personId) ? Customer::where('pipedrive_person_id', $personId)->first() : null;
    }

    /** First-touch: sets the owner only while the customer has none. */
    public function claim(Customer $customer, ?int $partnerId, ?int $voucherCodeId, ?int $leadId = null): void
    {
        $changes = [];

        if ($customer->partner_id === null && $partnerId !== null) {
            $changes['partner_id'] = $partnerId;
            $changes['voucher_code_id'] = $voucherCodeId;
        }

        if ($customer->first_lead_id === null && $leadId !== null) {
            $changes['first_lead_id'] = $leadId;
        }

        if ($changes !== []) {
            $customer->update($changes);
        }
    }

    /** Staff decision: overrides first-touch for all future orders. */
    public function reassign(Customer $customer, int $partnerId, ?int $voucherCodeId): void
    {
        $customer->update(['partner_id' => $partnerId, 'voucher_code_id' => $voucherCodeId]);
    }

    /**
     * Lead attributes for an order of an owned customer: the owner and the
     * terms of the customer's original code (partner default as fallback).
     *
     * @return array<string, mixed>
     */
    public function attributionFor(Customer $customer): array
    {
        $voucher = $customer->voucherCode()->withTrashed()->first();
        $partner = $customer->partner()->withTrashed()->first();

        return [
            'partner_id' => $customer->partner_id,
            'voucher_code_id' => $voucher?->id,
            'voucher_partner_raw' => $voucher?->partner_label ?? $partner?->company_name,
            'voucher_commission_raw' => $voucher?->commission_raw ?? $partner?->default_commission_raw,
            'attributed_via' => AttributedVia::Customer,
        ];
    }

    /** Remembers the Pipedrive person on the lead's customer; returns the surviving customer. */
    public function attachPerson(Lead $lead, string $personId): Customer
    {
        $customer = $lead->customer;

        if ($customer) {
            return $this->linkPerson($customer, $personId);
        }

        $customer = $this->forPersonId($personId) ?? Customer::create(['pipedrive_person_id' => $personId]);
        $lead->update(['customer_id' => $customer->id]);
        $this->claim($customer, $lead->partner_id, $lead->voucher_code_id, $lead->id);

        return $customer;
    }

    public function linkPerson(Customer $customer, string $personId): Customer
    {
        if ($customer->pipedrive_person_id !== null) {
            return $customer; // keep the first known person link
        }

        $other = $this->forPersonId($personId);

        if ($other && ! $other->is($customer)) {
            return $this->merge($customer, $other);
        }

        $customer->update(['pipedrive_person_id' => $personId]);

        return $customer;
    }

    /** Merges $drop into $keep. $keep's owner wins; gaps are filled from $drop. */
    public function merge(Customer $keep, Customer $drop): Customer
    {
        if ($keep->is($drop)) {
            return $keep;
        }

        Lead::where('customer_id', $drop->id)->update(['customer_id' => $keep->id]);
        Conversion::where('customer_id', $drop->id)->update(['customer_id' => $keep->id]);

        $fill = [];
        if ($keep->partner_id === null && $drop->partner_id !== null) {
            $fill['partner_id'] = $drop->partner_id;
            $fill['voucher_code_id'] = $drop->voucher_code_id;
        }
        foreach (['email_norm', 'pipedrive_person_id', 'first_lead_id'] as $field) {
            if ($keep->{$field} === null && $drop->{$field} !== null) {
                $fill[$field] = $drop->{$field};
            }
        }

        // Delete first: email_norm / pipedrive_person_id are unique.
        $drop->delete();

        if ($fill !== []) {
            $keep->update($fill);
        }

        return $keep->refresh();
    }
}
