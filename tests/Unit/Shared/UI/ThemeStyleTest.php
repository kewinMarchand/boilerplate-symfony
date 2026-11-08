<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI;

use App\Shared\Domain\SiteTheme;
use App\Shared\UI\ThemeStyle;
use PHPUnit\Framework\TestCase;

final class ThemeStyleTest extends TestCase
{
    public function testRedefinesTheTokensInLightAndInDarkButNeverInEnhancedMode(): void
    {
        $css = ThemeStyle::css(new SiteTheme(
            ['primary' => '#c2410c', 'on-primary' => '#ffffff'],
            ['primary' => '#da8d6d', 'on-primary' => '#0f141d'],
        ));

        self::assertSame(
            ':root:not([data-a11y-mode="enhanced"]){--bui-color-primary:#c2410c;--bui-color-on-primary:#ffffff;}'
            .'[data-color-scheme="dark"]:not([data-a11y-mode="enhanced"]){--bui-color-primary:#da8d6d;--bui-color-on-primary:#0f141d;}'
            .'@media (prefers-color-scheme: dark){[data-color-scheme="auto"]:not([data-a11y-mode="enhanced"]){--bui-color-primary:#da8d6d;--bui-color-on-primary:#0f141d;}}',
            $css,
        );
    }
}
