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
 * All the entity classes
 */
namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\entity\animation\Animation;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use function abs;
use function array_map;
use function assert;
use function cos;
use function count;
use function deg2rad;
use function floor;
use function get_class;
use function is_array;
use function is_infinite;
use function is_nan;
use function lcg_value;
use function sin;
use function spl_object_id;
use const M_PI_2;

abstract class Entity{

	public const MOTION_THRESHOLD = 0.00001;

	/** @var Player[] */
	protected $hasSpawned = [];

	/** @var int */
	protected $id;

	/** @var EntityMetadataCollection */
	private $networkProperties;

	/** @var Chunk|null */
	public $chunk;

	/** @var EntityDamageEvent|null */
	protected $lastDamageCause = null;

	/** @var Block[]|null */
	protected $blocksAround;

	/** @var Location */
	protected $location;
	/** @var Location */
	protected $lastLocation;
	/** @var Vector3 */
	protected $motion;
	/** @var Vector3 */
	protected $lastMotion;
	/** @var bool */
	protected $forceMovementUpdate = false;

	/** @var Vector3 */
	public $temporalVector;

	/** @var AxisAlignedBB */
	public $boundingBox;
	/** @var bool */
	public $onGround = false;

	/** @var float */
	public $eyeHeight = null;

	/** @var float */
	public $height;
	/** @var float */
	public $width;

	/** @var float */
	protected $baseOffset = 0.0;

	/** @var float */
	private $health = 20.0;
	/** @var int */
	private $maxHealth = 20;

	/** @var float */
	protected $stepHeight = 0.0;
	/** @var bool */
	public $keepMovement = false;

	/** @var float */
	public $fallDistance = 0.0;
	/** @var int */
	public $ticksLived = 0;
	/** @var int */
	public $lastUpdate;
	/** @var int */
	protected $fireTicks = 0;
	/** @var bool */
	public $canCollide = true;

	/** @var bool */
	protected $isStatic = false;

	/** @var bool */
	private $savedWithChunk = true;

	/** @var bool */
	public $isCollided = false;
	/** @var bool */
	public $isCollidedHorizontally = false;
	/** @var bool */
	public $isCollidedVertically = false;

	/** @var int */
	public $noDamageTicks = 0;
	/** @var bool */
	protected $justCreated = true;
	/** @var bool */
	private $invulnerable = false;

	/** @var AttributeMap */
	protected $attributeMap;

	/** @var float */
	protected $gravity;
	/** @var float */
	protected $drag;
	/** @var bool */
	protected $gravityEnabled = true;

	/** @var Server */
	protected $server;

	/** @var bool */
	protected $closed = false;
	/** @var bool */
	private $needsDespawn = false;

	/** @var TimingsHandler */
	protected $timings;

	/** @var string */
	protected $nameTag = "";
	/** @var bool */
	protected $nameTagVisible = true;
	/** @var bool */
	protected $alwaysShowNameTag = false;
	/** @var string */
	protected $scoreTag = "";
	/** @var float */
	protected $scale = 1.0;

	/** @var bool */
	protected $canClimb = false;
	/** @var bool */
	protected $canClimbWalls = false;
	/** @var bool */
	protected $immobile = false;
	/** @var bool */
	protected $invisible = false;

	/** @var int|null */
	protected $ownerId = null;
	/** @var int|null */
	protected $targetId = null;

	public function __construct(World $world, CompoundTag $nbt){
		$this->timings = Timings::getEntityTimings($this);

		$this->temporalVector = new Vector3();

		if($this->eyeHeight === null){
			$this->eyeHeight = $this->height / 2 + 0.1;
		}

		$this->id = EntityFactory::nextRuntimeId();
		$this->server = $world->getServer();

		/** @var float[] $pos */
		$pos = $nbt->getListTag("Pos")->getAllValues();
		/** @var float[] $rotation */
		$rotation = $nbt->getListTag("Rotation")->getAllValues();

		$this->location = new Location($pos[0], $pos[1], $pos[2], $rotation[0], $rotation[1], $world);
		assert(
			!is_nan($this->location->x) and !is_infinite($this->location->x) and
			!is_nan($this->location->y) and !is_infinite($this->location->y) and
			!is_nan($this->location->z) and !is_infinite($this->location->z)
		);

		$this->boundingBox = new AxisAlignedBB(0, 0, 0, 0, 0, 0);
		$this->recalculateBoundingBox();

		$this->chunk = $this->getWorld()->getChunkAtPosition($this->location, false);
		if($this->chunk === null){
			throw new \InvalidStateException("Cannot create entities in unloaded chunks");
		}

		$this->motion = new Vector3(0, 0, 0);
		if($nbt->hasTag("Motion", ListTag::class)){
			/** @var float[] $motion */
			$motion = $nbt->getListTag("Motion")->getAllValues();
			$this->setMotion($this->temporalVector->setComponents(...$motion));
		}

		$this->resetLastMovements();

		$this->networkProperties = new EntityMetadataCollection();

		$this->attributeMap = new AttributeMap();
		$this->addAttributes();

		$this->initEntity($nbt);

		$this->chunk->addEntity($this);
		$this->getWorld()->addEntity($this);

		$this->lastUpdate = $this->server->getTick();
		(new EntitySpawnEvent($this))->call();

		$this->scheduleUpdate();

	}

	public function getNameTag() : string{
		return $this->nameTag;
	}

	public function isNameTagVisible() : bool{
		return $this->nameTagVisible;
	}

	public function isNameTagAlwaysVisible() : bool{
		return $this->alwaysShowNameTag;
	}

	public function setNameTag(string $name) : void{
		$this->nameTag = $name;
	}

	public function setNameTagVisible(bool $value = true) : void{
		$this->nameTagVisible = $value;
	}

