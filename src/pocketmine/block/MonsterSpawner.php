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

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\item\TieredTool;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Tile;
use pocketmine\tile\MobSpawner;
use pocketmine\math\Vector3;
use pocketmine\Player;

class MonsterSpawner extends Transparent{

	protected $id = self::MONSTER_SPAWNER;

	public function __construct(int $meta = 0){
		$this->meta = $meta;
	}

	public function getHardness() : float{
		return 5;
	}

	public function getToolType() : int{
		return BlockToolType::TYPE_PICKAXE;
	}

	public function getToolHarvestLevel() : int{
		return TieredTool::TIER_WOODEN;
	}

	public function getName() : string{
		return "Monster Spawner";
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [];
	}

	public function isAffectedBySilkTouch() : bool{
		return false;
	}

	
	public function canBeActivated() : bool {
		return true;
	}
	
	public function onActivate(Item $item, Player $player = null) : bool{
		if($this->getDamage() == 0){
			if($item->getId() == Item::SPAWN_EGG){
				$tile = $this->getLevel()->getTile($this);
				if($tile instanceof MobSpawner){
					$this->meta = $item->getDamage();
					//$this->getLevel()->setBlock($this, $this, true, false);
					$tile->setEntityId($this->meta);
				}
				return true;
			}
		}
		return false;
	}
	
	public function place(Item $item, Block $block, Block $target, int $face, Vector3 $clickVector, Player $player = null) : bool {
		$this->getLevel()->setBlock($block, $this, true, true);
		$nbt = new CompoundTag("", [
				new StringTag("id", Tile::MOB_SPAWNER),
				new IntTag("x", $block->x),
				new IntTag("y", $block->y),
				new IntTag("z", $block->z),
				new IntTag("EntityId", 0),
		]);
		
		if($item->hasCustomBlockData()){
			foreach($item->getCustomBlockData() as $key => $v){
				$nbt->{$key} = $v;
			}
		}
		new MobSpawner($this->getLevel(), $nbt);
		// 		Tile::createTile("MobSpawner", $this->getLevel(), $nbt);
		return true;
  }

	protected function getXpDropAmount() : int{
		return mt_rand(15, 43);
	}
}
