<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final readonly class ImageSource
{
    public function __construct(
        public string $type,
        public string $srcset,
    ) {
    }
}
