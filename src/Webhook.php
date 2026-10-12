<?php

declare(strict_types=1);

namespace Reputify;

use Reputify\Exception\WebhookException;

/**
 * Webhooks einer Umfrage pruefen.
 *
 * Reputify signiert den **rohen Body** mit dem Webhook-Schluessel der
 * Umfrage: `X-Reputify-Signature: sha256=<hex>`. Das Ereignis steht in
 * `X-Reputify-Event` und im Body (`event`), der Zeitpunkt in
 * `occurred_at` — der steht im Body und nicht in einer Kopfzeile, damit
 * er mitsigniert ist und eine mitgeschnittene Zustellung nicht beliebig
 * oft wiederholt werden kann.
 *
 *     $event = Webhook::verify(file_get_contents('php://input'),
 *         $_SERVER['HTTP_X_REPUTIFY_SIGNATURE'] ?? null, $secret);
 *
 * Wichtig: den Body so pruefen, wie er ankam. Ein `json_decode` und
 * erneutes `json_encode` aendert die Bytes, und die Signatur stimmt nicht mehr.
 */
final class Webhook
{
    public const EVENT_SUBMITTED = 'review.submitted';

    public const EVENT_HANDOVER = 'review.handover';

    public const EVENT_FEEDBACK = 'feedback.received';

    /**
     * @param  int  $tolerance  Hoechstalter von `occurred_at` in Sekunden; 0 = nicht pruefen.
     *                          Wiederholte Zustellungen (bis zu 25 Minuten spaeter) tragen den
     *                          urspruenglichen Zeitpunkt — die Vorgabe laesst ihnen Platz.
     * @return array<string, mixed> Der entschluesselte Body.
     *
     * @throws WebhookException
     */
    public static function verify(string $payload, ?string $signature, string $secret, int $tolerance = 3600, ?int $now = null): array
    {
        if ($secret === '') {
            throw new WebhookException('No webhook secret configured.');
        }

        if (! self::isValidSignature($payload, $signature, $secret)) {
            throw new WebhookException('Invalid webhook signature.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new WebhookException('Webhook body is not JSON.');
        }

        if ($tolerance > 0) {
            $at = isset($event['occurred_at']) ? strtotime((string) $event['occurred_at']) : false;

            if ($at === false) {
                throw new WebhookException('Webhook has no occurred_at.');
            }

            if (abs(($now ?? time()) - $at) > $tolerance) {
                throw new WebhookException('Webhook is too old (replay?).');
            }
        }

        return $event;
    }

    public static function isValidSignature(string $payload, ?string $signature, string $secret): bool
    {
        if ($signature === null || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals(self::sign($payload, $secret), $signature);
    }

    /** Die Signatur, wie Reputify sie setzt — fuer eigene Tests. */
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $secret);
    }
}
