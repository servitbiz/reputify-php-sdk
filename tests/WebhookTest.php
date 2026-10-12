<?php

declare(strict_types=1);

namespace Reputify\Tests;

use PHPUnit\Framework\TestCase;
use Reputify\Exception\WebhookException;
use Reputify\Webhook;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private function body(string $at): string
    {
        return json_encode(['review' => ['id' => 1], 'event' => 'review.submitted', 'occurred_at' => $at], JSON_UNESCAPED_SLASHES);
    }

    public function test_a_signed_body_is_accepted(): void
    {
        $body = $this->body('2026-10-12T10:00:00+02:00');
        $event = Webhook::verify($body, Webhook::sign($body, self::SECRET), self::SECRET, 300, strtotime('2026-10-12T10:01:00+02:00'));

        $this->assertSame(Webhook::EVENT_SUBMITTED, $event['event']);
    }

    public function test_a_changed_body_is_refused(): void
    {
        $body = $this->body('2026-10-12T10:00:00+02:00');
        $this->expectException(WebhookException::class);
        Webhook::verify($body.' ', Webhook::sign($body, self::SECRET), self::SECRET, 0);
    }

    public function test_an_old_delivery_is_refused(): void
    {
        $body = $this->body('2026-10-10T10:00:00+02:00');
        $this->expectExceptionMessage('too old');
        Webhook::verify($body, Webhook::sign($body, self::SECRET), self::SECRET, 3600, strtotime('2026-10-12T10:00:00+02:00'));
    }

    public function test_the_signature_matches_the_server_formula(): void
    {
        $this->assertSame('sha256='.hash_hmac('sha256', 'abc', 'k'), Webhook::sign('abc', 'k'));
        $this->assertFalse(Webhook::isValidSignature('abc', null, 'k'));
        $this->assertFalse(Webhook::isValidSignature('abc', hash_hmac('sha256', 'abc', 'k'), 'k'));
    }
}
