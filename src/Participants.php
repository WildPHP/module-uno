<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use ValidationClosures\Types;
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
}