<?php

namespace pocketmine\entity\ai;

use pocketmine\entity\ai\AIHolder;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\entity\Monster;
use pocketmine\scheduler\CallbackTask;
use pocketmine\entity\Creeper;
use pocketmine\level\Explosion;
use pocketmine\level\Position;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

class MobsAI {
	protected $AIHolder;
	
	protected $ticksPerSecond = 20;  // Later should get this from Server->autoTickRateLimit.  Right now hard coding the default
	
	protected $addUntrackedMobsIntervalSecs = 2; // Add untracked mobs every 2 seconds
	protected $hateFinderIntervalSecs = .75; // Look for someone to chase every 3/4 of a second
	protected $hateMovementIntervalSecs = .75; // Chase player movement every 3/4 of a second
	protected $catchOnFireCheckIntervalSecs = 2; // Catch on fire (if appropriate) every 2 seconds
	
	private $addUntrackedMobsTask;
	private $mobHateFinderTask;
	private $mobHateWalkTask;
	private $mobFireTask;
	private $mobExplodeTask;
	
	private $enabled = true;
	
	
	// TODO it would be good to create separate lists of entities that can catch fire, and entities that can explode (just creepers) so don't 
	// need to iterate over complete list for mobFire and moExplode
	
	
	public function __construct(AIHolder $AIHolder) {
		$this->AIHolder = $AIHolder;
		if ($this->enabled()) {
			$this->startTasks();
		}
	}
	
