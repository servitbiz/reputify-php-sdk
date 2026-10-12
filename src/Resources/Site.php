<?php

declare(strict_types=1);

namespace Reputify\Resources;

/**
 * Fuer Website-Anbindungen (WordPress, WooCommerce, Shopify): eine Marke
 * aus dem Verbinden-Dialog, gebunden an **ein** Projekt. Kein Projekt in
 * der Adresse — die Marke kennt es.
 */
final class Site extends Resource
{
    /** @return array<string, mixed> Das verbundene Projekt. */
    public function get(): array
    {
        return $this->client->request('GET', 'v1/site') ?? [];
    }

    /** @return array<string, mixed> Umfragen, die Einladungen annehmen. */
    public function surveys(): array
    {
        return $this->client->request('GET', 'v1/site/surveys') ?? [];
    }

    /**
     * Was aus den Einladungen dieser Bestellungen wurde (hoechstens 100).
     *
     * @param  list<string>  $orderRefs
     * @return array<string, mixed>
     */
    public function invites(array $orderRefs): array
    {
        return $this->client->request('GET', 'v1/site/invites', ['order_refs' => $orderRefs]) ?? [];
    }

    /** @return array<string, mixed> */
    public function setIndustry(string $industry): array
    {
        return $this->client->request('PUT', 'v1/site/industry', [], ['industry' => $industry]) ?? [];
    }

    /**
     * Ein Einmal-Link ins Dashboard, angemeldet.
     *
     * @param  array{target?: string, platform?: string, return?: string}  $options
     * @return array<string, mixed>
     */
    public function loginLink(array $options = []): array
    {
        return $this->client->request('POST', 'v1/site/login-link', [], $options) ?? [];
    }
}
