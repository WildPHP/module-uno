<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

/**
 * Created by PhpStorm.
 * User: rick2
 * Date: 26-5-2017
 * Time: 21:13
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\ComponentContainer;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\Users\User;

class BotParticipant extends Participant
{
	use ContainerTrait;

	public function __construct(User $user, Deck $deck, ComponentContainer $container)
	{
		parent::__construct($user, $deck);
		$this->setContainer($container);
	}
}