<?php

declare(strict_types=1);

namespace Reputify\Exception;

/**
 * 403 — zwei verschiedene Gruende, am `errorCode` zu unterscheiden:
 * `insufficient_scope` (der Marke fehlt ein Recht, siehe `required()`)
 * und `plan_required` (der Tarif deckt es nicht).
 */
class PermissionException extends ApiException
{
    /** @return list<string> Die Rechte, die gefehlt haben. */
    public function required(): array
    {
        return array_values(array_filter((array) ($this->body['required'] ?? []), 'is_string'));
    }

    public function isPlanRequired(): bool
    {
        return $this->errorCode === 'plan_required';
    }
}
