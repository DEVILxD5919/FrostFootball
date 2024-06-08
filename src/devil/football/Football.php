<?php

declare(strict_types=1);

namespace devil\football;

use JsonException;
use devil\football\entity\FootballEntity;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\world\World;

class Football extends PluginBase {
    private static Football $instance;

    public static function getInstance(): Football {
        return self::$instance;
    }

    public function onEnable(): void {
        self::$instance = $this;

        $this->saveResource("football.json");
        $this->saveResource("football.png");

        EntityFactory::getInstance()->register(FootballEntity::class, function(World $world, CompoundTag $nbt): FootballEntity {
            return new FootballEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ["Football"]);
    }

    /**
     * @throws JsonException
     */
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if ($cmd->getName() === "football") {
            if (!$sender instanceof Player) {
                $sender->sendMessage("§7» §cYou can only use this command ingame!");
                return true;
            }
            if (!$sender->hasPermission("FrostFootball.use.cmd")) {
                $sender->sendMessage("§7» §cYou do not have permission to use this command!");
                return true;
            }

            switch ($args[0] ?? "") {
                case "spawn":
                    if (!$sender->hasPermission("FrostFootball.spawn.cmd")) {
                        $sender->sendMessage("§7» §cYou do not have permission to spawn footballs!");
                        return true;
                    }
                    for ($count = 1; $count <= (int)($args[1] ?? 1); $count++) {
                        FootballEntity::spawn($sender->getLocation());
                    }
                    $sender->sendMessage("§7» §aYou have spawned a new football!");
                    break;

                case "remove":
                    if (!$sender->hasPermission("FrostFootball.remove.cmd")) {
                        $sender->sendMessage("§7» §cYou do not have permission to remove footballs!");
                        return true;
                    }
                    $count = 0;
                    $world = $sender->getWorld();
                    foreach ($world->getEntities() as $entity) {
                        if (($entity instanceof FootballEntity) && !$entity->isClosed()) {
                            $entity->flagForDespawn();
                            $count++;
                        }
                    }
                    $sender->sendMessage("§7» §aSuccessfully removed §6" . $count . "§a footballs.");
                    break;

                default:
                    $sender->sendMessage($cmd->getUsage());
                    break;
            }
            return true;
        }
        return false;
    }
}
