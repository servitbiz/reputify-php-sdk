<?php

declare(strict_types=1);

namespace Reputify;

use Reputify\Exception\ApiException;
use Reputify\Exception\AuthenticationException;
use Reputify\Exception\ConnectionException;
use Reputify\Exception\ReputifyException;
use Reputify\Http\CurlTransport;
use Reputify\Http\Response;
use Reputify\Http\Transport;
use Reputify\Resources\Analysis;
use Reputify\Resources\Awards;
use Reputify\Resources\Catalog;
use Reputify\Resources\Embeds;
use Reputify\Resources\Feedback;
use Reputify\Resources\Invites;
use Reputify\Resources\Projects;
use Reputify\Resources\Recordings;
use Reputify\Resources\Reviews;
use Reputify\Resources\Site;
use Reputify\Resources\Surveys;

/**
 * Der Client fuer api.reputify.io.
 *
 *     $reputify = new Reputify('rpf_live_…');
 *     $score    = $reputify->projects->profile(12)['score'];
 *     foreach ($reputify->reviews->list(12, ['min_rating' => 4])->autoPaging() as $review) { … }
 *
 * Drei Arten von Marke, alle ueber `Authorization: Bearer`:
 *
 * - `rpf_live_…` (Team) — liest und schreibt, je nach Rechten der Marke.
 * - `rpf_inv_…` (Umfrage) — kann nur `invites->create()`.
 * - ein `TokenSet` aus dem Verbinden-Dialog — erneuert sich selbst, wenn
 *   `oauth` mitgegeben wird; `on_refresh` muss das neue Paar speichern.
 *
 * Wiederholt wird bei 429 (nach `Retry-After`) und bei 5xx/Netzfehlern,
 * Letzteres nur fuer GET/PUT/DELETE — ein POST koennte doppelt anlegen.
 */
final class Reputify
{
    public const VERSION = '1.0.0';

    public const BASE_URL = 'https://api.reputify.io';

    public readonly Projects $projects;

    public readonly Reviews $reviews;

    public readonly Surveys $surveys;

    public readonly Invites $invites;

    public readonly Analysis $analysis;

    public readonly Embeds $embeds;

    public readonly Recordings $recordings;

    public readonly Awards $awards;

    public readonly Feedback $feedback;

    public readonly Catalog $catalog;

    public readonly Site $site;

    private Transport $transport;

    private string $baseUrl;

    private float $timeout;

    private int $maxRetries;

    private ?TokenSet $tokens = null;

    private ?string $token = null;

    private ?OAuth $oauth;

    /** @var (\Closure(TokenSet): void)|null */
    private ?\Closure $onRefresh;

    /** @var \Closure(float): void */
    private \Closure $sleep;

    private ?Response $lastResponse = null;

    /**
     * @param  string|TokenSet  $token
     * @param  array{
     *     base_url?: string,
     *     timeout?: float,
     *     max_retries?: int,
     *     transport?: Transport,
     *     oauth?: OAuth,
     *     on_refresh?: callable(TokenSet): void,
     *     sleep?: callable(float): void,
     * }  $options
     */
    public function __construct(string|TokenSet $token, array $options = [])
    {
        if ($token instanceof TokenSet) {
            $this->tokens = $token;
        } else {
            $this->token = $token;
        }

        $this->baseUrl = rtrim($options['base_url'] ?? self::BASE_URL, '/');
        $this->timeout = (float) ($options['timeout'] ?? 30);
        $this->maxRetries = max(0, (int) ($options['max_retries'] ?? 2));
        $this->transport = $options['transport'] ?? new CurlTransport();
        $this->oauth = $options['oauth'] ?? null;
        $this->onRefresh = isset($options['on_refresh']) ? \Closure::fromCallable($options['on_refresh']) : null;
        $this->sleep = isset($options['sleep'])
            ? \Closure::fromCallable($options['sleep'])
            : static fn (float $seconds) => usleep((int) ($seconds * 1_000_000));

        $this->projects = new Projects($this);
        $this->reviews = new Reviews($this);
        $this->surveys = new Surveys($this);
        $this->invites = new Invites($this);
        $this->analysis = new Analysis($this);
        $this->embeds = new Embeds($this);
        $this->recordings = new Recordings($this);
        $this->awards = new Awards($this);
        $this->feedback = new Feedback($this);
        $this->catalog = new Catalog($this);
        $this->site = new Site($this);
    }

    public static function userAgent(): string
    {
        return 'reputify-php/'.self::VERSION.' PHP/'.PHP_VERSION;
    }

    /** Das aktuelle Paar — nach einer Erneuerung ein anderes als im Konstruktor. */
    public function tokens(): ?TokenSet
    {
        return $this->tokens;
    }

