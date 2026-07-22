<?php

namespace App\Support;

/**
 * HMAC-SHA256 over "<timestamp>.<raw_body>" — shared by inbound verification
 * and outbound signing so both sides use identical framing.
 */
class WebhookSignature
{
    public static function compute(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function header(string $secret, string $timestamp, string $body): string
    {
        return 'sha256='.self::compute($secret, $timestamp, $body);
    }

    /**
     * Constant-time verification with an anti-replay window.
     */
    public static function verify(
        string $secret,
        ?string $signatureHeader,
        ?string $timestamp,
        string $body,
        int $replayWindow = 300,
    ): bool {
        if (blank($signatureHeader) || blank($timestamp) || ! ctype_digit((string) $timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $replayWindow) {
            return false;
        }

        $expected = self::header($secret, $timestamp, $body);

        return hash_equals($expected, $signatureHeader);
    }
}
