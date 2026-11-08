<?php

declare(strict_types=1);

namespace App\Blog\Domain;

use App\Shared\Domain\Image;

final readonly class Article
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $excerpt,
        public \DateTimeImmutable $publishedAt,
        public ?Image $image,
        public ?ArticleTerm $section,
        public ?ArticleTerm $format,
        public ?ArticleAudio $audio,
        public string $speaker,
    ) {
    }
}
