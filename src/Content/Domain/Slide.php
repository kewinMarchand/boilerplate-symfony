<?php

declare(strict_types=1);

namespace App\Content\Domain;

use App\Shared\Domain\Image;

final readonly class Slide
{
    public function __construct(
        public ?Image $image,
        public string $title,
        public string $text,
    ) {
    }
}
