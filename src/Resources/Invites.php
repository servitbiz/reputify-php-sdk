<?php

declare(strict_types=1);

namespace Reputify\Resources;

/**
 * Einen Kunden zur Bewertung einladen — mit einer Umfrage-Marke
 * (`rpf_inv_…`) oder einer Website-Anbindung (`invites:write`).
 * Tarifunabhaengig.
 */
final class Invites extends Resource
{
    /**
     * Dieselbe `order_ref` zweimal legt keine zweite Einladung an; die
     * Antwort sagt dann `status: already_invited`.
     *
     * `product_items`: je Eintrag eine Artikelnummer oder
     * `['ref' => …, 'name' => …, 'url' => …]` — damit filtert ein Widget auf
     * der Produktseite (`data-product`).
     *
     * @param  array{
     *     name: string, email: string, phone?: string, order_ref?: string,
     *     product_items?: list<string|array{ref: string, name?: string, url?: string}>,
     *     delay_hours?: int, survey?: string
     * }  $customer
     * @return array{status: string, recipient_id?: int, scheduled_at?: string, link?: string}
     */
    public function create(array $customer): array
    {
        return $this->client->request('POST', 'v1/invite', [], $customer) ?? [];
    }
}
