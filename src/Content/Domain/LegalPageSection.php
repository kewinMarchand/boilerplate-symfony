<?php

declare(strict_types=1);

namespace App\Content\Domain;

final readonly class LegalPageSection
{
    public function __construct(
        public string $title,
        public string $html,
    ) {
    }
}
