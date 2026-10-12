<?php

declare(strict_types=1);

namespace Reputify\Resources;

/**
 * Einbettbare Bausteine. Rechte: `widgets:read`, zum Anlegen `embeds:write`.
 *
 * Eine Einbettung hat einen eigenen, oeffentlichen Schluessel (`pk_…`) —
 * der gehoert in die Website, **nie** die Zugangsmarke.
 */
final class Embeds extends Resource
{
    public const RENDER_BASE = 'https://cdn.reputify.io/w/v1';

    /**
     * Der Katalog aller Bausteine.
     *
     * @return array<string, mixed>
     */
    public function catalog(?string $area = null, ?string $locale = null): array
    {
        return $this->client->request('GET', 'v1/widgets', ['area' => $area, 'locale' => $locale]) ?? [];
    }

    /** @return array<string, mixed> */
    public function list(int|string $projectId): array
    {
        return $this->client->request('GET', 'v1/projects/'.self::id($projectId).'/embeds') ?? [];
    }

    /**
     * @param  array{variant: string, name?: string, locale?: string, allowed_domains?: list<string>, review_group_id?: int, config?: array<string, mixed>}  $attributes
     * @return array<string, mixed>
     */
    public function create(int|string $projectId, array $attributes): array
    {
        return $this->client->request('POST', 'v1/projects/'.self::id($projectId).'/embeds', [], $attributes) ?? [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(int|string $projectId, int|string $embedId, array $attributes): array
    {
        return $this->client->request('PATCH', 'v1/projects/'.self::id($projectId).'/embeds/'.self::id($embedId), [], $attributes) ?? [];
    }

    /**
     * Die Adresse, unter der eine Einbettung gerendert wird — z. B. fuer
     * ein `<img>`. Ohne Aufruf, ohne Marke.
     *
     * @param  string  $ext  `svg`, `png`, `html` oder `json`
     * @param  array{locale?: string, theme?: string, loc?: int|string, product?: string, reviews?: int, platforms?: string}  $query
     */
    public static function renderUrl(string $publicKey, string $variant, string $ext = 'svg', array $query = []): string
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        return self::RENDER_BASE.'/'.rawurlencode($publicKey).'/'.rawurlencode($variant).'.'.$ext
            .($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }
}
