<?php

declare(strict_types=1);

namespace Reputify\Resources;

use Reputify\Reputify;

/** @internal */
abstract class Resource
{
    public function __construct(protected readonly Reputify $client) {}

    protected static function id(int|string $id): string
    {
        return rawurlencode((string) $id);
    }
}
