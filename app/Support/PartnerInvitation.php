<?php

namespace App\Support;

use App\Mail\PartnerInvitationMail;
use App\Models\Partner;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Legt (falls nötig) den Portal-Login eines Partners an und verschickt die
 * Einladungs-Mail mit „Passwort festlegen"-Link (Filament-Reset-Route).
 */
class PartnerInvitation
{
    /** Stellt sicher, dass ein Partner-User existiert (mit Rolle + Verknüpfung). */
    public static function ensureUser(Partner $partner): ?User
    {
        if (blank($partner->email)) {
            return null;
        }

        $user = User::firstOrCreate(
            ['email' => $partner->email],
            [
                'name' => $partner->company_name,
                'partner_id' => $partner->id,
                'password' => Hash::make(Str::random(40)),
                'is_active' => true,
            ],
        );

        $user->forceFill(['partner_id' => $partner->id])->save();
        $user->syncRoles('partner');

        return $user;
    }

    /**
     * Verschickt die Einladung. Gibt false zurück, wenn kein Login möglich ist
     * (z. B. fehlende E-Mail). Wirft bei echten Versandfehlern (SMTP) weiter.
     */
    public static function send(Partner $partner): bool
    {
        $user = static::ensureUser($partner);

        if (! $user) {
            return false;
        }

        $token = Password::broker()->createToken($user);
        // Filaments eigene Methode erzeugt eine korrekt signierte Reset-URL
        // (die Reset-Seite ist per 'signed'-Middleware geschützt).
        $url = Filament::getPanel('portal')->getResetPasswordUrl($token, $user);

        Mail::to($user->email)->send(
            new PartnerInvitationMail($partner->firstName() ?: $partner->company_name, $url),
        );

        return true;
    }
}
