<?php

namespace App\Exceptions;

use RuntimeException;

class MskImportException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $errorCode,
        public readonly array $context = [],
    ) {
        parent::__construct($errorCode);
    }
}
