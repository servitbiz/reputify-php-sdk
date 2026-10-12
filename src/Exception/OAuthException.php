<?php

declare(strict_types=1);

namespace Reputify\Exception;

/**
 * Der Token-Endpunkt hat abgelehnt (`invalid_grant`, …).
 *
 * `invalid_grant` beim Erneuern heisst: die Verbindung ist weg — vom
 * Kunden getrennt, vom Tarif nicht mehr gedeckt oder wegen eines doppelt
 * benutzten Schluessels gesperrt. Neu verbinden, nicht wiederholen.
 */
class OAuthException extends ReputifyException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 400)
    {
        parent::__construct($message, $status);
    }
}
