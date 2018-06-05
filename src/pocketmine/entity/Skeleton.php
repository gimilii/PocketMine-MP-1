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
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */


namespace pocketmine\entity;


use pocketmine\Player;
use pocketmine\item\Item as ItemItem;

class Skeleton extends Monster implements ProjectileSource{
	const NETWORK_ID = 34;
	
	public $width = 0.6;
	public $length = 0.6;
	public $height = 1.8;

	public $dropExp = [5, 5];
	protected $startAction = -1;
	

	public function getName() : string{
		return "Skeleton";
	}
	
	public function spawnTo(Player $player){
		$this->handleSpawnTo($player, Skeleton::NETWORK_ID);
		
		$this->equip(ItemItem::BOW, $player);
	}
	
	public function canCatchOnFire() {
		return true;
	}
	
// 	public function onUpdate($currentTick){
// 		$this->launchArrow();
// 		parent::onUpdate($currentTick);
// 	}
	
// 	private function launchArrow() {
//         $bow = new ItemItem(262);
// 		$nbt = new CompoundTag("", [
// 				"Pos" => new ListTag("Pos", [
// 						new DoubleTag("", $this->x),
// 						new DoubleTag("", $this->y + $this->getEyeHeight()),
// 						new DoubleTag("", $this->z)
// 				]),
// 				"Motion" => new ListTag("Motion", [
// 						new DoubleTag("", -sin($this->yaw / 180 * M_PI) * cos($this->pitch / 180 * M_PI)),
// 						new DoubleTag("", -sin($this->pitch / 180 * M_PI)),
// 						new DoubleTag("", cos($this->yaw / 180 * M_PI) * cos($this->pitch / 180 * M_PI))
// 				]),
// 				"Rotation" => new ListTag("Rotation", [
// 						new FloatTag("", $this->yaw),
// 						new FloatTag("", $this->pitch)
// 				]),
// 				]);
		
// // 		$diff = ($this->server->getTick() - $this->startAction);
// // 		$p = $diff / 20;
// // 		$f = min((($p ** 2) + $p * 2) / 3, 1) * 2;
// // 		$ev = new EntityShootBowEvent($this, $bow, Entity::createEntity("Arrow", $this->chunk, $nbt, $this, $f == 2 ? true : false), $f);
// 		$ev = new EntityShootBowEvent($this, $bow, Entity::createEntity("Arrow", $this->chunk, $nbt, $this, true), 2);
// // 		if($f < 0.1 or $diff < 5){
// // 			$ev->setCancelled();
// // 		}
		
// 		$this->server->getPluginManager()->callEvent($ev);
		
// 	}
}
