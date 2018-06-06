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

namespace pocketmine\entity;

use pocketmine\Player;

abstract class Mob extends Creature {
	
	protected $moveDirection = null;
	protected $moveSpeed = 0.2;
	public $aiControl = true;
	
	
// 	public function initEntity(){
// 		$this->height = 1.8;
// 		$this->width = 0.6;
// 		$this->length = 0.6;
// 		$this->setMaxHealth(6);
// 		parent::initEntity();
// 	}
	
	public function setAIControl($control) {
		$this->aiControl = $control;
	}
	
// 	public function onUpdate($currentTick){
// 		if($this->closed !== false){
// 			return false;
// 		}
// 		if ($this->aiControl === true) {
// 			return parent::onUpdate($currentTick);
// 		}
	
// 	}
	
	public function canCatchOnFire() {
		return false;
	}
	
	public function getDistanceToMovePerSecond() {
		return 2;
	}
	
	public function spawnTo(Player $player){
		parent::addEntityPacketAndSpawnTo($player, $this::NETWORK_ID);
	}	
}
