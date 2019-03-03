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

/**
 * All the Tile classes and related classes
 */

namespace pocketmine\tile;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use function get_class;

abstract class Tile extends Position{

	public const TAG_ID = "id";
	public const TAG_X = "x";
	public const TAG_Y = "y";
	public const TAG_Z = "z";

	/** @var string */
	public $name = "";
	/** @var bool */
	public $closed = false;
	/** @var TimingsHandler */
	protected $timings;

	public static function init(){
		self::registerTile(Banner::class, [self::BANNER, "minecraft:banner"]);
		self::registerTile(Bed::class, [self::BED, "minecraft:bed"]);
		self::registerTile(Chest::class, [self::CHEST, "minecraft:chest"]);
		self::registerTile(EnchantTable::class, [self::ENCHANT_TABLE, "minecraft:enchanting_table"]);
		self::registerTile(EnderChest::class, [self::ENDER_CHEST, "minecraft:ender_chest"]);
		self::registerTile(FlowerPot::class, [self::FLOWER_POT, "minecraft:flower_pot"]);
		self::registerTile(Furnace::class, [self::FURNACE, "minecraft:furnace"]);
		self::registerTile(ItemFrame::class, [self::ITEM_FRAME]); //this is an entity in PC
		self::registerTile(Sign::class, [self::SIGN, "minecraft:sign"]);
		self::registerTile(Skull::class, [self::SKULL, "minecraft:skull"]);
		self::registerTile(MobSpawner::class);
		
	}

	/**
	 * @param string      $type
	 * @param Level       $level
	 * @param CompoundTag $nbt
	 * @param             $args
	 *
	 * @return Tile|null
	 */
	public static function createTile($type, Level $level, CompoundTag $nbt, ...$args) : ?Tile{
		if(isset(self::$knownTiles[$type])){
			$class = self::$knownTiles[$type];
			return new $class($level, $nbt, ...$args);
		}

		return null;
	}

	/**
	 * @param       $className
	 * @param array $saveNames
	 *
	 * @return bool
	 * @throws \ReflectionException
	 */
	public static function registerTile($className, array $saveNames = []) : bool{
		$class = new \ReflectionClass($className);
		if(is_a($className, Tile::class, true) and !$class->isAbstract()){
			$shortName = $class->getShortName();
			if(!in_array($shortName, $saveNames, true)){
				$saveNames[] = $shortName;
			}

			foreach($saveNames as $name){
				self::$knownTiles[$name] = $className;
			}

			self::$saveNames[$className] = $saveNames;


			return true;
		}

		return false;
	}

	/**
	 * Returns the short save name
	 * @return string
	 */
	public static function getSaveId() : string{
		if(!isset(self::$saveNames[static::class])){
			throw new \InvalidStateException("Tile is not registered");
		}

		reset(self::$saveNames[static::class]);
		return current(self::$saveNames[static::class]);
	}

	public function __construct(Level $level, Vector3 $pos){
		$this->timings = Timings::getTileEntityTimings($this);
		parent::__construct($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $level);
	}

	/**
	 * @internal
	 * Reads additional data from the CompoundTag on tile creation.
	 *
	 * @param CompoundTag $nbt
	 */
	abstract public function readSaveData(CompoundTag $nbt) : void;

	/**
	 * Writes additional save data to a CompoundTag, not including generic things like ID and coordinates.
	 *
	 * @param CompoundTag $nbt
	 */
	abstract protected function writeSaveData(CompoundTag $nbt) : void;

	public function saveNBT() : CompoundTag{
		$nbt = new CompoundTag();
		$nbt->setString(self::TAG_ID, TileFactory::getSaveId(get_class($this)));
		$nbt->setInt(self::TAG_X, $this->x);
		$nbt->setInt(self::TAG_Y, $this->y);
		$nbt->setInt(self::TAG_Z, $this->z);
		$this->writeSaveData($nbt);

		return $nbt;
	}

	public function getCleanedNBT() : ?CompoundTag{
		$this->writeSaveData($tag = new CompoundTag());
		return $tag->getCount() > 0 ? $tag : null;
	}

	/**
	 * @internal
	 *
	 * @param Item $item
	 *
	 * @throws \RuntimeException
	 */
	public function copyDataFromItem(Item $item) : void{
		if($item->hasCustomBlockData()){ //TODO: check item root tag (MCPE doesn't use BlockEntityTag)
			$this->readSaveData($item->getCustomBlockData());
		}
	}

	/**
	 * @return Block
	 */
	public function getBlock() : Block{
		return $this->level->getBlockAt($this->x, $this->y, $this->z);
	}

	/**
	 * @return bool
	 */
	public function onUpdate() : bool{
		return false;
	}

	final public function scheduleUpdate() : void{
		if($this->closed){
			throw new \InvalidStateException("Cannot schedule update on garbage tile " . get_class($this));
		}
		$this->level->updateTiles[Level::blockHash($this->x, $this->y, $this->z)] = $this;
	}

	public function isClosed() : bool{
		return $this->closed;
	}

	public function __destruct(){
		$this->close();
	}

	/**
	 * Called when the tile's block is destroyed.
	 */
	final public function onBlockDestroyed() : void{
		$this->onBlockDestroyedHook();
		$this->close();
	}

	/**
	 * Override this method to do actions you need to do when this tile is destroyed due to block being broken.
	 */
	protected function onBlockDestroyedHook() : void{

	}

	public function close() : void{
		if(!$this->closed){
			$this->closed = true;

			if($this->isValid()){
				$this->level->removeTile($this);
				$this->setLevel(null);
			}
		}
	}

	public function getName() : string{
		return $this->name;
	}
}
