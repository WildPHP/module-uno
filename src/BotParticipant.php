<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\ComponentContainer;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\Users\User;

class BotParticipant extends Participant
{
	use ContainerTrait;

	/**
	 * BotParticipant constructor.
	 *
	 * @param User $user
	 * @param Deck $deck
	 * @param ComponentContainer $container
	 */
	public function __construct(User $user, Deck $deck, ComponentContainer $container)
	{
		parent::__construct($user, $deck);
		$this->setContainer($container);
	}
}