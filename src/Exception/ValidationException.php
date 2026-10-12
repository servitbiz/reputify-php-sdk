<?php

declare(strict_types=1);

namespace Reputify\Exception;

/** 422 — Eingaben abgelehnt; `errors()` nennt sie je Feld. */
class ValidationException extends ApiException
{
    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $errors = $this->body['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }
}
