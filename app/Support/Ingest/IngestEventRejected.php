<?php

namespace App\Support\Ingest;

use RuntimeException;

final class IngestEventRejected extends RuntimeException
{
    public function __construct(
        public readonly string $rejectionCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $rejectionCode);
    }
}
