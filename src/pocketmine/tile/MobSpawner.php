<?php

/*
 *
 *  _____   _____   __   _   _   _____  __    __  _____
 * /  ___| | ____| |  \ | | | | /  ___/ \ \  / / /  ___/
 * | |     | |__   |   \| | | | | |___   \ \/ /  | |___
 * | |  _  |  __|  | |\   | | | \___  \   \  /   \___  \
 * | |_| | | |___  | | \  | | |  ___| |   / /     ___| |
 * \_____/ |_____| |_|  \_| |_| /_____/  /_/     /_____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */

namespace pocketmine\tile;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityGenerateEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\level\Position;

class MobSpawner extends Spawnable{
	
	public function __construct(Level $level, CompoundTag $nbt){
		parent::__construct($level, $nbt);
				
		if(!$nbt->hasTag("EntityId", IntTag::class)) {
			$nbt->setInt("EntityId", 0);
		}
		if(!$nbt->hasTag("SpawnCount", IntTag::class)) {
			$nbt->setInt("SpawnCount", 4);
		}
		if(!$nbt->hasTag("SpawnRange", IntTag::class)) {
			$nbt->setInt("SpawnRange", 4);
		}
		if(!$nbt->hasTag("MinSpawnDelay", IntTag::class)) {
			$nbt->setInt("MinSpawnDelay", 200);
		}
		if(!$nbt->hasTag("MaxSpawnDelay", IntTag::class)) {
			$nbt->setInt("MaxSpawnDelay", 799);
		}
		if(!$nbt->hasTag("Delay", IntTag::class)) {
			$nbt->setInt("Delay",  mt_rand($nbt->getInt("MinSpawnDelay"), $nbt->getInt("MaxSpawnDelay")));
		}
		if($this->getEntityId() > 0){
			$this->scheduleUpdate();
		}
	}

	public function getEntityId(){
		return $this->namedtag["EntityId"];
	}

	public function setEntityId(int $id){
		$this->namedtag->setInt("EntityId", $id);
		
		$this->spawnToAll();
		// 2018-03-24 Commented out since was causing an error and think this is now handled by lower level code
		// but not sure.
// 		if($this->chunk instanceof FullChunk){
// 			$this->chunk->setChanged();
// 			$this->level->clearChunkCache($this->chunk->getX(), $this->chunk->getZ());
// 		}
		$this->scheduleUpdate();
	}

	public function getSpawnCount(){
		return $this->namedtag->getInt("SpawnCount");
	}

	public function setSpawnCount(int $value){
		$this->namedtag->setInt("SpawnCount", $value);
	}

	public function getSpawnRange(){
		return $this->namedtag->getInt("SpawnRange");
	}

	public function setSpawnRange(int $value){
		$this->namedtag->setInt("SpawnRange", $value);
	}

	public function getMinSpawnDelay(){
		return $this->namedtag->getInt("MinSpawnDelay");
	}

	public function setMinSpawnDelay(int $value){
		$this->namedtag->setInt("MinSpawnDelay", $value);
	}

	public function getMaxSpawnDelay(){
		return $this->namedtag->getInt("MaxSpawnDelay");
	}

	public function setMaxSpawnDelay(int $value){
		$this->namedtag->setInt("MaxSpawnDelay", $value);
	}

	public function getDelay(){
		return $this->namedtag->getInt("Delay");
	}

	public function setDelay(int $value){
		$this->namedtag->setInt("Delay", $value);
	}

	public function getName() : string{
		return "Monster Spawner";
	}

	public function canUpdate() : bool{
		$id = $this->getEntityId();
		if($this->getEntityId() === 0) return false;;
		$hasPlayer = false;
		$count = 0;
		foreach($this->getLevel()->getEntities() as $e){
			if($e instanceof Player){
				if($e->distance($this->getBlock()) <= 15) $hasPlayer = true;
			}
			if($e::NETWORK_ID == $this->getEntityId()){
				$count++;
			}
		}
		if($hasPlayer and $count < 15){ // Spawn limit = 15
			return true;
		}
		return false;
	}

	public function onUpdate() : bool {
		if($this->closed === true){
			return false;
		}
		$this->timings->startTiming();

// 		if(!($this->chunk instanceof FullChunk)){
// 			return false;
// 		}
		if($this->canUpdate()){
			if($this->getDelay() <= 0){
				$success = 0;
				for($i = 0; $i < $this->getSpawnCount(); $i++){
					$pos = $this->add(mt_rand() / mt_getrandmax() * $this->getSpawnRange(), mt_rand(-1, 1), mt_rand() / mt_getrandmax() * $this->getSpawnRange());
					$newPos = new Position($pos->x, $pos->y, $pos->z, $this->level);
					$target = $this->getLevel()->getBlock($pos);
					$ground = $target->getSide(Vector3::SIDE_DOWN);
					if($target->getId() == Item::AIR){
						$success++;
						$this->getLevel()->getServer()->getPluginManager()->callEvent($ev = new EntityGenerateEvent($newPos, $this->getEntityId(), EntityGenerateEvent::CAUSE_MOB_SPAWNER));
						if(!$ev->isCancelled()){
							$nbt = new CompoundTag("", [
								"Pos" => new ListTag("Pos", [
									new DoubleTag("", $pos->x),
									new DoubleTag("", $pos->y),
									new DoubleTag("", $pos->z)
								]),
								"Motion" => new ListTag("Motion", [
									new DoubleTag("", 0),
									new DoubleTag("", 0),
									new DoubleTag("", 0)
								]),
								"Rotation" => new ListTag("Rotation", [
									new FloatTag("", mt_rand() / mt_getrandmax() * 360),
									new FloatTag("", 0)
								]),
							]);
							$entity = Entity::createEntity($this->getEntityId(), $this->level, $nbt);
							$entity->spawnToAll();
						}
					}
				}
				if($success > 0){
					$this->setDelay(mt_rand($this->getMinSpawnDelay(), $this->getMaxSpawnDelay()));
				}
			}else{
				$this->setDelay($this->getDelay() - 1);
			}
		}

		$this->timings->stopTiming();

		return true;
	}

	// 2018-03-24 Commented this out and replaced with new functions addAdditionalSpawnData and createAdditionalNBT
// 	public function getSpawnCompound(){
// 		$c = new CompoundTag("", [
// 			new StringTag("id", Tile::MOB_SPAWNER),
// 			new IntTag("x", (int) $this->x),
// 			new IntTag("y", (int) $this->y),
// 			new IntTag("z", (int) $this->z),
// 			new IntTag("EntityId", (int) $this->getEntityId())
// 		]);

// 		return $c;
// 	}

	public function addAdditionalSpawnData(CompoundTag $nbt) : void{
		// Nothing to add
	}
	
	protected static function createAdditionalNBT(CompoundTag $nbt, Vector3 $pos, ?int $face = null, ?Item $item = null, ?Player $player = null) : void{
		$nbt->setTag("EntityId", (int) $this->getEntityId());
	}
}
