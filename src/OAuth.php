<?php

declare(strict_types=1);

namespace Reputify;

use Reputify\Exception\OAuthException;
use Reputify\Http\CurlTransport;
use Reputify\Http\Transport;

/**
 * Der Verbinden-Dialog (OAuth 2.1, oeffentlicher Client mit PKCE S256).
 *
 *     $oauth = new OAuth('rpc_…', 'https://shop.example/reputify/callback');
 *     $pkce  = OAuth::pkce();                    // verifier in die Sitzung
 *     $state = bin2hex(random_bytes(16));        // ebenfalls
 *     header('Location: '.$oauth->authorizeUrl(['reviews:read'], $state, $pkce['challenge']));
 *
 *     // Rueckweg:
 *     $tokens = $oauth->exchangeCode($_GET['code'], $verifierAusDerSitzung);
 *
 * Eine Kennung (`client_id`) bekommt ein Plugin ueber `register()` (RFC 7591)
 * — einmal je Website, weil jede Website einen eigenen Rueckweg hat.
 * Es gibt kein Client-Secret: der Token-Endpunkt kennt nur `none`.
 */
final class OAuth
{
    public const AUTHORIZE_URL = 'https://app.reputify.io/oauth/authorize';

    public const TOKEN_URL = 'https://app.reputify.io/oauth/token';

    public const REGISTER_URL = 'https://app.reputify.io/oauth/register';

    public const RESOURCE = 'https://api.reputify.io';

    private Transport $transport;

    /** @param array{authorize_url?: string, token_url?: string, register_url?: string, resource?: string, timeout?: float, transport?: Transport} $options */
    public function __construct(
        private readonly string $clientId,
        private readonly string $redirectUri,
        private readonly array $options = [],
    ) {
        $this->transport = $options['transport'] ?? new CurlTransport();
    }

    /**
     * PKCE-Paar. Den `verifier` bis zum Rueckweg aufheben, die `challenge`
     * geht in die Adresse.
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function pkce(): array
    {
        $verifier = self::base64url(random_bytes(48));

        return ['verifier' => $verifier, 'challenge' => self::challenge($verifier)];
    }

    public static function challenge(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    /** @param list<string> $scopes z. B. `['widgets:read', 'invites:write']` */
    public function authorizeUrl(array $scopes, string $state, string $codeChallenge): string
    {
        return ($this->options['authorize_url'] ?? self::AUTHORIZE_URL).'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'resource' => $this->options['resource'] ?? self::RESOURCE,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Der Code ist fuenf Minuten gueltig und genau einmal einloesbar. */
    public function exchangeCode(string $code, string $codeVerifier): TokenSet
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $codeVerifier,
        ]);
    }

    /**
     * Neues Paar gegen den Erneuerungsschluessel. Der alte ist danach
     * verbraucht — das Ergebnis sofort speichern.
     */
    public function refresh(string $refreshToken): TokenSet
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ]);
    }

    /**
     * Einen Client anmelden (RFC 7591). Gibt die Antwort zurueck, darin
     * `client_id`.
     *
     * @param  list<string>  $redirectUris
     * @param  array{logo_uri?: string, client_uri?: string, timeout?: float, transport?: Transport, register_url?: string}  $options
     * @return array<string, mixed>
     */
    public static function register(string $clientName, array $redirectUris, array $options = []): array
    {
        $transport = $options['transport'] ?? new CurlTransport();
        $payload = array_filter([
            'client_name' => $clientName,
            'redirect_uris' => $redirectUris,
            'logo_uri' => $options['logo_uri'] ?? null,
            'client_uri' => $options['client_uri'] ?? null,
        ], fn ($v) => $v !== null);

        $response = $transport->send(
            'POST',
            $options['register_url'] ?? self::REGISTER_URL,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'User-Agent' => Reputify::userAgent()],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
            (float) ($options['timeout'] ?? 15),
        );

        $body = $response->json() ?? [];

        if ($response->status >= 400 || ! isset($body['client_id'])) {
            throw new OAuthException(
                (string) ($body['error_description'] ?? $body['message'] ?? 'Client registration failed.'),
                (string) ($body['error'] ?? 'invalid_client_metadata'),
                $response->status,
            );
        }

        return $body;
    }

    /** @param array<string, string> $params */
    private function token(array $params): TokenSet
    {
        $response = $this->transport->send(
            'POST',
            $this->options['token_url'] ?? self::TOKEN_URL,
            ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json', 'User-Agent' => Reputify::userAgent()],
            http_build_query($params, '', '&'),
            (float) ($this->options['timeout'] ?? 15),
        );

        $body = $response->json() ?? [];

        if ($response->status >= 400 || ! isset($body['access_token'])) {
            throw new OAuthException(
                (string) ($body['error_description'] ?? $body['message'] ?? 'Token request failed.'),
                (string) ($body['error'] ?? 'invalid_request'),
                $response->status,
            );
        }

        return TokenSet::fromTokenResponse($body);
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
