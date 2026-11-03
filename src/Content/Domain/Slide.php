<?php

declare(strict_types=1);

namespace App\Content\Domain;

final readonly class Slide
{
    /**
     * @param string $image préfixe du visuel dans assets/images (ex. slide-1)
     */
    public function __construct(
        public string $image,
        public string $title,
        public string $text,
    ) {
    }
}
