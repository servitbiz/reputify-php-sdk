<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** 429 — die Grenze des Tarifs je Minute ist erreicht (auch nach den Wiederholungen). */
class RateLimitException extends ApiException
{
    /** Sekunden bis zum naechsten Versuch, laut `Retry-After`. */
    public function retryAfter(): ?int
    {
        $value = $this->response?->header('Retry-After');

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }
}
