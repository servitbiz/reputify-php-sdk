<?php

declare(strict_types=1);

namespace Reputify\Tests;

use Reputify\Http\Response;
use Reputify\Http\Transport;

/** Spielt vorbereitete Antworten ab und merkt sich jede Anfrage. */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @param list<Response|\Throwable> $responses */
    public function __construct(private array $responses) {}

    public static function json(int $status, mixed $body, array $headers = []): Response
    {
        return new Response($status, array_change_key_case($headers), $body === null ? '' : json_encode($body, JSON_PRESERVE_ZERO_FRACTION));
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $next = array_shift($this->responses) ?? throw new \LogicException('No more fake responses.');

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
