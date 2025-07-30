<?php

declare(strict_types=1);

namespace devil\football\util;

use devil\football\Football;
use pocketmine\entity\Skin;
use JsonException;
use function chr;
use function file_get_contents;
use function getimagesize;
use function imagecolorat;
use function imagecreatefrompng;
use function imagedestroy;

class FootballSkin {
    private static ?Skin $skin = null;

    /**
     * Returns the football skin, generating it if not already cached.
     *
     * @throws JsonException
     */
    public static function get(): Skin {
        if (self::$skin instanceof Skin) {
            return self::$skin;
        }

        $plugin = Football::getInstance();
        $dataFolder = $plugin->getDataFolder();

        $imgPath = $dataFolder . "football.png";
        $jsonPath = $dataFolder . "football.json";

        $img = imagecreatefrompng($imgPath);
        $skinBytes = "";

        $height = (int)(@getimagesize($imgPath)[1] ?? 0);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < 64; ++$x) {
                $pixel = @imagecolorat($img, $x, $y);
                $a = ((~($pixel >> 24)) << 1) & 0xFF;
                $r = ($pixel >> 16) & 0xFF;
                $g = ($pixel >> 8) & 0xFF;
                $b = $pixel & 0xFF;
                $skinBytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        @imagedestroy($img);

        self::$skin = new Skin(
            "Football",
            $skinBytes,
            "",
            "geometry.football",
            file_get_contents($jsonPath)
        );

        return self::get();
    }
}