	public function setNameTagAlwaysVisible(bool $value = true) : void{
		$this->alwaysShowNameTag = $value;
	}

	public function getScoreTag() : ?string{
		return $this->scoreTag; //TODO: maybe this shouldn't be nullable?
	}

	public function setScoreTag(string $score) : void{
		$this->scoreTag = $score;
	}

	public function getScale() : float{
		return $this->scale;
	}

	public function setScale(float $value) : void{
		if($value <= 0){
			throw new \InvalidArgumentException("Scale must be greater than 0");
		}
		$multiplier = $value / $this->getScale();

		$this->width *= $multiplier;
		$this->height *= $multiplier;
		$this->eyeHeight *= $multiplier;

		$this->scale = $value;

		$this->recalculateBoundingBox();
	}

	public function getBoundingBox() : AxisAlignedBB{
		return $this->boundingBox;
	}

	protected function recalculateBoundingBox() : void{
		$halfWidth = $this->width / 2;

		$this->boundingBox = new AxisAlignedBB(
			$this->location->x - $halfWidth,
			$this->location->y,
			$this->location->z - $halfWidth,
			$this->location->x + $halfWidth,
			$this->location->y + $this->height,
			$this->location->z + $halfWidth
		);
	}

	public function isImmobile() : bool{
		return $this->immobile;
	}

	public function setImmobile(bool $value = true) : void{
		$this->immobile = $value;
	}

	public function isInvisible() : bool{
		return $this->invisible;
	}

	public function setInvisible(bool $value = true) : void{
		$this->invisible = $value;
	}

	/**
	 * Returns whether the entity is able to climb blocks such as ladders or vines.
	 */
	public function canClimb() : bool{
		return $this->canClimb;
	}

	/**
	 * Sets whether the entity is able to climb climbable blocks.
	 */
	public function setCanClimb(bool $value = true) : void{
		$this->canClimb = $value;
	}

	/**
	 * Returns whether this entity is climbing a block. By default this is only true if the entity is climbing a ladder or vine or similar block.
	 */
	public function canClimbWalls() : bool{
		return $this->canClimbWalls;
	}

	/**
	 * Sets whether the entity is climbing a block. If true, the entity can climb anything.
	 */
	public function setCanClimbWalls(bool $value = true) : void{
		$this->canClimbWalls = $value;
	}

	/**
	 * Returns the entity ID of the owning entity, or null if the entity doesn't have an owner.
	 */
	public function getOwningEntityId() : ?int{
		return $this->ownerId;
	}

	/**
	 * Returns the owning entity, or null if the entity was not found.
	 */
	public function getOwningEntity() : ?Entity{
		return $this->ownerId !== null ? $this->server->getWorldManager()->findEntity($this->ownerId) : null;
	}

	/**
	 * Sets the owner of the entity. Passing null will remove the current owner.
	 *
	 * @throws \InvalidArgumentException if the supplied entity is not valid
	 */
	public function setOwningEntity(?Entity $owner) : void{
		if($owner === null){
			$this->ownerId = null;
		}elseif($owner->closed){
			throw new \InvalidArgumentException("Supplied owning entity is garbage and cannot be used");
		}else{
			$this->ownerId = $owner->getId();
		}
	}

	/**
	 * Returns the entity ID of the entity's target, or null if it doesn't have a target.
	 */
	public function getTargetEntityId() : ?int{
		return $this->targetId;
	}

	/**
	 * Returns the entity's target entity, or null if not found.
	 * This is used for things like hostile mobs attacking entities, and for fishing rods reeling hit entities in.
	 */
	public function getTargetEntity() : ?Entity{
		return $this->targetId !== null ? $this->server->getWorldManager()->findEntity($this->targetId) : null;
	}

	/**
	 * Sets the entity's target entity. Passing null will remove the current target.
	 *
	 * @throws \InvalidArgumentException if the target entity is not valid
	 */
	public function setTargetEntity(?Entity $target) : void{
		if($target === null){
			$this->targetId = null;
		}elseif($target->closed){
			throw new \InvalidArgumentException("Supplied target entity is garbage and cannot be used");
		}else{
			$this->targetId = $target->getId();
		}
	}

	/**
	 * Returns whether this entity will be saved when its chunk is unloaded.
	 */
	public function canSaveWithChunk() : bool{
		return $this->savedWithChunk;
	}

	/**
	 * Sets whether this entity will be saved when its chunk is unloaded. This can be used to prevent the entity being
	 * saved to disk.
	 */
	public function setCanSaveWithChunk(bool $value) : void{
		$this->savedWithChunk = $value;
	}

	public function saveNBT() : CompoundTag{
		$nbt = new CompoundTag();
		if(!($this instanceof Player)){
			$nbt->setString("id", EntityFactory::getInstance()->getSaveId(get_class($this)));

			if($this->getNameTag() !== ""){
				$nbt->setString("CustomName", $this->getNameTag());
				$nbt->setByte("CustomNameVisible", $this->isNameTagVisible() ? 1 : 0);
			}
		}

		$nbt->setTag("Pos", new ListTag([
			new DoubleTag($this->location->x),
			new DoubleTag($this->location->y),
			new DoubleTag($this->location->z)
		]));

		$nbt->setTag("Motion", new ListTag([
			new DoubleTag($this->motion->x),
			new DoubleTag($this->motion->y),
			new DoubleTag($this->motion->z)
		]));

		$nbt->setTag("Rotation", new ListTag([
			new FloatTag($this->location->yaw),
			new FloatTag($this->location->pitch)
		]));

		$nbt->setFloat("FallDistance", $this->fallDistance);
		$nbt->setShort("Fire", $this->fireTicks);
		$nbt->setByte("OnGround", $this->onGround ? 1 : 0);
		$nbt->setByte("Invulnerable", $this->invulnerable ? 1 : 0);

		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->fireTicks = $nbt->getShort("Fire", 0);

		$this->onGround = $nbt->getByte("OnGround", 0) !== 0;
		$this->invulnerable = $nbt->getByte("Invulnerable", 0) !== 0;

		$this->fallDistance = $nbt->getFloat("FallDistance", 0.0);

		if($nbt->hasTag("CustomName", StringTag::class)){
			$this->setNameTag($nbt->getString("CustomName"));

			if($nbt->hasTag("CustomNameVisible", StringTag::class)){
				//Older versions incorrectly saved this as a string (see 890f72dbf23a77f294169b79590770470041adc4)
				$this->setNameTagVisible($nbt->getString("CustomNameVisible") !== "");
			}else{
				$this->setNameTagVisible($nbt->getByte("CustomNameVisible", 1) !== 0);
			}
		}
	}

