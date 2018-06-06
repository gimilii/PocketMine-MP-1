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
use pocketmine\block\Air;
use pocketmine\block\Obsidian;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageEvent;

class Ghast extends Monster {
	const NETWORK_ID = 41;

	public $width = 6;
	public $length = 6;
	public $height = 6;
	
	public function getName() : string{
		return "Ghast";
	}

	public function initEntity(){
		$server = $this->getLevel()->getServer();
		$dif = $server->getDifficulty();
		$health = ($dif*150)+50;
		$this->setMaxHealth($health);
		$this->setHealth($health);
		
		$block = new Obsidian();
		$level = $this->getLevel();
		$this->getLevel()->setBlock(new Vector3(168, 71, 257), $block);
		$this->getLevel()->setBlock(new Vector3(168, 72, 257), $block);
		$this->setAIControl(false);
		parent::initEntity();
	}
	
	public function spawnTo(Player $player){
		$this->handleSpawnTo($player, Ghast::NETWORK_ID);
	}
	
	public function kill(){
		$block = new Air();
		$this->getLevel()->setBlock(new Vector3(168, 71, 257), $block);
		$this->getLevel()->setBlock(new Vector3(168, 72, 257), $block);
		parent::kill();
	}
	
	public function attack(EntityDamageEvent $source){
		parent::attack($source);
		$h = $this->getHealth();
		print "Ghast health remaining: $h \n";
// 		if ($h % 100 == 0) {
// 			print "Ghast health remaining: $h \n";
// 		}
	}

}