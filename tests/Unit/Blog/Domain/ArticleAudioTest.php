<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain;

use App\Blog\Domain\ArticleAudio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArticleAudioTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function durations(): iterable
    {
        yield 'secondes' => [4, '4 s'];
        yield 'minute arrondie' => [1570, '26 min'];
        yield 'heures' => [3900, '1 h 05 min'];
    }

    #[DataProvider('durations')]
    public function testReadableDuration(int $seconds, string $expected): void
    {
        self::assertSame($expected, new ArticleAudio('https://api.test/a.mp3', 'audio/mpeg', $seconds)->readableDuration());
    }

    public function testIsoDuration(): void
    {
        self::assertSame('PT1570S', new ArticleAudio('https://api.test/a.mp3', 'audio/mpeg', 1570)->isoDuration());
    }
}
