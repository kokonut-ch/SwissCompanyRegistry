<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Contracts;

/**
 * Base contract every registry provider implements. What a provider can
 * actually do is expressed through the capability contracts extending
 * this one; the manager routes each call to the first provider in the
 * configured chain that implements the required capability.
 */
interface RegistryProvider
{
    /** Stable machine key, e.g. "zefix". Matches the config key. */
    public function name(): string;
}
