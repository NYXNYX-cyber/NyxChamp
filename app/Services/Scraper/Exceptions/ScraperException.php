<?php

namespace App\Services\Scraper\Exceptions;

use RuntimeException;

/**
 * Base exception untuk domain scraper Laravel-side.
 * ScraperService melempar ini saat scraper service tidak bisa dipanggil
 * atau response tidak valid. Bedanya dengan HTTP exception: ini menandai
 * "gagal dapat data", bukan "gagal request".
 */
class ScraperException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
