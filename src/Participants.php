<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use ValidationClosures\Types;
use WildPHP\Core\Users\User;
use Yoshi2889\Collections\Collection;

class Participants extends Collection
{
	/**
	 * Participants constructor.
	 */
	public function __construct()
	{
		parent::__construct(Types::instanceof(Participant::class));
	}

	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	public function includes(User $user): bool 
	{
		$filtered = $this->filter(function (Participant $participant) use ($user)
		{
			return $participant->getUserObject() == $user;
		});
		return !empty((array) $filtered);
	}

	/**
	 * @param User $user
	 *
	 * @return Participant|false
	 */
	public function findForUser(User $user)
	{
		$filtered = $this->filter(function (Participant $participant) use ($user)
		{
			return $participant->getUserObject() === $user;
		});

		if (!empty($filtered))
			return reset($filtered);

		return false;
	}
}