<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI\Locale;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Les fichiers de traduction de chaque langue livrée ont exactement les mêmes clés (section 29 bis de la spec commune).
 */
final class TranslationFilesTest extends TestCase
{
    private const string TRANSLATIONS = __DIR__.'/../../../../../translations/';

    /**
     * @return iterable<string, array{string}>
     */
    public static function domains(): iterable
    {
        yield 'messages' => ['messages'];
        yield 'validators' => ['validators'];
    }

    #[DataProvider('domains')]
    public function testFrenchAndEnglishHaveTheSameKeys(string $domain): void
    {
        $french = self::keys($domain, 'fr');

        self::assertNotEmpty($french);
        self::assertSame($french, self::keys($domain, 'en'));
    }

    /**
     * @return list<string>
     */
    private static function keys(string $domain, string $locale): array
    {
        $keys = self::flatten(Yaml::parseFile(self::TRANSLATIONS.$domain.'.'.$locale.'.yaml'));
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private static function flatten(mixed $node, string $prefix = ''): array
    {
        if (!\is_array($node)) {
            return [rtrim($prefix, '.')];
        }

        $keys = [];
        foreach ($node as $key => $value) {
            $keys = [...$keys, ...self::flatten($value, $prefix.$key.'.')];
        }

        return $keys;
    }
}
