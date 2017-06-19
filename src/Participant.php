<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
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