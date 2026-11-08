<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ApiPayload;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use App\Shared\Infrastructure\Http\SiteIdentityTranslation;
use PHPUnit\Framework\TestCase;

final class SiteIdentityTranslationTest extends TestCase
{
    public function testTranslatesTheDefaultIdentityWithoutLogoNorColors(): void
    {
        $identity = SiteIdentityTranslation::identity(self::fixture('site-identity'));

        self::assertSame('Boilerplate', $identity->name);
        self::assertStringStartsWith('Modèle de site', $identity->tagline);
        self::assertNull($identity->logo);
        self::assertSame('auto', $identity->defaultColorScheme);
        self::assertNull($identity->theme);
    }

    public function testTranslatesTheLogo(): void
    {
        $logo = SiteIdentityTranslation::identity(self::fixture('site-identity-logo'))->logo;

        self::assertNotNull($logo);
        self::assertSame([400, 120], [$logo->width, $logo->height]);
        self::assertSame('image/webp', $logo->sources[0]->type);
    }

    public function testTranslatesTheTwelveTokensOfEachColorScheme(): void
    {
        $identity = SiteIdentityTranslation::identity(self::fixture('site-identity-couleurs'));

        self::assertSame('dark', $identity->defaultColorScheme);
        self::assertNotNull($identity->theme);
        self::assertCount(12, $identity->theme->light);
        self::assertSame('#c2410c', $identity->theme->light['primary']);
        self::assertSame('#da8d6d', $identity->theme->dark['primary']);
    }

    public function testRejectsAColorThatIsNotHexadecimal(): void
    {
        $payload = self::fixture('site-identity-couleurs', ['"#c2410c"' => '"red}</style><script>"']);

        $this->expectException(InvalidApiPayloadException::class);
        $this->expectExceptionMessage('theme.light.primary');

        SiteIdentityTranslation::identity($payload);
    }

    public function testRejectsAMissingToken(): void
    {
        $payload = self::fixture('site-identity-couleurs', ['"on-secondary": "#0f141d",' => '']);

        $this->expectException(InvalidApiPayloadException::class);

        SiteIdentityTranslation::identity($payload);
    }

    public function testRejectsAnUnknownColorScheme(): void
    {
        $this->expectException(InvalidApiPayloadException::class);

        SiteIdentityTranslation::identity(self::fixture('site-identity', ['"auto"' => '"sepia"']));
    }

    /**
     * @param array<string, string> $replacements remplacements dans le JSON brut de la fixture
     *
     * @return array<string, mixed>
     */
    private static function fixture(string $name, array $replacements = []): array
    {
        $json = strtr((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/'.$name.'.json'), $replacements);

        return ApiPayload::object(json_decode($json, true, flags: \JSON_THROW_ON_ERROR), $name);
    }
}
