<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

declare(strict_types=1);
namespace pocketmine\entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;

abstract class Monster extends Mob {
	
	public $drag = 0.2;
	public $gravity = 0.3;
	
	protected $hated = false; 
	protected $tempTicker = 0;
	protected $tempTicking = false; 
	protected $moveTicker = 0;
	protected $hate_r = 16; 
	protected $attack_r = 1.5; 
	protected $fire_r = 1.3; 
	protected $hateTicker = 0; 
	
	public function attack(EntityDamageEvent $source){
		parent::attack($source);
		if($source instanceof EntityDamageByEntityEvent){
			$e = $source->getDamager();
			if ($e != null) {	
				$deltaX = $this->x - $e->x;
				$deltaZ = $this->z - $e->z;
				$this->knockBack($e, $source->getDamage(), $deltaX / 100, $deltaZ / 100, $source->getKnockBack());
			}
		}
	}
	
	private function generateRandomDirection(){
		return new Vector3(mt_rand(-1000, 1000) / 1000, 0, mt_rand(-1000, 1000) / 1000);
	}
	
	/*
	 * 返回一个一位小数
	 */
	private function toFloat($num){
		while(((abs($num) > 1) or (abs($num) < 0.1)) and (abs($num) > 0)) {
			if(abs($num) > 1) $num /= 10;
			if(abs($num) < 0.1) $num *= 10;
		}
		return $num;
	}
	
	private function generateDirection(Vector3 $pos){
		return new Vector3($this->toFloat($pos->x - $this->x), 0, $this->toFloat($pos->z - $this->z));
	}
	
	private function getNearestPlayer(){
		$dis = PHP_INT_MAX;
		$player = false;
		foreach($this->getViewers() as $p){
			if ($p->isAlive()) {
				$myDis = $p->distance($this);
				$myName = $p->getName();
				if($p->distance($this) < $dis){
					$dis = $p->distance($this);
					$player = $p;
				}
			}
		}
		return (($dis <= $this->hate_r) ? $player : false);
	}
	
	private function getVelY(){
		$expectedPos = (new Vector3($this->x + $this->moveDirection->x * $this->moveSpeed, $this->y + $this->motionY, $this->z + $this->moveDirection->z * $this->moveSpeed))->round();
		$block0 = $this->getLevel()->getBlock($expectedPos);
		$block1 = $this->getLevel()->getBlock($expectedPos->add(0, 1, 0));
		if($block1->getId() != 0) return 1.2;
		return 0;
	}
	
