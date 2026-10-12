<?php

declare(strict_types=1);

namespace Reputify;

/**
 * Eine Seite einer Liste: `data`, `meta` (page, per_page, total, pages)
 * und `links` (next, prev).
 *
 * `foreach ($page as $item)` laeuft ueber diese eine Seite;
 * `autoPaging()` holt die folgenden nach, solange es `links.next` gibt
 * (bei Aufnahmen, die keine `links` tragen: solange `total` nicht erreicht ist).
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param  list<array<string, mixed>>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     * @param  (\Closure(int): self)|null  $fetch  Holt eine andere Seite derselben Liste.
     */
    public function __construct(
        public readonly array $data,
        public readonly array $meta = [],
        public readonly array $links = [],
        private readonly ?\Closure $fetch = null,
    ) {}

    /** @param array<string, mixed> $body */
    public static function fromBody(array $body, ?\Closure $fetch = null): self
    {
        return new self(
            array_values((array) ($body['data'] ?? [])),
            (array) ($body['meta'] ?? []),
            (array) ($body['links'] ?? []),
            $fetch,
        );
    }

    public function hasMore(): bool
    {
        if ($this->fetch === null || $this->data === []) {
            return false;
        }

        if (array_key_exists('next', $this->links)) {
            return ! empty($this->links['next']);
        }

        // Aufnahmen tragen keine `links`, nur `current_page`/`per_page`/`total`.
        $perPage = (int) ($this->meta['per_page'] ?? 0);

        return $perPage > 0 && isset($this->meta['total']) && $this->currentPage() * $perPage < (int) $this->meta['total'];
    }

    public function currentPage(): int
    {
        return (int) ($this->meta['page'] ?? $this->meta['current_page'] ?? 1);
    }

    public function next(): ?self
    {
        if (! $this->hasMore()) {
            return null;
        }

        return ($this->fetch)($this->currentPage() + 1);
    }

    public function total(): ?int
    {
        return isset($this->meta['total']) ? (int) $this->meta['total'] : null;
    }

    /**
     * Alle Eintraege ueber alle Seiten — Seite fuer Seite geholt, nicht
     * auf einmal.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function autoPaging(): \Generator
    {
        $page = $this;

        while ($page !== null) {
            foreach ($page->data as $item) {
                yield $item;
            }

            $page = $page->next();
        }
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
