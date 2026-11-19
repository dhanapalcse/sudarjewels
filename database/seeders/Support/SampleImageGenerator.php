<?php

namespace Database\Seeders\Support;

/**
 * Generates simple, clearly labelled "SAMPLE IMAGE" jewellery illustrations with GD,
 * so development data never looks like real product photography.
 */
final class SampleImageGenerator
{
    private const BACKGROUNDS = [[250, 249, 246], [232, 241, 237], [244, 239, 230], [238, 243, 242]];

    public static function product(string $kind, int $seed, int $size = 1200): string
    {
        mt_srand($seed);
        $img = imagecreatetruecolor($size, $size);
        imageantialias($img, true);
        [$r, $g, $b] = self::BACKGROUNDS[$seed % count(self::BACKGROUNDS)];
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));

        $gold = imagecolorallocate($img, 198, 167, 108);
        $deep = imagecolorallocate($img, 168, 135, 75);
        $teal = imagecolorallocate($img, 20, 90, 88);
        $pearl = imagecolorallocate($img, 255, 255, 255);
        $c = (int) ($size / 2);
        imagesetthickness($img, (int) ($size * 0.022));

        switch ($kind) {
            case 'earrings':
                foreach ([-1, 1] as $side) {
                    $x = $c + $side * (int) ($size * 0.18);
                    imagearc($img, $x, $c + 40, (int) ($size * 0.26), (int) ($size * 0.30), 0, 360, $gold);
                    imagefilledellipse($img, $x, $c - (int) ($size * 0.13), 34, 34, $deep);
                    if ($seed % 2) {
                        imagefilledellipse($img, $x, $c + (int) ($size * 0.19), 56, 56, $pearl);
                        imageellipse($img, $x, $c + (int) ($size * 0.19), 56, 56, $deep);
                    }
                }
                break;
            case 'necklaces':
                imagearc($img, $c, (int) ($size * 0.25), (int) ($size * 0.7), (int) ($size * 0.8), 20, 160, $gold);
                imagefilledpolygon($img, [$c, (int) ($size * 0.58), $c - 50, (int) ($size * 0.66), $c, (int) ($size * 0.76), $c + 50, (int) ($size * 0.66)], $seed % 2 ? $teal : $deep);
                break;
            case 'rings':
                imageellipse($img, $c, $c + 60, (int) ($size * 0.42), (int) ($size * 0.30), $gold);
                imageellipse($img, $c, $c + 60, (int) ($size * 0.38), (int) ($size * 0.26), $deep);
                imagefilledellipse($img, $c, $c - (int) ($size * 0.11), 110, 110, $seed % 2 ? $teal : $pearl);
                imageellipse($img, $c, $c - (int) ($size * 0.11), 110, 110, $deep);
                break;
            default: // bracelets
                imageellipse($img, $c, $c, (int) ($size * 0.6), (int) ($size * 0.36), $gold);
                for ($i = 0; $i < 12; $i++) {
                    $a = deg2rad($i * 30);
                    imagefilledellipse($img, $c + (int) (cos($a) * $size * 0.3), $c + (int) (sin($a) * $size * 0.18), 30, 30, $i % 3 ? $deep : $pearl);
                }
        }

        self::label($img, 'SAMPLE IMAGE', $size);
        ob_start();
        imagejpeg($img, null, 90);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    public static function hero(int $w = 1800, int $h = 1000): string
    {
        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $col = imagecolorallocate($img, (int) (232 - 40 * $t), (int) (241 - 60 * $t), (int) (237 - 62 * $t));
            imageline($img, 0, $y, $w, $y, $col);
        }
        $gold = imagecolorallocate($img, 198, 167, 108);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagesetthickness($img, 18);
        imagearc($img, (int) ($w * 0.68), (int) ($h * 0.38), 360, 420, 0, 360, $gold);
        imagearc($img, (int) ($w * 0.82), (int) ($h * 0.42), 300, 350, 0, 360, $gold);
        imagefilledellipse($img, (int) ($w * 0.68), (int) ($h * 0.64), 70, 70, $white);
        imagefilledellipse($img, (int) ($w * 0.82), (int) ($h * 0.64), 60, 60, $white);
        self::label($img, 'SAMPLE IMAGE', (int) ($w * 0.6), true);
        ob_start();
        imagejpeg($img, null, 88);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** Large legible label: render with a built-in font, then scale up. */
    private static function label(\GdImage $img, string $text, int $width, bool $topRight = false): void
    {
        $font = 5;
        $tw = imagefontwidth($font) * strlen($text) + 8;
        $th = imagefontheight($font) + 6;
        $small = imagecreatetruecolor($tw, $th);
        imagefill($small, 0, 0, imagecolorallocate($small, 37, 51, 50));
        imagestring($small, $font, 4, 3, $text, imagecolorallocate($small, 250, 249, 246));
        // Bottom-centre: survives square/portrait crops and never sits under storefront badges.
        $scale = max(3, (int) ($width / 260));
        $x = $topRight ? (int) (imagesx($img) * 0.62) : (int) ((imagesx($img) - $tw * $scale) / 2);
        // 82 % down: still visible after a centred 4:3 crop (cards) and inside a circle (categories).
        $y = $topRight ? (int) (imagesy($img) * 0.08) : (int) (imagesy($img) * 0.82) - $th * $scale;
        imagecopyresized($img, $small, $x, $y, 0, 0, $tw * $scale, $th * $scale, $tw, $th);
        imagedestroy($small);
    }
}
