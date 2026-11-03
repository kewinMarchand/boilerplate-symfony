<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Infrastructure\Http;

use App\Content\Infrastructure\Http\SlideTranslation;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use PHPUnit\Framework\TestCase;

final class SlideTranslationTest extends TestCase
{
    public function testTranslatesTheApiSlidesInOrderWithTheirVisual(): void
    {
        $slides = SlideTranslation::slides(json_decode((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/slides.json'), true, flags: \JSON_THROW_ON_ERROR));

        self::assertCount(5, $slides);
        self::assertSame('Heliconia', $slides[0]->title);
        self::assertSame('slide-1', $slides[0]->image);
        self::assertSame('slide-5', $slides[4]->image);
    }

    public function testRejectsAVisualThatIsNotANumber(): void
    {
        $this->expectException(InvalidApiPayloadException::class);

        SlideTranslation::slides([['id' => 1, 'slug' => 's', 'title' => 'T', 'text' => 'X', 'image' => 'slide-1']]);
    }
}
