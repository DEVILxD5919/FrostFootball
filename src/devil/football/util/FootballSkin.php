<?php

declare(strict_types=1);

namespace devil\football\util;

use JsonException;
use devil\football\Football;
use pocketmine\entity\Skin;
use function file_get_contents;

class FootballSkin {
    protected static ?Skin $skin = null;

    /**
     * @throws JsonException
     */
    public static function get(): Skin {
        if(self::$skin !== null) {
            return self::$skin;
        }
        $instance = Football::getInstance();
        $image = imagecreatefrompng($instance->getDataFolder()."football.png");
        $bytes = "";
        $l = (int) @getimagesize($instance->getDataFolder()."football.png")[1];
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgba = @imagecolorat($image, $x, $y);
                $a = ((~(($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($image);
        self::$skin = new Skin("Football", $bytes, "", "geometry.football", file_get_contents($instance->getDataFolder()."football.json"));
        return self::get();
    }
}