    /** Die letzte Antwort, z. B. fuer `X-RateLimit-Remaining` oder `ETag`. */
    public function lastResponse(): ?Response
    {
        return $this->lastResponse;
    }

    /**
     * @return array{limit: ?int, remaining: ?int}
     */
    public function rateLimit(): array
    {
        $limit = $this->lastResponse?->header('X-RateLimit-Limit');
        $remaining = $this->lastResponse?->header('X-RateLimit-Remaining');

        return [
            'limit' => $limit === null ? null : (int) $limit,
            'remaining' => $remaining === null ? null : (int) $remaining,
        ];
    }

    /**
     * Eine Anfrage an einen beliebigen Pfad — fuer Endpunkte, die noch
     * keine eigene Methode haben.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|null  `null` bei 204 und 304
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): ?array
    {
        $method = strtoupper($method);
        $url = $this->baseUrl.'/'.ltrim($path, '/').self::query($query);
        $payload = $body === null ? null : (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $retryable = in_array($method, ['GET', 'HEAD', 'PUT', 'DELETE'], true);
        $refreshed = false;
        $attempt = 0;

        while (true) {
            $headers = [
                'Authorization' => 'Bearer '.$this->accessToken(),
                'Accept' => 'application/json',
                'User-Agent' => self::userAgent(),
            ];

            if ($payload !== null) {
                $headers['Content-Type'] = 'application/json';
            }

            try {
                $response = $this->transport->send($method, $url, $headers, $payload, $this->timeout);
            } catch (ConnectionException $e) {
                if ($retryable && $attempt < $this->maxRetries) {
                    ($this->sleep)($this->backoff($attempt++));

                    continue;
                }

                throw $e;
            }

            $this->lastResponse = $response;

            // Abgelaufen trotz Vorlauf (Uhr daneben, lange Anfrage): einmal erneuern.
            if ($response->status === 401 && ! $refreshed && $this->canRefresh()) {
                $this->refresh();
                $refreshed = true;

                continue;
            }

            if ($response->status === 429 && $attempt < $this->maxRetries) {
                $wait = $response->header('Retry-After');
                ($this->sleep)($wait !== null && is_numeric($wait) ? min((float) $wait, 60.0) : $this->backoff($attempt));
                $attempt++;

                continue;
            }

            if ($response->status >= 500 && $retryable && $attempt < $this->maxRetries) {
                ($this->sleep)($this->backoff($attempt++));

                continue;
            }

            if ($response->status >= 400) {
                throw ApiException::fromResponse($response);
            }

            if ($response->status === 204 || $response->status === 304) {
                return null;
            }

            $decoded = $response->json();

            if ($decoded === null && $response->body !== '') {
                throw new ReputifyException('Reputify returned a response that is not JSON (status '.$response->status.').');
            }

            return $decoded;
        }
    }

    /**
     * Eine Liste als `Page`.
     *
     * @param  array<string, mixed>  $query
     */
    public function page(string $path, array $query = []): Page
    {
        $body = $this->request('GET', $path, $query) ?? [];

        return Page::fromBody($body, fn (int $page) => $this->page($path, ['page' => $page] + $query));
    }

    private function accessToken(): string
    {
        if ($this->tokens === null) {
            return (string) $this->token;
        }

        if ($this->tokens->expiresSoon() && $this->canRefresh()) {
            $this->refresh();
        }

        return $this->tokens->accessToken;
    }

    private function canRefresh(): bool
    {
        return $this->tokens?->refreshToken !== null && $this->oauth !== null;
    }

    private function refresh(): void
    {
        if (! $this->canRefresh()) {
            throw new AuthenticationException('The access token expired and cannot be refreshed.', 401, 'unauthorized', []);
        }

        $this->tokens = $this->oauth->refresh((string) $this->tokens->refreshToken);

        // Vor dem naechsten Aufruf speichern: der alte Schluessel ist
        // verbraucht, und ein zweites Vorlegen sperrt die Verbindung.
        if ($this->onRefresh !== null) {
            ($this->onRefresh)($this->tokens);
        }
    }

    private function backoff(int $attempt): float
    {
        return min(0.5 * (2 ** $attempt), 8.0) + mt_rand(0, 250) / 1000;
    }

    /** @param array<string, mixed> $query */
    private static function query(array $query): string
    {
        $clean = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            $clean[$key] = match (true) {
                is_bool($value) => $value ? '1' : '0',
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                is_array($value) => implode(',', $value),
                default => (string) $value,
            };
        }

        return $clean === [] ? '' : '?'.http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }
}
