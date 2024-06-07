<?php

declare(strict_types=1);

namespace devil\football\command;

use pocketmine\Server;
use devil\football\command\Commands\FootballCommand;

final class CommandManager
{
    public static function initalize(): void
    {
        foreach (self::getCommands() as $key => $value) {
            Server::getInstance()->getCommandMap()->register($key, $value);
        }
    }

    public static function getCommands(): array
    {
        return [
            "football" => new FootballCommand()
        ];
    }
}