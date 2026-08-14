<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\SharedModel;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function count;

/**
 * Immutable base for every typed collection this library returns. No API method ever returns a bare
 * `array`: a raw list guarantees nothing about its contents and gives list-level behaviour no home,
 * whereas a typed collection is valid by construction and is where that behaviour lives (e.g.
 * {@see \Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\Dto\KeycloakCredentials::hasSecondFactor()}).
 *
 * Immutable like the DTOs it holds — there is no mutating operation; a derived collection is always a
 * new instance. Subclasses add a typed `fromRawResponse()` named constructor and refine the element
 * type via `@extends KeycloakCollection<TheDto>`.
 *
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 * @internal
 */
abstract class KeycloakCollection implements IteratorAggregate, Countable
{
    /**
     * @param  list<T>  $items
     */
    protected function __construct(
        protected readonly array $items,
    ) {}

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * The items as a plain list — for the rare caller that needs `array_map`/`array_slice` and cannot
     * iterate. Prefer iterating the collection directly.
     *
     * @return list<T>
     */
    public function all(): array
    {
        return $this->items;
    }
}
