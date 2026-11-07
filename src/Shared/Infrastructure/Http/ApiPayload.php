<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Image;
use App\Shared\Domain\ImageSource;

/**
 * Lecture typée d'une réponse JSON de l'API : chaque accès vérifie la forme attendue et lève
 * InvalidApiPayloadException sinon. Les traductions des modules ne manipulent jamais de mixed brut.
 */
final class ApiPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function object(mixed $value, string $path): array
    {
        if (!\is_array($value) || array_is_list($value) && [] !== $value) {
            throw new InvalidApiPayloadException($path, 'un objet');
        }

        $object = [];
        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function objects(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidApiPayloadException($path, 'une liste');
        }

        return array_map(static fn (mixed $item): array => self::object($item, $path.'[]'), $value);
    }

    /**
     * @param array<string, mixed> $object
     */
    public static function string(array $object, string $key): string
    {
        $value = $object[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidApiPayloadException($key, 'une chaîne');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     */
    public static function int(array $object, string $key): int
    {
        $value = $object[$key] ?? null;
        if (!\is_int($value)) {
            throw new InvalidApiPayloadException($key, 'un entier');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     */
    public static function bool(array $object, string $key): bool
    {
        $value = $object[$key] ?? null;
        if (!\is_bool($value)) {
            throw new InvalidApiPayloadException($key, 'un booléen');
        }

        return $value;
    }

    /**
     * Identifiant entier ou chaîne, rendu en chaîne comme dans le domaine.
     *
     * @param array<string, mixed> $object
     */
    public static function id(array $object, string $key): string
    {
        $value = $object[$key] ?? null;
        if (!\is_int($value) && !\is_string($value)) {
            throw new InvalidApiPayloadException($key, 'un identifiant');
        }

        return (string) $value;
    }

    /**
     * Objet `image` du contrat, ou null quand l'API n'en a pas.
     *
     * @param array<string, mixed> $object
     */
    public static function image(array $object, string $key): ?Image
    {
        if (null === ($object[$key] ?? null)) {
            return null;
        }
        $image = self::object($object[$key], $key);

        return new Image(
            self::string($image, 'alt'),
            self::int($image, 'width'),
            self::int($image, 'height'),
            array_map(
                static fn (array $source): ImageSource => new ImageSource(self::string($source, 'type'), self::string($source, 'srcset')),
                self::objects($image['sources'] ?? null, $key.'.sources'),
            ),
            self::string($image, 'src'),
        );
    }
}
