<?php

declare(strict_types=1);

namespace App\Content\Infrastructure\Http;

use App\Content\Domain\LegalPage;
use App\Content\Domain\LegalPageSection;
use App\Shared\Infrastructure\Http\ApiPayload;

final class LegalPageTranslation
{
    public static function legalPage(mixed $payload): LegalPage
    {
        $page = ApiPayload::object($payload, 'legalPage');

        return new LegalPage(
            ApiPayload::string($page, 'title'),
            ApiPayload::string($page, 'introduction'),
            array_map(
                static fn (array $section): LegalPageSection => new LegalPageSection(ApiPayload::string($section, 'title'), ApiPayload::string($section, 'html')),
                ApiPayload::objects($page['sections'] ?? null, 'sections'),
            ),
        );
    }
}
