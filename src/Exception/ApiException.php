<?php

declare(strict_types=1);

namespace Reputify\Exception;

use Reputify\Http\Response;

/**
 * Die API hat mit einem Fehler geantwortet.
 *
 * Jede Fehlerantwort traegt `error` (fuer die Maschine) und `message`
 * (fuer den Menschen); `errorCode` ist das erste — darauf wird verzweigt,
 * nicht auf den Text.
 */
class ApiException extends ReputifyException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly array $body,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json() ?? [];
        $code = match (true) {
            is_string($body['error'] ?? null) => $body['error'],
            isset($body['errors']) => 'validation_failed',
            default => 'http_'.$response->status,
        };
        $message = is_string($body['message'] ?? null) ? $body['message'] : 'Reputify API error ('.$response->status.').';

        return match (true) {
            $response->status === 401 => new AuthenticationException($message, $response->status, $code, $body, $response),
            $response->status === 403 => new PermissionException($message, $response->status, $code, $body, $response),
            $response->status === 404 => new NotFoundException($message, $response->status, $code, $body, $response),
            $response->status === 409 => new ConflictException($message, $response->status, $code, $body, $response),
            $response->status === 422 => new ValidationException($message, $response->status, $code, $body, $response),
            $response->status === 429 => new RateLimitException($message, $response->status, $code, $body, $response),
            $response->status >= 500 => new ServerException($message, $response->status, $code, $body, $response),
            default => new self($message, $response->status, $code, $body, $response),
        };
    }
}
