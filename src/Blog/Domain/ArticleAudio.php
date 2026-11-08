<?php

declare(strict_types=1);

namespace App\Blog\Domain;

/**
 * Fichier audio d'un article, servi par l'API (adresse absolue), et sa durée lue à l'envoi.
 */
final readonly class ArticleAudio
{
    public function __construct(
        public string $src,
        public string $type,
        public int $durationSeconds,
    ) {
    }

    /** Durée lisible : « 4 s » sous la minute, « 26 min », « 1 h 05 min ». */
    public function readableDuration(): string
    {
        if ($this->durationSeconds < 60) {
            return $this->durationSeconds.' s';
        }
        $minutes = (int) round($this->durationSeconds / 60);

        return $minutes < 60 ? $minutes.' min' : \sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60);
    }

    /** Durée au format ISO 8601, pour l'attribut datetime de <time>. */
    public function isoDuration(): string
    {
        return 'PT'.$this->durationSeconds.'S';
    }
}
