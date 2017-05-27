<?php
/**
 * WildPHP - an advanced and easily extensible IRC bot written in PHP
 * Copyright (C) 2017 WildPHP
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\Users\User;

class Participant
{
	/**
	 * @var User
	 */
	protected $userObject = null;

	/**
	 * @var Deck
	 */
	protected $deck = null;

	public function __construct(User $user, Deck $deck)
	{
		$this->setUserObject($user);
		$this->setDeck($deck);
	}

	/**
	 * @return User
	 */
	public function getUserObject(): User
	{
		return $this->userObject;
	}

	/**
	 * @param User $userObject
	 */
	public function setUserObject(User $userObject)
	{
		$this->userObject = $userObject;
	}

	/**
	 * @return Deck
	 */
	public function getDeck(): Deck
	{
		return $this->deck;
	}

	/**
	 * @param Deck $deck
	 */
	public function setDeck(Deck $deck)
	{
		$this->deck = $deck;
	}
}