	protected function addAttributes() : void{

	}

	public function attack(EntityDamageEvent $source) : void{
		$source->call();
		if($source->isCancelled()){
			return;
		}

		$this->setLastDamageCause($source);

		$this->setHealth($this->getHealth() - $source->getFinalDamage());
	}

	public function heal(EntityRegainHealthEvent $source) : void{
		$source->call();
		if($source->isCancelled()){
			return;
		}

		$this->setHealth($this->getHealth() + $source->getAmount());
	}

	public function kill() : void{
		if($this->isAlive()){
			$this->health = 0;
			$this->onDeath();
			$this->scheduleUpdate();
		}
	}

	/**
	 * Override this to do actions on death.
	 */
	protected function onDeath() : void{

	}

	/**
	 * Called to tick entities while dead. Returns whether the entity should be flagged for despawn yet.
	 */
	protected function onDeathUpdate(int $tickDiff) : bool{
		return true;
	}

	public function isAlive() : bool{
		return $this->health > 0;
	}

	public function getHealth() : float{
		return $this->health;
	}

	/**
	 * Sets the health of the Entity. This won't send any update to the players
	 */
	public function setHealth(float $amount) : void{
		if($amount == $this->health){
			return;
		}

		if($amount <= 0){
			if($this->isAlive()){
				$this->kill();
			}
		}elseif($amount <= $this->getMaxHealth() or $amount < $this->health){
			$this->health = $amount;
		}else{
			$this->health = $this->getMaxHealth();
		}
	}

	public function getMaxHealth() : int{
		return $this->maxHealth;
	}

	public function setMaxHealth(int $amount) : void{
		$this->maxHealth = $amount;
	}

	public function setLastDamageCause(EntityDamageEvent $type) : void{
		$this->lastDamageCause = $type;
	}

	public function getLastDamageCause() : ?EntityDamageEvent{
		return $this->lastDamageCause;
	}

	public function getAttributeMap() : AttributeMap{
		return $this->attributeMap;
	}

	public function getNetworkProperties() : EntityMetadataCollection{
		return $this->networkProperties;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		//TODO: check vehicles

		$this->justCreated = false;

		$changedProperties = $this->getSyncedNetworkData(true);
		if(count($changedProperties) > 0){
			$this->sendData($this->hasSpawned, $changedProperties);
			$this->networkProperties->clearDirtyProperties();
		}

		$hasUpdate = false;

		$this->checkBlockCollision();

		if($this->location->y <= -16 and $this->isAlive()){
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_VOID, 10);
			$this->attack($ev);
			$hasUpdate = true;
		}

		if($this->isOnFire() and $this->doOnFireTick($tickDiff)){
			$hasUpdate = true;
		}

		if($this->noDamageTicks > 0){
			$this->noDamageTicks -= $tickDiff;
			if($this->noDamageTicks < 0){
				$this->noDamageTicks = 0;
			}
		}

		$this->ticksLived += $tickDiff;

