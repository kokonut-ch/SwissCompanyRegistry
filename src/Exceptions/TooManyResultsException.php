<?php

declare(strict_types=1);

namespace Kokonut\SwissCompanyRegistry\Exceptions;

/**
 * The registry refused to answer because the query would match too many
 * records. The user should narrow the search term or add filters.
 */
class TooManyResultsException extends SwissCompanyRegistryException {}
