<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data\Concerns;

use Duxbo\Seo\Exceptions\InvalidSeoData;

/**
 * Gives an immutable DTO a `with()` copy constructor.
 *
 *     $data->with(title: 'New title');
 *
 * Unlike a `with()` built from optional parameters, this can set a value back
 * to null, and adding a field to the DTO never changes `with()`'s signature.
 */
trait Copyable
{
    /**
     * @param  mixed  ...$changes  Named arguments matching constructor parameters.
     */
    public function with(mixed ...$changes): static
    {
        $current = $this->constructorArgs();

        foreach (array_keys($changes) as $key) {
            if (! is_string($key) || ! array_key_exists($key, $current)) {
                throw InvalidSeoData::unknownAttribute((string) $key, static::class);
            }
        }

        /** @phpstan-ignore-next-line named arguments spread from a string-keyed array */
        return new static(...array_merge($current, $changes));
    }

    /**
     * Constructor-shaped array: keys are parameter names, values current state.
     *
     * Deliberately not called `attributes()` — some DTOs carry a property by
     * that name, and a property/method name clash reads as a bug even though
     * PHP permits it.
     *
     * @return array<string, mixed>
     */
    abstract protected function constructorArgs(): array;
}
