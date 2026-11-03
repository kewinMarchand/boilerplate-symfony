<?php

declare(strict_types=1);

namespace App\Content\Infrastructure\Http;

use App\Content\Domain\Slide;
use App\Shared\Infrastructure\Http\ApiPayload;

final class SlideTranslation
{
    /**
     * @return list<Slide>
     */
    public static function slides(mixed $payload): array
    {
        return array_map(static fn (array $slide): Slide => new Slide(
            'slide-'.ApiPayload::int($slide, 'image'),
            ApiPayload::string($slide, 'title'),
            ApiPayload::string($slide, 'text'),
        ), ApiPayload::objects($payload, 'slides'));
    }
}
