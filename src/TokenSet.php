<?php

declare(strict_types=1);

namespace Reputify;

/**
 * Zugangs- und Erneuerungsschluessel aus dem Verbinden-Dialog.
 *
 * Die Zugangsmarke gilt eine Stunde. Der Erneuerungsschluessel (`rpf_rt_…`)
 * **rotiert**: jede Erneuerung liefert einen neuen, und der alte ist
 * verbraucht. Wer einen verbrauchten noch einmal vorlegt, sperrt die ganze
 * Verbindung — deshalb muss jedes neue TokenSet gespeichert sein, bevor
 * der naechste Aufruf hinausgeht.
 */
final class TokenSet
{
    /** @param array{id: int, name: string}|null $project */
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly int $expiresAt,
        public readonly string $scope = '',
        public readonly ?array $project = null,
    ) {}

    /** @param array<string, mixed> $body Antwort von `/oauth/token`. */
    public static function fromTokenResponse(array $body, ?int $now = null): self
    {
        return new self(
            (string) $body['access_token'],
            isset($body['refresh_token']) ? (string) $body['refresh_token'] : null,
            ($now ?? time()) + (int) ($body['expires_in'] ?? 3600),
            (string) ($body['scope'] ?? ''),
            isset($body['project']) && is_array($body['project']) ? $body['project'] : null,
        );
    }

    /** @param array<string, mixed> $data Was `toArray()` geschrieben hat. */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['access_token'],
            isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            (int) $data['expires_at'],
            (string) ($data['scope'] ?? ''),
            isset($data['project']) && is_array($data['project']) ? $data['project'] : null,
        );
    }

    /** @return array<string, mixed> Zum Speichern (Datenbank, `wp_options`, …). */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt,
            'scope' => $this->scope,
            'project' => $this->project,
        ];
    }

    /** Laeuft in den naechsten `$leeway` Sekunden ab (oder ist schon abgelaufen). */
    public function expiresSoon(int $leeway = 60, ?int $now = null): bool
    {
        return ($now ?? time()) + $leeway >= $this->expiresAt;
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return array_values(array_filter(explode(' ', $this->scope)));
    }
}
