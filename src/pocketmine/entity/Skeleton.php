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

use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\network\protocol\MobEquipmentPacket;
use pocketmine\item\Item as ItemItem;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\event\entity\EntityShootBowEvent;


class Skeleton extends Monster implements ProjectileSource{
	const NETWORK_ID = 34;
	
	public $width = 0.6;
	public $length = 0.6;
	public $height = 1.8;

	public $dropExp = [5, 5];
	protected $startAction = -1;
	


	
	private $moveDirection = null; //移动方向
	private $moveSpeed = 0.2; //移动速度
	private $hated = false; //仇恨的玩家
	private $tempTicker = 0;
	private $tempTicking = false; //走出困境计时器
	private $moveTicker = 0; //运动计时器
	private $hate_r = 16; //仇恨半径
	private $attack_r = 1.5; //攻击半径
	private $fire_r = 1.3; //点燃半径
	private $hateTicker = 0; //仇恨计时器

	public function getName() : string{
		return "Skeleton";
	}
	
	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Skeleton::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
		
		$pk = new MobEquipmentPacket();
		$pk->eid = $this->getId();
		$pk->item = new ItemItem(ItemItem::BOW);
		$pk->slot = 0;
		$pk->selectedSlot = 0;

		$player->dataPacket($pk);
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
