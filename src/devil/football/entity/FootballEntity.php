<?php

declare(strict_types=1);

namespace devil\football\entity;

use devil\football\util\Configuration;
use devil\football\util\FootballSkin;
use JsonException;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\Position;

use function abs;
use function atan2;
use function cos;
use function deg2rad;
use function max;
use function min;
use function sin;
use function sqrt;

class FootballEntity extends Human {
    protected float $verticalEnergy = 0.0;
    protected float $horizontalEnergy = 0.0;
    protected float $lastMotionY = 0.0;
    protected float $lastY = 0.0;
    protected int $timeout = 0;

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->setScale(1.5);
    }

    public function onUpdate(int $currentTick): bool {
        $this->processVertical();
        $this->processHorizontal();

        foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()) as $nearby) {
            $this->handleCollisionWithEntity($nearby);
        }

        if ($this->isUnderwater()) {
            $this->motion->x /= 3;
            $this->motion->y = 0.16;
            $this->motion->z /= 3;
        }

        $this->lastY = $this->location->y;

        $updated = parent::onUpdate($currentTick);
        if (!$updated) {
            if (++$this->timeout > 20) return false;
            return true;
        }

        $this->timeout = 0;
        return true;
    }

    protected function processVertical(): bool {
        if ($this->onGround) {
            if ($this->verticalEnergy > Configuration::MIN_VERTICAL_ENERGY) {
                if ($this->lastMotionY === 0.0) {
                    $this->lastMotionY = $this->verticalEnergy * Configuration::IMPACT_ENERGY_LOSS;
                }
                $this->motion->y = $this->lastMotionY * Configuration::IMPACT_MOTION_LOSS;
                $this->lastMotionY = $this->motion->y;
            }
            $this->verticalEnergy = 0.0;
        } else {
            $fall = $this->lastY - $this->location->y;
            if ($fall > 0) {
                $this->verticalEnergy += $fall;
                $this->horizontalEnergy += Configuration::IN_AIR_SPEED_INCREASE;
            }
        }
        return true;
    }

    protected function processHorizontal(): bool {
        if ($this->horizontalEnergy >= Configuration::MIN_HORIZONTAL_ENERGY) {
            $this->motion->x = -sin(deg2rad($this->location->yaw)) * $this->horizontalEnergy;
            $this->motion->z = cos(deg2rad($this->location->yaw)) * $this->horizontalEnergy;
        } else {
            $this->horizontalEnergy = 0.0;
        }

        $blocks = $this->getHorizontallyCollidingBlocks();
        if ($blocks !== []) {
            $nearest = null;
            foreach ($blocks as $block) {
                if (
                    $nearest === null ||
                    $this->location->distanceSquared($block->getPosition()) <
                    $this->location->distanceSquared($nearest->getPosition())
                ) {
                    $nearest = $block;
                }
            }
            if ($nearest !== null) {
                $this->adjustYawOnCollision($nearest->getPosition());
            }
        }

        if ($this->horizontalEnergy > 0.0) {
            $this->horizontalEnergy -= Configuration::DEFAULT_SPEED_LOSS;
        }

        return true;
    }

    public function adjustYawOnCollision(Position $pos): void {
        $dx = $pos->x - $this->location->x;
        $dz = $pos->z - $this->location->z;
        $yaw = atan2($dz, $dx) * (180 / M_PI) - 90;

        $yaw = fmod($yaw + 360, 360); // Normalize

        if ($yaw < 45 || $yaw > 315 || ($yaw > 135 && $yaw < 225)) {
            $this->location->yaw = -$yaw - 180;
        } else {
            $this->location->yaw = -$yaw;
        }
    }

    protected function handleCollisionWithEntity(Entity $entity): void {
        if (!$entity instanceof self) return;

        $dx = $entity->getLocation()->x - $this->location->x;
        $dz = $entity->getLocation()->z - $this->location->z;
        $magnitude = abs(max($dx, $dz));

        if ($magnitude > 0) {
            $magnitude = sqrt($magnitude);
            $dx /= $magnitude;
            $dz /= $magnitude;

            $scale = min(1, 1 / $magnitude);
            $entity->setMotion($entity->getMotion()->add($dx * $scale * 0.08, 0, $dz * $scale * 0.08));
            $entity->scheduleUpdate();
        }
    }

    public function kick(float $strength = 1.0, float $y = 0.6): void {
        $this->verticalEnergy = 0.0;
        $this->lastMotionY = 0.0;
        $this->horizontalEnergy = $strength;
        $this->motion->y = $y;
    }

    public function onCollideWithPlayer(Player $player): void {
        $pos = $player->getLocation();
        $vector = $pos->asVector3();

        $this->location->yaw = $pos->yaw;

        $player->getWorld()->broadcastPacketToViewers(
            $vector,
            LevelSoundEventPacket::create(LevelSoundEvent::ITEM_SHIELD_BLOCK, $vector, 0, ":", false, true, 0)
        );

        $this->kick(
            match (true) {
                $player->isSneaking() => 0.5,
                $player->isSprinting() => 1.5,
                default => 1.0
            },
            match (true) {
                $player->isSneaking() => 0.2,
                $player->isSprinting() => 0.75,
                default => 0.6
            }
        );
    }

    public function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.4, 0.4, 0.4);
    }

    public function attack(EntityDamageEvent $source): void {
        if (in_array($source->getCause(), [EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK], true)) {
            $this->flagForDespawn();
            $world = $this->getWorld();
            $world->addParticle($this->location, new HugeExplodeParticle());
            $world->broadcastPacketToViewers(
                $this->location,
                PlaySoundPacket::create("random.explode", $this->location->x, $this->location->y, $this->location->z, 10, 0.3)
            );
        }
    }

    /**
     * @throws JsonException
     */
    public static function spawn(Location $location): self {
        $location->pitch = 0.0;
        $ball = new self($location, FootballSkin::get());
        $ball->spawnToAll();
        return $ball;
    }

    /**
     * @return Block[]
     */
    public function getHorizontallyCollidingBlocks(): array {
        if (!$this->isCollidedHorizontally) return [];
        return $this->location->getWorld()->getCollisionBlocks(
            $this->getBoundingBox()->expandedCopy(0.01, 0, 0.01)
        );
    }
}
