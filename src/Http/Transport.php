<?php

declare(strict_types=1);

namespace Reputify\Http;

/**
 * Was der Client vom Netz braucht: eine Anfrage hinaus, eine Antwort
 * zurueck. Eigene Umsetzungen (Guzzle, WordPress `wp_remote_request`,
 * ein Test-Double) implementieren nur das.
 *
 * Wirft bei Netzfehlern eine `Reputify\Exception\ConnectionException`;
 * HTTP-Fehlerstatus sind keine Ausnahme, sondern eine Antwort.
 */
interface Transport
{
    /** @param array<string, string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response;
}
