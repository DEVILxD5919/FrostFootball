<?php

declare(strict_types=1);

namespace devil\football\entity;

use JsonException;
use devil\football\util\Configuration;
use devil\football\util\FootballSkin;
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

    protected function initEntity(CompoundTag $nbt): void{
        parent::initEntity($nbt);
        $this->setScale(1.5);
    }

    public function onUpdate(int $currentTick): bool{
        $this->handleVertical();
        $this->handleHorizontal();

        foreach($this->getWorld()->getNearbyEntities($this->getBoundingBox()) as $entity) {
            $this->applyEntityCollision($entity);
        }

        if($this->isUnderwater()) {
            $this->motion->x /= 3;
            $this->motion->y = 0.16;
            $this->motion->z /= 3;
        }

        $this->lastY = $this->location->y;
        $update = parent::onUpdate($currentTick);
        if(!$update) {
            if(++$this->timeout > 20) {
                return false;
            }
            return true;
        }
        $this->timeout = 0;
        return true;
    }

    protected function handleVertical(): bool {
        if($this->onGround) {
            if($this->verticalEnergy > Configuration::MIN_VERTICAL_ENERGY) {
                if($this->lastMotionY === 0.0) {
                    $this->lastMotionY = $this->verticalEnergy * Configuration::IMPACT_ENERGY_LOSS;
                }
                $this->motion->y = $this->lastMotionY * Configuration::IMPACT_MOTION_LOSS;
                $this->lastMotionY = $this->motion->y;
            }
            $this->verticalEnergy = 0.0;
        } else {
            $yDiff = $this->lastY - $this->location->y;
            if($yDiff > 0){
                $this->verticalEnergy += $yDiff;
                $this->horizontalEnergy += Configuration::IN_AIR_SPEED_INCREASE;
            }
        }
        return true;
    }

    protected function handleHorizontal(): bool {
        if($this->horizontalEnergy < Configuration::MIN_HORIZONTAL_ENERGY){
            $this->horizontalEnergy = 0.0;
        } else {
            $this->motion->x = (-1 * sin(deg2rad($this->location->yaw))) * $this->horizontalEnergy;
            $this->motion->z = (cos(deg2rad($this->location->yaw)))  * $this->horizontalEnergy;
        }

        $collidingBlocks = $this->getHorizontallyCollidingBlocks();
        if(!empty($collidingBlocks)) {
            $block = null;
            foreach($collidingBlocks as $collidingBlock) {
                if($block === null || $this->location->distanceSquared($collidingBlock->getPosition()) < $this->location->distanceSquared($block->getPosition())) {
                    $block = $collidingBlock;
                }
            }
            $this->handleCollision($block->getPosition());
        }

        if($this->horizontalEnergy > 0.0) {
            $this->horizontalEnergy -= Configuration::DEFAULT_SPEED_LOSS;
        }
        return true;
    }

    public function handleCollision(Position $position): void {
        $xDist = $position->x - $this->location->x;
        $zDist = $position->z - $this->location->z;
        $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;

        while($yaw > 360) {
            $yaw -= 360;
        }
        while($yaw < 0) {
            $yaw += 360;
        }

        //Mhhhhhhhhhhhhhhhhhhhhhhhhhhhhh
        if(($yaw < 45 || $yaw > 315) || ($yaw < 225 && $yaw > 135)) {
            $this->location->yaw = (-1 * $yaw) - 180;
        } else {
            $this->location->yaw = (-1 * $yaw);
        }
    }

    protected function applyEntityCollision(Entity $entity): void {
        if(!$entity instanceof self) {
            return;
        }
        $d0 = $entity->getLocation()->x - $this->location->x;
        $d1 = $entity->getLocation()->z - $this->location->z;
        $d2 = abs(max($d0, $d1));

        if($d2 > 0){
            $d2 = sqrt($d2);
            $d0 /= $d2;
            $d1 /= $d2;
            $d3 = min(1, 1 / $d2);

            $entity->setMotion($entity->getMotion()->add($d0 * $d3 * 0.08, 0, $d1 * $d3 * 0.08));
            $entity->scheduleUpdate();
        }
    }

    public function kick(float $strength = 1.0, float $y = 0.6): void {
        $this->verticalEnergy = 0.0;
        $this->lastMotionY = 0.0;
        $this->horizontalEnergy = $strength;
        $this->motion->y = $y;
    }

    public function onCollideWithPlayer(Player $player): void{
        $location = $player->getLocation();
        $vector3 = $location->asVector3();
        $this->location->yaw = $location->yaw;
        $player->getWorld()->broadcastPacketToViewers($vector3, LevelSoundEventPacket::create(LevelSoundEvent::ITEM_SHIELD_BLOCK, $vector3, 0, ":", false, true, 0));
        $this->kick(match (true) {
            $player->isSneaking() => 0.5,
            $player->isSprinting() => 1.5,
            default => 1.0
        }, match (true) {
            $player->isSneaking() => 0.2,
            $player->isSprinting() => 0.75,
            default => 0.6
        });
    }

    public function getInitialSizeInfo(): EntitySizeInfo{
        return new EntitySizeInfo(0.4, 0.4, 0.4);
    }

    public function attack(EntityDamageEvent $source): void{
        switch($source->getCause()) {
            case EntityDamageEvent::CAUSE_FIRE_TICK: {}
            case EntityDamageEvent::CAUSE_FIRE: {
                $this->flagForDespawn();
                $this->getWorld()->addParticle($this->location, new HugeExplodeParticle());
                $this->getWorld()->broadcastPacketToViewers($this->location, PlaySoundPacket::create("random.explode", $this->location->x, $this->location->y, $this->location->z, 10, 0.3));
                break;
            }
        }
    }

    /**
     * @throws JsonException
     */
    public static function spawn(Location $location): FootballEntity {
        $location->pitch = 0.0;
        $entity = new FootballEntity($location, FootballSkin::get());
        $entity->spawnToAll();
        return $entity;
    }

    /**
     * @return Block[]
     */
    public function getHorizontallyCollidingBlocks(): array {
        if(!$this->isCollidedHorizontally) {
            return [];
        }
        return $this->location->getWorld()->getCollisionBlocks($this->getBoundingBox()->expandedCopy(0.01, 0, 0.01));
    }
}
