<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\PageMeta;

final class PageMetaTranslation
{
    /**
     * @return array<string, PageMeta>
     */
    public static function pageMetas(mixed $payload): array
    {
        $metas = [];
        foreach (ApiPayload::objects($payload, 'pageMetas') as $meta) {
            $metas[ApiPayload::string($meta, 'page')] = new PageMeta(ApiPayload::string($meta, 'title'), ApiPayload::string($meta, 'description'));
        }

        return $metas;
    }
}