	private function startTasks() {
		$addUntrackedMobsTask = $this->AIHolder->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this, "addUntrackedMobs" ] ),
				$this->getTimeInTicks($this->addUntrackedMobsIntervalSecs) );
		$mobHateFinderTask = $this->AIHolder->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,	"mobHateFinder" ] ),
				$this->getTimeInTicks($this->hateFinderIntervalSecs) );
		$mobHateWalkTask = $this->AIHolder->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,	"mobHateWalk" ] ),
				$this->getTimeInTicks($this->hateMovementIntervalSecs) );
		$mobFireTask = $this->AIHolder->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,	"mobFire" ] ),
				$this->getTimeInTicks($this->catchOnFireCheckIntervalSecs) );
		$mobExplodeTask = $this->AIHolder->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,	"mobExplode" ] ), 40 );
	}
	
	private function stopTasks() {
		$this->AIHolder->getServer ()->getScheduler ()->cancelTask($addUntrackedMobsTask->getTaskId);
		$this->AIHolder->getServer ()->getScheduler ()->cancelTask($mobHateFinderTask->getTaskId);
		$this->AIHolder->getServer ()->getScheduler ()->cancelTask($mobHateWalkTask->getTaskId);
		$this->AIHolder->getServer ()->getScheduler ()->cancelTask($mobFireTask->getTaskId);
		$this->AIHolder->getServer ()->getScheduler ()->cancelTask($mobExplodeTask->getTaskId);
	}
	
	
	// Functions to be overridden
	
	public function enabled() {
		return $this->enabled;
	}
	
	public function setEnabled($newEnabled) {
		if ($enabled != $newEnabled) {
			// Only process if a change in state.
			if ($newEnabled == false) {
				$this->stopTasks();
			} else {
				$this->startTasks();
			}
		}
	}
	
	// Core functions
	public function addUntrackedMobs() {
		foreach ( $this->AIHolder->getServer ()->getLevels () as $level ) {
			foreach ( $level->getEntities () as $mobEntity ) {
				if($mobEntity instanceof Monster) {
					if (! isset ( $this->AIHolder->mob [$mobEntity->getId ()] )) {
						$this->AIHolder->mob [$mobEntity->getId ()] = array (
								'ID' => $mobEntity->getId (),
								'IsChasing' => false,
								'time' => 10,
								'x' => $mobEntity->getX (),
								'y' => $mobEntity->getY (),
								'z' => $mobEntity->getZ (),
								'canAttack' => 0,
								'yaw' => $mobEntity->yaw,
								'pitch' => $mobEntity->pitch,
								'explodeCount' => 0
						);
					}
				}
			}
		}
	}
	
	public function mobHateFinder() {
		foreach ( $this->AIHolder->getServer ()->getLevels () as $level ) {
			foreach ( $level->getEntities () as $mobEntity ) {
				if($mobEntity instanceof Monster) {
					if (isset ( $this->AIHolder->mob [$mobEntity->getId ()] )) {
						$mobData = &$this->AIHolder->mob [$mobEntity->getId ()];
						if ($mobData ['IsChasing'] !== false) {
							$p = $this->AIHolder->getServer ()->getPlayer ( $mobData ['IsChasing'] );
							if (($p instanceof Player) === false || !$p->isAlive()) {
								$mobData ['IsChasing'] = false;
							}
						} else {
							$pos = new Vector3 ( $mobEntity->getX (), $mobEntity->getY (), $mobEntity->getZ () );
							$hatred = false;
							foreach ( $mobEntity->getViewers () as $p ) {
								if ($p->distance ( $pos ) <= $mobEntity->getHateRadius()) {
									if ($hatred === false) {
										$hatred = $p;
									} elseif ($hatred instanceof Player) {
										if ($p->distance ( $pos ) <= $hatred->distance ( $pos ) || (! $hatred->isAlive ())) {
											$hatred = $p;
										}
									}
								}
							}
							if ($hatred == false) {
								$mobData ['IsChasing'] = false;
							} else {
								$mobData ['IsChasing'] = $hatred->getName ();
							}
						}
					}
				}
			}
		}
	}
	
	public function mobHateWalk() {
		foreach ( $this->AIHolder->getServer ()->getLevels () as $level ) {
			foreach ( $level->getEntities () as $mobEntity ) {
				if($mobEntity instanceof Monster) {
					if (isset ( $this->AIHolder->mob [$mobEntity->getId ()] )) {
						$mobData = &$this->AIHolder->mob [$mobEntity->getId ()];
						// IsChasing starts out false and is set to a player to chase by hate finder when a player is near
						// enough to start chasing
						if ($mobData ['IsChasing'] !== false) {
							$p = $this->AIHolder->getServer ()->getPlayer ( $mobData ['IsChasing'] );
							if (($p instanceof Player) === false || (!$p->isAlive())) {
								$mobData ['IsChasing'] = false;
							} else {
								$xDistance = $p->getX () - $mobEntity->getX ();
								$zDistance = $p->getZ () - $mobEntity->getZ ();
								// pythagoream theorem
								$diagonal = sqrt($xDistance**2+$zDistance**2);
								
								$movement = $mobEntity->getDistanceToMovePerSecond() * $this->hateMovementIntervalSecs;
								
								// To get the movement 
								$xMovement = $xDistance*$movement/$diagonal;
								$zMovement = $zDistance*$movement/$diagonal;
								$mobEntity->y = $mobEntity->getFloorY();
								$newPositon = new Vector3 ($mobEntity->getX() + $xMovement, $mobEntity->getY(), $mobEntity->getZ() + $zMovement);
								if (!$this->safePosition($mobEntity->getLevel(), $newPositon)) {
									$newPositon = new Vector3 ($mobEntity->getX() + $xMovement, $mobEntity->getY()+1, $mobEntity->getZ() + $zMovement);
									if (!$this->safePosition($mobEntity->getLevel(), $newPositon)) {
										$newPositon = new Vector3 ($mobEntity->getX() + $xMovement, $mobEntity->getY()-1, $mobEntity->getZ() + $zMovement);
										if (!$this->safePosition($mobEntity->getLevel(), $newPositon)) {
											$newPositon = new Vector3 ($mobEntity->getX() + $xMovement, $mobEntity->getY()+2, $mobEntity->getZ() + $zMovement);
											if (!$this->safePosition($mobEntity->getLevel(), $newPositon)) {
												$newPositon = new Vector3 ($mobEntity->getX() + $xMovement, $mobEntity->getY()-2, $mobEntity->getZ() + $zMovement);
												if (!$this->safePosition($mobEntity->getLevel(), $newPositon)) {
													$newPositon = new Vector3 ($mobEntity->getX(), $mobEntity->getY(), $mobEntity->getZ());
												}
											}
										}
									}
								}
								$newPositon = new Vector3 ($newPositon->getX(), $newPositon->getY()-2, $newPositon->getZ());
								
								// Adjust which way left/right (yaw) and up/down (pitch) the mob should turn to look at the
								// direction it is going to go.  The actual movement will be handled by RotationTimer, maybe to slowly turn the mobs
								// head?  Maybe later could just change to turn the head here and not need a separate task,
								// but the benefit of the separate task seems to be that the mob will turn to look at the
								// player even if the mob isn't moving.
								$yaw = $this->AIHolder->getyaw($xDistance, $zDistance);
								$mobData['yaw'] = $yaw;
								$mobEyes = $newPositon;
								$mobEyes->y = $mobEyes->y + 2.62;
								$playerEyes = $p->getLocation();
								$playerEyes->y = $playerEyes->y + $p->getEyeHeight();
								$pitch = $this->AIHolder->getpitch($mobEyes, $playerEyes);
								$mobData['pitch'] = $pitch;
								
								$mobEntity->setPosition ( $newPositon );

								if ($p instanceof Player) {
									if ($p->distance($newPositon) <= 1.5) {
// 										if ($mobData['canAttack'] == 0) {
// 											$mobData['canAttack'] = 10;
											if ($p->isSurvival()) {
												$mobDamage = $this->AIHolder->getZombieDamage($mobEntity->getHealth());
												$damage = $this->AIHolder->getPlayerDamage($p, $mobDamage);
												//echo $zoDamage."-".$damage."\n";
												$p->attack(new EntityDamageByEntityEvent($mobEntity,$p,EntityDamageEvent::CAUSE_ENTITY_ATTACK,$damage));
											}
// 										} else {
// 											$mobData['canAttack'] -= 1;
// 										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	public function mobFire() {
		foreach ( $this->AIHolder->getServer ()->getLevels () as $level ) {
			foreach ( $level->getEntities () as $mobEntity ) {
				if (isset ( $this->AIHolder->mob [$mobEntity->getId ()] )) {
					$mobData = &$this->AIHolder->mob [$mobEntity->getId ()];
					if($mobEntity instanceof Monster) {
						if ($mobEntity->canCatchOnFire()) {
							$timeOfDay = abs ( $level->getTime () % 24000 );
							if (0 < $timeOfDay and $timeOfDay < 13000) {
								$v3 = new Vector3 ( $mobEntity->getX (), $mobEntity->getY (), $mobEntity->getZ () );
								$ok = true;
								for($y0 = $mobEntity->getY () + 2; $y0 <= $mobEntity->getY () + 10; $y0 ++) {
									$v3->y = $y0;
									if ($level->getBlock ( $v3 )->getID () != 0) {
										$ok = false;
										break;
									}
								}
								if ($this->AIHolder->whatBlock ( $level, new Vector3 ( $mobEntity->getX (), floor ( $mobEntity->getY () - 1 ), $mobEntity->getZ () ) ) == "water") {
									$ok = false;
								}
								if ($ok) {
									$mobEntity->setOnFire ( 2 );
									$p = $this->AIHolder->getServer ()->getPlayer ( $mobData ['IsChasing'] );
									if ($p instanceof Player) {
										if ($p->distance($v3) <= 1.3) {
											$p->setOnFire(1);
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	public function mobExplode() {
		foreach ( $this->AIHolder->getServer ()->getLevels () as $level ) {
			foreach ( $level->getEntities () as $mobEntity ) {
				if($mobEntity instanceof Creeper) {
					if (isset ( $this->AIHolder->mob [$mobEntity->getId ()] )) {
						$mobData = &$this->AIHolder->mob [$mobEntity->getId ()];
						if ($mobData ['IsChasing'] !== false) {
							$mobData['explodeCount'] = $mobData['explodeCount'] + 1 ;
							$explodeCount = $mobData['explodeCount'];
							if($mobData['explodeCount'] >= 4){
								unset($this->AIHolder->mob[$mobEntity->getId()]);
								$e = new Explosion(new Position($mobEntity->getX(), $mobEntity->getY(), $mobEntity->getZ(), $level),4,
										$mobEntity);
								$e->explodeB();
								$level->getEntity($mobEntity->getId())->close();
							}
						} else {
							$mobData['explodeCount'] = 0;
						}
					}
				}
			}
		}
	}
	
	private function safePosition($level, Vector3 $newPos) {
		$below = new Vector3 ( $newPos->getX (), $newPos->getY()-1, $newPos->getZ ());
		$bottom = $newPos;
		$top = new Vector3 ( $newPos->getX (), $newPos->getY()+1, $newPos->getZ ());
		
		// Check if block below the one to be stood on is air and if the block to be stood on is not a stair
		if ($this->isAir($level, $below, "below") && !$this->isStair($level, $bottom, "bottom")) {
			return false;
		}
// 		// Check if the block to be stood on isn't air and isn't a stair
		if (!$this->isAir($level, $bottom, "bottom") && !$this->isStair($level, $bottom, "bottom")) {
			return false;
		}
		if (!$this->isAir($level, $top, "top")) {
			return false;
		}
		return true;
	}
	
	private function isAir($level, $position, $label) {
		$block = $this->AIHolder->whatBlock($level, $position);
		if ($block == "air") {
			return true;
		}
		return false;
	}
	
	private function isStair($level, $position, $label) {
		$block = $this->AIHolder->whatBlock($level, $position);
		if ($block == "stair") {
			return true;
		}
		return false;
	}
	
	private function getTimeInTicks($targetSeconds) {
		return $targetSeconds*$this->ticksPerSecond;
	}
}
