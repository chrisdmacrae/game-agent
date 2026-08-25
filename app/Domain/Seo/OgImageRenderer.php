<?php

namespace App\Domain\Seo;

use GdImage;
use RuntimeException;

/**
 * Renders 1200x630 Open Graph card PNGs with GD, styled after the site's
 * dark zinc/amber look. Fonts are the Instrument Sans TTFs committed in
 * resources/fonts (converted from the bunny.net WOFF files the frontend uses).
 */
class OgImageRenderer
{
    protected const WIDTH = 1200;

    protected const HEIGHT = 630;

    /**
     * @param  list<string>  $badges
     */
    public function render(string $kicker, string $title, ?string $subtitle, array $badges = []): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $background = imagecolorallocate($image, 9, 9, 11); // zinc-950
        $panel = imagecolorallocate($image, 24, 24, 27); // zinc-900
        $border = imagecolorallocate($image, 39, 39, 42); // zinc-800
        $amber = imagecolorallocate($image, 245, 158, 11); // amber-500
        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 161, 161, 170); // zinc-400
        $badgeText = imagecolorallocate($image, 212, 212, 216); // zinc-300

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $background);

        // Amber top rule and a hairline frame.
        imagefilledrectangle($image, 0, 0, self::WIDTH, 8, $amber);
        imagerectangle($image, 0, 8, self::WIDTH - 1, self::HEIGHT - 1, $border);

        $x = 80;

        // Kicker.
        $this->trackedText($image, 22, $x, 150, $amber, $this->font('SemiBold'), mb_strtoupper($kicker), 6);

        // Title, wrapped to at most two lines with ellipsis.
        $titleLines = $this->wrap($title, 54, $this->font('SemiBold'), self::WIDTH - 2 * $x, 2);
        $y = 250;

        foreach ($titleLines as $line) {
            imagettftext($image, 54, 0, $x, $y, $white, $this->font('SemiBold'), $line);
            $y += 82;
        }

        // Subtitle, wrapped to at most two lines.
        if ($subtitle !== null && $subtitle !== '') {
            $y += 10;

            foreach ($this->wrap($subtitle, 26, $this->font('Regular'), self::WIDTH - 2 * $x, 2) as $line) {
                imagettftext($image, 26, 0, $x, $y, $muted, $this->font('Regular'), $line);
                $y += 44;
            }
        }

        // Badge pills along the bottom.
        $badgeX = $x;

        foreach (array_slice($badges, 0, 4) as $badge) {
            $box = imagettfbbox(20, 0, $this->font('Regular'), $badge);
            $textWidth = abs($box[2] - $box[0]);
            $pillWidth = $textWidth + 48;

            if ($badgeX + $pillWidth > self::WIDTH - $x) {
                break;
            }

            $this->pill($image, $badgeX, 508, $pillWidth, 56, $panel, $border);
            imagettftext($image, 20, 0, $badgeX + 24, 544, $badgeText, $this->font('Regular'), $badge);
            $badgeX += $pillWidth + 16;
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    protected function font(string $variant): string
    {
        $path = resource_path("fonts/InstrumentSans-{$variant}.ttf");

        if (! is_file($path)) {
            throw new RuntimeException("Missing OG image font: {$path}");
        }

        return $path;
    }

    /**
     * Wraps text to fit maxWidth, returning at most maxLines lines and
     * ellipsizing the last line when the text is longer.
     *
     * @return list<string>
     */
    protected function wrap(string $text, int $size, string $font, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $index => $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($this->textWidth($candidate, $size, $font) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current === '') {
                // Single word wider than the line: hard-truncate it.
                $current = $this->ellipsize($word, $size, $font, $maxWidth);

                continue;
            }

            $lines[] = $current;
            $current = $word;

            if (count($lines) === $maxLines - 1) {
                $rest = implode(' ', array_slice($words, $index));
                $lines[] = $this->ellipsize($rest, $size, $font, $maxWidth);

                return $lines;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    protected function ellipsize(string $text, int $size, string $font, int $maxWidth): string
    {
        if ($this->textWidth($text, $size, $font) <= $maxWidth) {
            return $text;
        }

        while ($text !== '' && $this->textWidth("{$text}…", $size, $font) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    protected function textWidth(string $text, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return abs($box[2] - $box[0]);
    }

    /**
     * GD has no letter-spacing, so draw each glyph with a manual advance to
     * approximate the site's tracking-widest kicker style.
     */
    protected function trackedText(GdImage $image, int $size, int $x, int $y, int $color, string $font, string $text, int $tracking): void
    {
        foreach (mb_str_split($text) as $char) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $char);
            $x += $this->textWidth($char === ' ' ? '- -' : $char, $size, $font) + $tracking;

            if ($char === ' ') {
                $x -= $this->textWidth('--', $size, $font);
            }
        }
    }

    protected function pill(GdImage $image, int $x, int $y, int $width, int $height, int $fill, int $border): void
    {
        $radius = intdiv($height, 2);

        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $fill);
        imagefilledellipse($image, $x + $radius, $y + $radius, $height, $height, $fill);
        imagefilledellipse($image, $x + $width - $radius, $y + $radius, $height, $height, $fill);

        imagearc($image, $x + $radius, $y + $radius, $height, $height, 90, 270, $border);
        imagearc($image, $x + $width - $radius, $y + $radius, $height, $height, 270, 90, $border);
        imageline($image, $x + $radius, $y, $x + $width - $radius, $y, $border);
        imageline($image, $x + $radius, $y + $height, $x + $width - $radius, $y + $height, $border);
    }
}