	public function onUpdate(int $currentTick) : bool {
		if($this->closed !== false){
			return false;
		}
		
		if ($this->aiControl === true) {
			return parent::onUpdate($currentTick);
		}
	
		$this->lastUpdate = $currentTick;
	
		$this->timings->startTiming();
	
		$hasUpdate = parent::onUpdate($currentTick);
	
		if($this->isAlive()){
// 			$this->kill();   //Uncomment to kill all the mobs
			/* Don't use time directly
			 * Instead, get remainder of current time divided by 24,000
			 * This tells us the time of day, which is what we really need
			 */
			$timeOfDay = abs($this->getLevel()->getTime() % 24000);
			if(0 < $timeOfDay and $timeOfDay < 13000) $this->setOnFire(2); //僵尸起火
				
			$p = $this->getNearestPlayer();//找到最近的可以被仇恨的玩家
			if(!$p) {
				$this->hated = false;
				if(++$this->moveTicker >= 100) {
					$this->moveDirection = $this->generateRandomDirection();
					$this->moveTicker = 0;
				}
			}else{
				$myName = $p->getName();
				$this->hated = $p;
				if($p->distance($this) <= $this->fire_r) $p->setOnFire(2); //点燃玩家
				if(!$this->tempTicking){
					if(++$this->hateTicker >= 10 or $this->moveDirection == null) { //每0.5秒获取僵尸前进的新方向
						$this->moveDirection = $this->generateDirection($p);
						$this->hateTicker = 0;
					}
				}
			}
				
				
			if($this->tempTicking) { //帮助僵尸寻找新的方向走出困境
				if(++$this->tempTicker >= 20) {
					$this->tempTicking = false;
					$this->tempTicker = 0;
				}
			}
				
			if($this->hated instanceof Player){ //攻击玩家
				if($this->hated->distance($this) < $this->attack_r) {
					$this->hated->attack(new EntityDamageByEntityEvent($this, $this->hated, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 2));
				}
			}
				
			if($this->moveDirection != null){
				if($this->motionX ** 2 + $this->motionZ ** 2 <= $this->moveDirection->lengthSquared()){
					$motionY = $this->getVelY(); //僵尸运动计算
					if($motionY >= 0){
						$this->motionX = $this->moveDirection->x * $this->moveSpeed;
						$this->motionZ = $this->moveDirection->z * $this->moveSpeed;
						$this->motionY = $motionY;
					}else{
						$this->moveDirection = $this->generateRandomDirection(); //生成随机运动方向
						$this->moveTicker = 0;
						$this->tempTicking = true;
					}
				}
			}else{
				$this->moveDirection = $this->generateRandomDirection();
				$this->moveTicker = 0;
			}
				
			//var_dump($this->moveDirection,$this->motionX,$this->motionZ);
				
			$expectedPos = new Vector3($this->x + $this->motionX, $this->y + $this->motionY, $this->z + $this->motionZ);
				
			if($this->motionY == 0) $this->motionY -= $this->gravity; //重力计算
	
			$this->move($this->motionX, $this->motionY, $this->motionZ);
	
			if($expectedPos->distanceSquared($this) > 0){
				$this->moveDirection = $this->generateRandomDirection();
			}
				
			$friction = 1 - $this->drag;
	
			$this->motionX *= $friction;
			//$this->motionY *= 1 - $this->drag;
			$this->motionZ *= $friction;
	
			$f = sqrt(($this->motionX ** 2) + ($this->motionZ ** 2));
			$this->yaw = (-atan2($this->motionX, $this->motionZ) * 180 / M_PI); //视角计算
			//$this->pitch = (-atan2($f, $this->motionY) * 180 / M_PI);
				
			$this->updateMovement();
		}
	
		$this->timings->stopTiming();
	
		return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
	}
	
	public function getDrops(): array{
		$lootingL = 0;
		$cause = $this->lastDamageCause;
		$drops = [];
		if($cause instanceof EntityDamageByEntityEvent and $cause->getDamager() instanceof Player){
			$lootingL = $this->getItemInHandLootingEnchantmentLevel($cause);
			
			if(mt_rand(0, 199) < (5 + 2 * $lootingL)){
				switch(mt_rand(0, 3)){
					case 0:
						$drops[] = ItemItem::get(ItemItem::IRON_INGOT, 0, 1);
						break;
					case 1:
						$drops[] = ItemItem::get(ItemItem::CARROT, 0, 1);
						break;
					case 2:
						$drops[] = ItemItem::get(ItemItem::POTATO, 0, 1);
						break;
				}
			}
			$count = mt_rand(0, 2 + $lootingL);
			if($count > 0){
				$drops[] = ItemItem::get(ItemItem::ROTTEN_FLESH, 0, $count);
			}
		}
	
		return $drops;
	}
	
	function getItemInHandLootingEnchantmentLevel(EntityDamageByEntityEvent $event) : int {
		return $event->getDamager()->getInventory()->getItemInHand()->getEnchantmentLevel(Enchantment::LOOTING);
	}
	
	function equip(int $itemId, Player $spawnedToPlayer) {
		$pk = new MobEquipmentPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->eid = $this->getId();
		$pk->item = new ItemItem($itemId);
		$pk->slot = 0;
		$pk->inventorySlot = 0;
		$pk->hotbarSlot = 0;
		$pk->selectedSlot = 0;
// 		$pk->windowId = ContainerIds::INVENTORY;		
		$spawnedToPlayer->dataPacket($pk);
	}
	
	function getHateRadius() : int {
		return 5;
	}
}