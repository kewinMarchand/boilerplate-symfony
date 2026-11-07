<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Infrastructure\Http;

use App\Content\Infrastructure\Http\SlideTranslation;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use PHPUnit\Framework\TestCase;

final class SlideTranslationTest extends TestCase
{
    public function testTranslatesTheApiSlidesInOrderWithTheirImage(): void
    {
        $slides = SlideTranslation::slides(json_decode((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/slides.json'), true, flags: \JSON_THROW_ON_ERROR));

        self::assertCount(5, $slides);
        self::assertSame('Heliconia', $slides[0]->title);
        self::assertNotNull($slides[0]->image);
        self::assertSame('Bractées rouges et jaunes d’un heliconia, en gros plan', $slides[0]->image->alt);
        self::assertSame([1280, 720], [$slides[0]->image->width, $slides[0]->image->height]);
        self::assertSame(['image/avif', 'image/webp'], array_map(static fn ($source): string => $source->type, $slides[0]->image->sources));
        self::assertStringStartsWith('http://localhost:8090/media/', $slides[0]->image->src);
    }

    public function testKeepsASlideWithoutImage(): void
    {
        $slides = SlideTranslation::slides([['id' => 1, 'slug' => 's', 'title' => 'T', 'text' => 'X', 'image' => null]]);

        self::assertNull($slides[0]->image);
    }

    public function testRejectsAnImageThatIsNotAnObject(): void
    {
        $this->expectException(InvalidApiPayloadException::class);

        SlideTranslation::slides([['id' => 1, 'slug' => 's', 'title' => 'T', 'text' => 'X', 'image' => 1]]);
    }
}