		return $hasUpdate;
	}

	public function isOnFire() : bool{
		return $this->fireTicks > 0;
	}

	public function setOnFire(int $seconds) : void{
		$ticks = $seconds * 20;
		if($ticks > $this->getFireTicks()){
			$this->setFireTicks($ticks);
		}
	}

	public function getFireTicks() : int{
		return $this->fireTicks;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function setFireTicks(int $fireTicks) : void{
		if($fireTicks < 0 or $fireTicks > 0x7fff){
			throw new \InvalidArgumentException("Fire ticks must be in range 0 ... " . 0x7fff . ", got $fireTicks");
		}
		$this->fireTicks = $fireTicks;
	}

	public function extinguish() : void{
		$this->fireTicks = 0;
	}

	public function isFireProof() : bool{
		return false;
	}

	protected function doOnFireTick(int $tickDiff = 1) : bool{
		if($this->isFireProof() and $this->fireTicks > 1){
			$this->fireTicks = 1;
		}else{
			$this->fireTicks -= $tickDiff;
		}

		if(($this->fireTicks % 20 === 0) or $tickDiff > 20){
			$this->dealFireDamage();
		}

		if(!$this->isOnFire()){
			$this->extinguish();
		}else{
			return true;
		}

		return false;
	}

	/**
	 * Called to deal damage to entities when they are on fire.
	 */
	protected function dealFireDamage() : void{
		$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1);
		$this->attack($ev);
	}

	public function canCollideWith(Entity $entity) : bool{
		return !$this->justCreated and $entity !== $this;
	}

	public function canBeCollidedWith() : bool{
		return $this->isAlive();
	}

	protected function updateMovement(bool $teleport = false) : void{
		$diffPosition = $this->location->distanceSquared($this->lastLocation);
		$diffRotation = ($this->location->yaw - $this->lastLocation->yaw) ** 2 + ($this->location->pitch - $this->lastLocation->pitch) ** 2;

		$diffMotion = $this->motion->subtractVector($this->lastMotion)->lengthSquared();

		$still = $this->motion->lengthSquared() == 0.0;
		$wasStill = $this->lastMotion->lengthSquared() == 0.0;
		if($wasStill !== $still){
			//TODO: hack for client-side AI interference: prevent client sided movement when motion is 0
			$this->setImmobile($still);
		}

		if($teleport or $diffPosition > 0.0001 or $diffRotation > 1.0 or (!$wasStill and $still)){
			$this->lastLocation = $this->location->asLocation();

			$this->broadcastMovement($teleport);
		}

		if($diffMotion > 0.0025 or $wasStill !== $still){ //0.05 ** 2
			$this->lastMotion = clone $this->motion;

			$this->broadcastMotion();
		}
	}

	public function getOffsetPosition(Vector3 $vector3) : Vector3{
		return new Vector3($vector3->x, $vector3->y + $this->baseOffset, $vector3->z);
	}

	protected function broadcastMovement(bool $teleport = false) : void{
		$pk = new MoveActorAbsolutePacket();
		$pk->entityRuntimeId = $this->id;
		$pk->position = $this->getOffsetPosition($this->location);

		//this looks very odd but is correct as of 1.5.0.7
		//for arrows this is actually x/y/z rotation
		//for mobs x and z are used for pitch and yaw, and y is used for headyaw
		$pk->xRot = $this->location->pitch;
		$pk->yRot = $this->location->yaw; //TODO: head yaw
		$pk->zRot = $this->location->yaw;

		if($teleport){
			$pk->flags |= MoveActorAbsolutePacket::FLAG_TELEPORT;
		}
		if($this->onGround){
			$pk->flags |= MoveActorAbsolutePacket::FLAG_GROUND;
		}

		$this->getWorld()->broadcastPacketToViewers($this->location, $pk);
	}

	protected function broadcastMotion() : void{
		$this->getWorld()->broadcastPacketToViewers($this->location, SetActorMotionPacket::create($this->id, $this->getMotion()));
	}

	public function hasGravity() : bool{
		return $this->gravityEnabled;
	}

	public function setHasGravity(bool $v = true) : void{
		$this->gravityEnabled = $v;
	}

	protected function applyDragBeforeGravity() : bool{
		return false;
	}

	protected function applyGravity() : void{
		$this->motion->y -= $this->gravity;
	}

	protected function tryChangeMovement() : void{
		$friction = 1 - $this->drag;

		if($this->applyDragBeforeGravity()){
			$this->motion->y *= $friction;
		}

		if($this->gravityEnabled){
			$this->applyGravity();
		}

		if(!$this->applyDragBeforeGravity()){
			$this->motion->y *= $friction;
		}

		if($this->onGround){
			$friction *= $this->getWorld()->getBlockAt((int) floor($this->location->x), (int) floor($this->location->y - 1), (int) floor($this->location->z))->getFrictionFactor();
		}

		$this->motion->x *= $friction;
		$this->motion->z *= $friction;
	}

	protected function checkObstruction(float $x, float $y, float $z) : bool{
		$world = $this->getWorld();
		if(count($world->getCollisionBoxes($this, $this->getBoundingBox(), false)) === 0){
			return false;
		}

		$floorX = (int) floor($x);
		$floorY = (int) floor($y);
		$floorZ = (int) floor($z);

		$diffX = $x - $floorX;
		$diffY = $y - $floorY;
		$diffZ = $z - $floorZ;

		if($world->getBlockAt($floorX, $floorY, $floorZ)->isSolid()){
			$westNonSolid  = !$world->getBlockAt($floorX - 1, $floorY, $floorZ)->isSolid();
			$eastNonSolid  = !$world->getBlockAt($floorX + 1, $floorY, $floorZ)->isSolid();
			$downNonSolid  = !$world->getBlockAt($floorX, $floorY - 1, $floorZ)->isSolid();
			$upNonSolid    = !$world->getBlockAt($floorX, $floorY + 1, $floorZ)->isSolid();
			$northNonSolid = !$world->getBlockAt($floorX, $floorY, $floorZ - 1)->isSolid();
			$southNonSolid = !$world->getBlockAt($floorX, $floorY, $floorZ + 1)->isSolid();

			$direction = -1;
			$limit = 9999;

			if($westNonSolid){
				$limit = $diffX;
				$direction = Facing::WEST;
			}

			if($eastNonSolid and 1 - $diffX < $limit){
				$limit = 1 - $diffX;
				$direction = Facing::EAST;
			}

			if($downNonSolid and $diffY < $limit){
				$limit = $diffY;
				$direction = Facing::DOWN;
			}

			if($upNonSolid and 1 - $diffY < $limit){
				$limit = 1 - $diffY;
				$direction = Facing::UP;
			}

			if($northNonSolid and $diffZ < $limit){
				$limit = $diffZ;
				$direction = Facing::NORTH;
			}

			if($southNonSolid and 1 - $diffZ < $limit){
				$direction = Facing::SOUTH;
			}

			$force = lcg_value() * 0.2 + 0.1;

			if($direction === Facing::WEST){
				$this->motion->x = -$force;

				return true;
			}

			if($direction === Facing::EAST){
				$this->motion->x = $force;

				return true;
			}

			if($direction === Facing::DOWN){
				$this->motion->y = -$force;

				return true;
			}

			if($direction === Facing::UP){
				$this->motion->y = $force;

				return true;
			}

			if($direction === Facing::NORTH){
				$this->motion->z = -$force;

				return true;
			}

			if($direction === Facing::SOUTH){
				$this->motion->z = $force;

				return true;
			}
		}

		return false;
	}

	public function getHorizontalFacing() : int{
		$angle = $this->location->yaw % 360;
		if($angle < 0){
			$angle += 360.0;
		}

		if((0 <= $angle and $angle < 45) or (315 <= $angle and $angle < 360)){
			return Facing::SOUTH;
		}
		if(45 <= $angle and $angle < 135){
			return Facing::WEST;
		}
		if(135 <= $angle and $angle < 225){
			return Facing::NORTH;
		}

		return Facing::EAST;
	}

	public function getDirectionVector() : Vector3{
		$y = -sin(deg2rad($this->location->pitch));
		$xz = cos(deg2rad($this->location->pitch));
		$x = -$xz * sin(deg2rad($this->location->yaw));
		$z = $xz * cos(deg2rad($this->location->yaw));

		return $this->temporalVector->setComponents($x, $y, $z)->normalize();
	}

	public function getDirectionPlane() : Vector2{
		return (new Vector2(-cos(deg2rad($this->location->yaw) - M_PI_2), -sin(deg2rad($this->location->yaw) - M_PI_2)))->normalize();
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->closed){
			return false;
		}

		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0){
			if(!$this->justCreated){
				$this->server->getLogger()->debug("Expected tick difference of at least 1, got $tickDiff for " . get_class($this));
			}

			return true;
		}

		$this->lastUpdate = $currentTick;

		if(!$this->isAlive()){
			if($this->onDeathUpdate($tickDiff)){
				$this->flagForDespawn();
			}

			return true;
		}

		$this->timings->startTiming();

		if($this->hasMovementUpdate()){
			$this->tryChangeMovement();

			if(abs($this->motion->x) <= self::MOTION_THRESHOLD){
				$this->motion->x = 0;
			}
			if(abs($this->motion->y) <= self::MOTION_THRESHOLD){
				$this->motion->y = 0;
			}
			if(abs($this->motion->z) <= self::MOTION_THRESHOLD){
				$this->motion->z = 0;
			}

			if($this->motion->x != 0 or $this->motion->y != 0 or $this->motion->z != 0){
				$this->move($this->motion->x, $this->motion->y, $this->motion->z);
			}

			$this->forceMovementUpdate = false;
		}

		$this->updateMovement();

		Timings::$timerEntityBaseTick->startTiming();
		$hasUpdate = $this->entityBaseTick($tickDiff);
		Timings::$timerEntityBaseTick->stopTiming();

		$this->timings->stopTiming();

		//if($this->isStatic())
		return ($hasUpdate or $this->hasMovementUpdate());
		//return !($this instanceof Player);
	}

	final public function scheduleUpdate() : void{
		if($this->closed){
			throw new \InvalidStateException("Cannot schedule update on garbage entity " . get_class($this));
		}
		$this->getWorld()->updateEntities[$this->id] = $this;
	}

	public function onNearbyBlockChange() : void{
		$this->setForceMovementUpdate();
		$this->scheduleUpdate();
	}

	/**
	 * Flags the entity as needing a movement update on the next tick. Setting this forces a movement update even if the
	 * entity's motion is zero. Used to trigger movement updates when blocks change near entities.
	 */
	final public function setForceMovementUpdate(bool $value = true) : void{
		$this->forceMovementUpdate = $value;

		$this->blocksAround = null;
	}

	/**
	 * Returns whether the entity needs a movement update on the next tick.
	 */
	public function hasMovementUpdate() : bool{
		return (
			$this->forceMovementUpdate or
			$this->motion->x != 0 or
			$this->motion->y != 0 or
			$this->motion->z != 0 or
			!$this->onGround
		);
	}

	public function resetFallDistance() : void{
		$this->fallDistance = 0.0;
	}

	protected function updateFallState(float $distanceThisTick, bool $onGround) : void{
		if($onGround){
			if($this->fallDistance > 0){
				$this->fall($this->fallDistance);
				$this->resetFallDistance();
			}
		}elseif($distanceThisTick < 0){
			$this->fallDistance -= $distanceThisTick;
		}
	}

	/**
	 * Called when a falling entity hits the ground.
	 */
	public function fall(float $fallDistance) : void{

	}

	public function getEyeHeight() : float{
		return $this->eyeHeight;
	}

	public function getEyePos() : Vector3{
		return new Vector3($this->location->x, $this->location->y + $this->getEyeHeight(), $this->location->z);
	}

	public function onCollideWithPlayer(Player $player) : void{

	}

	public function isUnderwater() : bool{
		$block = $this->getWorld()->getBlockAt((int) floor($this->location->x), $blockY = (int) floor($y = ($this->location->y + $this->getEyeHeight())), (int) floor($this->location->z));

		if($block instanceof Water){
			$f = ($blockY + 1) - ($block->getFluidHeightPercent() - 0.1111111);
			return $y < $f;
		}

		return false;
	}

	public function isInsideOfSolid() : bool{
		$block = $this->getWorld()->getBlockAt((int) floor($this->location->x), (int) floor($y = ($this->location->y + $this->getEyeHeight())), (int) floor($this->location->z));

		return $block->isSolid() and !$block->isTransparent() and $block->collidesWithBB($this->getBoundingBox());
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		$this->blocksAround = null;

		Timings::$entityMoveTimer->startTiming();

		$movX = $dx;
		$movY = $dy;
		$movZ = $dz;

		if($this->keepMovement){
			$this->boundingBox->offset($dx, $dy, $dz);
		}else{

			/*
			if($this->isColliding){ //With cobweb?
				$this->isColliding = false;
				$dx *= 0.25;
				$dy *= 0.05;
				$dz *= 0.25;
				$this->motionX = 0;
				$this->motionY = 0;
				$this->motionZ = 0;
			}
			*/

			$moveBB = clone $this->boundingBox;

			/*$sneakFlag = $this->onGround and $this instanceof Player;

			if($sneakFlag){
				for($mov = 0.05; $dx != 0.0 and count($this->world->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox($dx, -1, 0))) === 0; $movX = $dx){
					if($dx < $mov and $dx >= -$mov){
						$dx = 0;
					}elseif($dx > 0){
						$dx -= $mov;
					}else{
						$dx += $mov;
					}
				}

				for(; $dz != 0.0 and count($this->world->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox(0, -1, $dz))) === 0; $movZ = $dz){
					if($dz < $mov and $dz >= -$mov){
						$dz = 0;
					}elseif($dz > 0){
						$dz -= $mov;
					}else{
						$dz += $mov;
					}
				}

				//TODO: big messy loop
			}*/

			assert(abs($dx) <= 20 and abs($dy) <= 20 and abs($dz) <= 20, "Movement distance is excessive: dx=$dx, dy=$dy, dz=$dz");

			$list = $this->getWorld()->getCollisionBoxes($this, $moveBB->addCoord($dx, $dy, $dz), false);

			foreach($list as $bb){
				$dy = $bb->calculateYOffset($moveBB, $dy);
			}

			$moveBB->offset(0, $dy, 0);

			$fallingFlag = ($this->onGround or ($dy != $movY and $movY < 0));

			foreach($list as $bb){
				$dx = $bb->calculateXOffset($moveBB, $dx);
			}

			$moveBB->offset($dx, 0, 0);

			foreach($list as $bb){
				$dz = $bb->calculateZOffset($moveBB, $dz);
			}

			$moveBB->offset(0, 0, $dz);

			if($this->stepHeight > 0 and $fallingFlag and ($movX != $dx or $movZ != $dz)){
				$cx = $dx;
				$cy = $dy;
				$cz = $dz;
				$dx = $movX;
				$dy = $this->stepHeight;
				$dz = $movZ;

				$stepBB = clone $this->boundingBox;

				$list = $this->getWorld()->getCollisionBoxes($this, $stepBB->addCoord($dx, $dy, $dz), false);
				foreach($list as $bb){
					$dy = $bb->calculateYOffset($stepBB, $dy);
				}

				$stepBB->offset(0, $dy, 0);

				foreach($list as $bb){
					$dx = $bb->calculateXOffset($stepBB, $dx);
				}

				$stepBB->offset($dx, 0, 0);

				foreach($list as $bb){
					$dz = $bb->calculateZOffset($stepBB, $dz);
				}

				$stepBB->offset(0, 0, $dz);

				//TODO: here we need to shift back down on the Y-axis to the top of the target block (we don't want to jump into the air when walking onto carpet)

				if(($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)){
					$dx = $cx;
					$dy = $cy;
					$dz = $cz;
				}else{
					$moveBB = $stepBB;
				}
			}

			$this->boundingBox = $moveBB;
		}

		$this->location->x = ($this->boundingBox->minX + $this->boundingBox->maxX) / 2;
		$this->location->y = $this->boundingBox->minY;
		$this->location->z = ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2;

		$this->checkChunks();
		$this->checkBlockCollision();
		$this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
		$this->updateFallState($dy, $this->onGround);

		if($movX != $dx){
			$this->motion->x = 0;
		}

		if($movY != $dy){
			$this->motion->y = 0;
		}

		if($movZ != $dz){
			$this->motion->z = 0;
		}

		//TODO: vehicle collision events (first we need to spawn them!)

		Timings::$entityMoveTimer->stopTiming();
	}

	protected function checkGroundState(float $movX, float $movY, float $movZ, float $dx, float $dy, float $dz) : void{
		$this->isCollidedVertically = $movY != $dy;
		$this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
		$this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
		$this->onGround = ($movY != $dy and $movY < 0);
	}

	/**
	 * @deprecated WARNING: Despite what its name implies, this function DOES NOT return all the blocks around the entity.
	 * Instead, it returns blocks which have reactions for an entity intersecting with them.
	 *
	 * @return Block[]
	 */
	public function getBlocksAround() : array{
		if($this->blocksAround === null){
			$inset = 0.001; //Offset against floating-point errors

			$minX = (int) floor($this->boundingBox->minX + $inset);
			$minY = (int) floor($this->boundingBox->minY + $inset);
			$minZ = (int) floor($this->boundingBox->minZ + $inset);
			$maxX = (int) floor($this->boundingBox->maxX - $inset);
			$maxY = (int) floor($this->boundingBox->maxY - $inset);
			$maxZ = (int) floor($this->boundingBox->maxZ - $inset);

			$this->blocksAround = [];

			$world = $this->getWorld();

			for($z = $minZ; $z <= $maxZ; ++$z){
				for($x = $minX; $x <= $maxX; ++$x){
					for($y = $minY; $y <= $maxY; ++$y){
						$block = $world->getBlockAt($x, $y, $z);
						if($block->hasEntityCollision()){
							$this->blocksAround[] = $block;
						}
					}
				}
			}
		}

		return $this->blocksAround;
	}

	/**
	 * Returns whether this entity can be moved by currents in liquids.
	 */
	public function canBeMovedByCurrents() : bool{
		return true;
	}

	protected function checkBlockCollision() : void{
		$vector = $this->temporalVector->setComponents(0, 0, 0);

		foreach($this->getBlocksAround() as $block){
			$block->onEntityInside($this);
			$block->addVelocityToEntity($this, $vector);
		}

		if($vector->lengthSquared() > 0){
			$vector = $vector->normalize();
			$d = 0.014;
			$this->motion->x += $vector->x * $d;
			$this->motion->y += $vector->y * $d;
			$this->motion->z += $vector->z * $d;
		}
	}

	public function getPosition() : Position{
		return $this->location->asPosition();
	}

	public function getLocation() : Location{
		return $this->location->asLocation();
	}

	public function getWorld() : World{
		return $this->location->getWorldNonNull();
	}

	protected function setPosition(Vector3 $pos) : bool{
		if($this->closed){
			return false;
		}

		if($pos instanceof Position and $pos->isValid() and $pos->getWorldNonNull() !== $this->getWorld()){
			if(!$this->switchWorld($pos->getWorldNonNull())){
				return false;
			}
		}

		$this->location->x = $pos->x;
		$this->location->y = $pos->y;
		$this->location->z = $pos->z;

		$this->recalculateBoundingBox();

		$this->blocksAround = null;

		$this->checkChunks();

		return true;
	}

	public function setRotation(float $yaw, float $pitch) : void{
		$this->location->yaw = $yaw;
		$this->location->pitch = $pitch;
		$this->scheduleUpdate();
	}

	protected function setPositionAndRotation(Vector3 $pos, float $yaw, float $pitch) : bool{
		if($this->setPosition($pos)){
			$this->setRotation($yaw, $pitch);

			return true;
		}

		return false;
	}

	protected function checkChunks() : void{
		$chunkX = $this->location->getFloorX() >> 4;
		$chunkZ = $this->location->getFloorZ() >> 4;
		if($this->chunk === null or ($this->chunk->getX() !== $chunkX or $this->chunk->getZ() !== $chunkZ)){
			if($this->chunk !== null){
				$this->chunk->removeEntity($this);
			}
			$this->chunk = $this->getWorld()->getChunk($chunkX, $chunkZ, true);

			if(!$this->justCreated){
				$newChunk = $this->getWorld()->getViewersForPosition($this->location);
				foreach($this->hasSpawned as $player){
					if(!isset($newChunk[spl_object_id($player)])){
						$this->despawnFrom($player);
					}else{
						unset($newChunk[spl_object_id($player)]);
					}
				}
				foreach($newChunk as $player){
					$this->spawnTo($player);
				}
			}

			if($this->chunk === null){
				return;
			}

			$this->chunk->addEntity($this);
		}
	}

	protected function resetLastMovements() : void{
		$this->lastLocation = $this->location->asLocation();
		$this->lastMotion = clone $this->motion;
	}

	public function getMotion() : Vector3{
		return clone $this->motion;
	}

	public function setMotion(Vector3 $motion) : bool{
		if(!$this->justCreated){
			$ev = new EntityMotionEvent($this, $motion);
			$ev->call();
			if($ev->isCancelled()){
				return false;
			}
		}

		$this->motion = clone $motion;

		if(!$this->justCreated){
			$this->updateMovement();
		}

		return true;
	}

	/**
	 * Adds the given values to the entity's motion vector.
	 */
	public function addMotion(float $x, float $y, float $z) : void{
		$this->motion->x += $x;
		$this->motion->y += $y;
		$this->motion->z += $z;
	}

	public function isOnGround() : bool{
		return $this->onGround;
	}

	/**
	 * @param Vector3|Position|Location $pos
	 */
	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		if($pos instanceof Location){
			$yaw = $yaw ?? $pos->yaw;
			$pitch = $pitch ?? $pos->pitch;
		}
		$from = $this->location->asPosition();
		$to = Position::fromObject($pos, $pos instanceof Position ? $pos->getWorldNonNull() : $this->getWorld());
		$ev = new EntityTeleportEvent($this, $from, $to);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}
		$pos = $ev->getTo();

		$this->setMotion($this->temporalVector->setComponents(0, 0, 0));
		if($this->setPositionAndRotation($pos, $yaw ?? $this->location->yaw, $pitch ?? $this->location->pitch)){
			$this->resetFallDistance();
			$this->onGround = true;

			$this->updateMovement(true);

			return true;
		}

		return false;
	}

	protected function switchWorld(World $targetWorld) : bool{
		if($this->closed){
			return false;
		}

		if($this->location->isValid()){
			$this->getWorld()->removeEntity($this);
			if($this->chunk !== null){
				$this->chunk->removeEntity($this);
			}
			$this->despawnFromAll();
		}

		$this->location = new Location(
			$this->location->x,
			$this->location->y,
			$this->location->z,
			$this->location->yaw,
			$this->location->pitch,
			$targetWorld
		);
		$this->getWorld()->addEntity($this);
		$this->chunk = null;

		return true;
	}

	public function getId() : int{
		return $this->id;
	}

	/**
	 * @return Player[]
	 */
	public function getViewers() : array{
		return $this->hasSpawned;
	}

	abstract public static function getNetworkTypeId() : int;

	/**
	 * Called by spawnTo() to send whatever packets needed to spawn the entity to the client.
	 */
	protected function sendSpawnPacket(Player $player) : void{
		$pk = new AddActorPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = LegacyEntityIdToStringIdMap::getInstance()->legacyToString(static::getNetworkTypeId());
		$pk->position = $this->location->asVector3();
		$pk->motion = $this->getMotion();
		$pk->yaw = $this->location->yaw;
		$pk->headYaw = $this->location->yaw; //TODO
		$pk->pitch = $this->location->pitch;
		$pk->attributes = array_map(function(Attribute $attr) : NetworkAttribute{
			return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue());
		}, $this->attributeMap->getAll());
		$pk->metadata = $this->getSyncedNetworkData(false);

		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function spawnTo(Player $player) : void{
		$id = spl_object_id($player);
		//TODO: this will cause some visible lag during chunk resends; if the player uses a spawn egg in a chunk, the
		//created entity won't be visible until after the resend arrives. However, this is better than possibly crashing
		//the player by sending them entities too early.
		if(!isset($this->hasSpawned[$id]) and $player->hasReceivedChunk($this->location->getFloorX() >> 4, $this->location->getFloorZ() >> 4)){
			$this->hasSpawned[$id] = $player;

			$this->sendSpawnPacket($player);
		}
	}

	public function spawnToAll() : void{
		if($this->closed){
			return;
		}
		foreach($this->getWorld()->getViewersForPosition($this->location) as $player){
			$this->spawnTo($player);
		}
	}

	public function respawnToAll() : void{
		foreach($this->hasSpawned as $key => $player){
			unset($this->hasSpawned[$key]);
			$this->spawnTo($player);
		}
	}

	/**
	 * @deprecated WARNING: This function DOES NOT permanently hide the entity from the player. As soon as the entity or
	 * player moves, the player will once again be able to see the entity.
	 */
	public function despawnFrom(Player $player, bool $send = true) : void{
		$id = spl_object_id($player);
		if(isset($this->hasSpawned[$id])){
			if($send){
				$player->getNetworkSession()->onEntityRemoved($this);
			}
			unset($this->hasSpawned[$id]);
		}
	}

	/**
	 * @deprecated WARNING: This function DOES NOT permanently hide the entity from viewers. As soon as the entity or
	 * player moves, viewers will once again be able to see the entity.
	 */
	public function despawnFromAll() : void{
		foreach($this->hasSpawned as $player){
			$this->despawnFrom($player);
		}
	}

	/**
	 * Flags the entity to be removed from the world on the next tick.
	 */
	public function flagForDespawn() : void{
		$this->needsDespawn = true;
		$this->scheduleUpdate();
	}

	public function isFlaggedForDespawn() : bool{
		return $this->needsDespawn;
	}

	/**
	 * Returns whether the entity has been "closed".
	 */
	public function isClosed() : bool{
		return $this->closed;
	}

	/**
	 * Closes the entity and frees attached references.
	 *
	 * WARNING: Entities are unusable after this has been executed!
	 */
	final public function close() : void{
		if(!$this->closed){
			$this->closed = true;
			(new EntityDespawnEvent($this))->call();

			$this->onDispose();
			$this->destroyCycles();
		}
	}

	/**
	 * Called when the entity is disposed to clean up things like viewers. This SHOULD NOT destroy internal state,
	 * because it may be needed by descendent classes.
	 */
	protected function onDispose() : void{
		$this->despawnFromAll();
		if($this->chunk !== null){
			$this->chunk->removeEntity($this);
		}
		if($this->location->isValid()){
			$this->getWorld()->removeEntity($this);
		}
	}

	/**
	 * Called when the entity is disposed, after all events have been fired. This should be used to perform destructive
	 * circular object references and things which could impact memory usage.
	 *
	 * It is expected that the object is unusable after this is called.
	 */
	protected function destroyCycles() : void{
		$this->chunk = null;
		$this->location = null;
		$this->lastDamageCause = null;
	}

	/**
	 * @param Player[]|Player    $player
	 * @param MetadataProperty[] $data Properly formatted entity data, defaults to everything
	 * @phpstan-param array<int, MetadataProperty> $data
	 */
	public function sendData($player, ?array $data = null) : void{
		if(!is_array($player)){
			$player = [$player];
		}

		$data = $data ?? $this->getSyncedNetworkData(false);

		foreach($player as $p){
			if($p === $this){
				continue;
			}
			$p->getNetworkSession()->syncActorData($this, $data);
		}

		if($this instanceof Player){
			//TODO: bad hack, remove
			$this->getNetworkSession()->syncActorData($this, $data);
		}
	}

	/**
	 * @return MetadataProperty[]
	 */
	final protected function getSyncedNetworkData(bool $dirtyOnly) : array{
		$this->syncNetworkData($this->networkProperties);

		return $dirtyOnly ? $this->networkProperties->getDirty() : $this->networkProperties->getAll();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		$properties->setByte(EntityMetadataProperties::ALWAYS_SHOW_NAMETAG, $this->alwaysShowNameTag ? 1 : 0);
		$properties->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, $this->height);
		$properties->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, $this->width);
		$properties->setFloat(EntityMetadataProperties::SCALE, $this->scale);
		$properties->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, -1);
		$properties->setLong(EntityMetadataProperties::OWNER_EID, $this->ownerId ?? -1);
		$properties->setLong(EntityMetadataProperties::TARGET_EID, $this->targetId ?? 0);
		$properties->setString(EntityMetadataProperties::NAMETAG, $this->nameTag);
		$properties->setString(EntityMetadataProperties::SCORE_TAG, $this->scoreTag);

		$properties->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, true);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_CLIMB, $this->canClimb);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_SHOW_NAMETAG, $this->nameTagVisible);
		$properties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, true);
		$properties->setGenericFlag(EntityMetadataFlags::IMMOBILE, $this->immobile);
		$properties->setGenericFlag(EntityMetadataFlags::INVISIBLE, $this->invisible);
		$properties->setGenericFlag(EntityMetadataFlags::ONFIRE, $this->isOnFire());
		$properties->setGenericFlag(EntityMetadataFlags::WALLCLIMBING, $this->canClimbWalls);
	}

	/**
	 * @param Player[]|null $targets
	 */
	public function broadcastAnimation(Animation $animation, ?array $targets = null) : void{
		$this->server->broadcastPackets($targets ?? $this->getViewers(), $animation->encode());
	}

	public function __destruct(){
		$this->close();
	}

	public function __toString(){
		return (new \ReflectionClass($this))->getShortName() . "(" . $this->getId() . ")";
	}
}
