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
use pocketmine\network\protocol\AddEntityPacket;

class Witch extends Monster{
	const NETWORK_ID = 45;
	
	public $dropExp = [5, 5];
	
	public function getName() : string{
		return "Witch";
	}
	
	public function initEntity(){
		$this->setMaxHealth(26);
		parent::initEntity();
	}
	
	public function spawnTo(Player $player){
		$this->handleSpawnTo($player, self::NETWORK_ID);
	}
	
	public function getDrops(){
		//TODO
		return [];
	}
}