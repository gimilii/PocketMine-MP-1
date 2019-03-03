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

namespace pocketmine\event\block;

use pocketmine\block\SignPost;
use pocketmine\block\utils\SignText;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\Player;

/**
 * Called when a sign's text is changed by a player.
 */
class SignChangeEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	/** @var SignPost */
	private $sign;

	/** @var Player */
	private $player;

	/** @var SignText */
	private $text;

	/**
	 * @param SignPost $sign
	 * @param Player   $player
	 * @param SignText $text
	 */
	public function __construct(SignPost $sign, Player $player, SignText $text){
		parent::__construct($sign);
		$this->sign = $sign;
		$this->player = $player;
		$this->text = $text;
	}

	/**
	 * @return SignPost
	 */
	public function getSign() : SignPost{
		return $this->sign;
	}

	/**
	 * @return Player
	 */
	public function getPlayer() : Player{
		return $this->player;
	}

	/**
	 * Returns the text currently on the sign.
	 *
	 * @return SignText
	 */
	public function getOldText() : SignText{
		return $this->sign->getText();
	}

	/**
	 * Returns the text which will be on the sign after the event.
	 *
	 * @return SignText
	 */
	public function getNewText() : SignText{
		return $this->text;
	}

	/**
	 * Sets the text to be written on the sign after the event.
	 *
	 * @param SignText $text
	 */
	public function setNewText(SignText $text) : void{
		$this->text = $text;
	}
}
