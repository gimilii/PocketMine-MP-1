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

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use pocketmine\item\Item as ItemItem;

class PigZombie extends Monster{
	const NETWORK_ID = 36;

	public $width = 0.6;
	public $length = 0.6;
	public $height = 1.8;

	public $dropExp = [5, 5];
	
	public function getName() : string{
		return "PigZombie";
	}
	
	public function spawnTo(Player $player){
		$this->handleSpawnTo($player, PigZombie::NETWORK_ID);
		
		$this->equip(ItemItem::GOLD_SWORD, $player);
		
	}

	public function getDrops(){
		$cause = $this->lastDamageCause;
		$drops = [];
		if($cause instanceof EntityDamageByEntityEvent and $cause->getDamager() instanceof Player){
			$lootingL = $this->getItemInHandLootingEnchantmentLevel($cause);
			if(mt_rand(1, 200) <= (5 + 2 * $lootingL)){
				$drops[] = ItemItem::get(ItemItem::GOLD_INGOT, 0, 1);
			}
			$drops[] = ItemItem::get(ItemItem::GOLD_NUGGET, 0, mt_rand(0, 1 + $lootingL));
			$drops[] = ItemItem::get(ItemItem::ROTTEN_FLESH, 0, mt_rand(0, 1 + $lootingL));
		}
		return $drops;
	}
}