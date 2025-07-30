<?php

declare(strict_types=1);

namespace devil\football;

use devil\football\entity\FootballEntity;
use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\world\World;

class Football extends PluginBase {

    private static Football $instance;

    public static function getInstance(): Football {
        return self::$instance;
    }

    public function onEnable(): void {
        self::$instance = $this;

        foreach (["football.json", "football.png"] as $resource) {
            $this->saveResource($resource);
        }

        EntityFactory::getInstance()->register(
            FootballEntity::class,
            function (World $world, CompoundTag $nbt): FootballEntity {
                $location = EntityDataHelper::parseLocation($nbt, $world);
                $skin = Human::parseSkinNBT($nbt);
                return new FootballEntity($location, $skin, $nbt);
            },
            ["Football"]
        );
    }

    /**
     * @throws JsonException
     */
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if ($cmd->getName() !== "football") {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("§7» §cThis command can only be used by players.");
            return true;
        }

        if (!$sender->hasPermission("FrostFootball.use.cmd")) {
            $sender->sendMessage("§7» §cYou do not have the required permission to use this command.");
            return true;
        }

        $subCommand = $args[0] ?? "";

        if ($subCommand === "spawn") {
            if (!$sender->hasPermission("FrostFootball.spawn.cmd")) {
                $sender->sendMessage("§7» §cYou are not allowed to spawn footballs.");
                return true;
            }

            $times = isset($args[1]) ? (int)$args[1] : 1;

            for ($i = 0; $i < $times; $i++) {
                FootballEntity::spawn($sender->getLocation());
            }

            $sender->sendMessage("§7» §aSuccessfully spawned football(s).");

        } elseif ($subCommand === "remove") {
            if (!$sender->hasPermission("FrostFootball.remove.cmd")) {
                $sender->sendMessage("§7» §cYou are not allowed to remove footballs.");
                return true;
            }

            $removed = 0;
            $world = $sender->getWorld();

            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof FootballEntity && !$entity->isClosed()) {
                    $entity->flagForDespawn();
                    $removed++;
                }
            }

            $sender->sendMessage("§7» §aRemoved §6$removed§a football(s).");

        } else {
            $sender->sendMessage($cmd->getUsage());
        }

        return true;
    }
}
