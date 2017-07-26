<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use Collections\Collection;

class Participants extends Collection
{
	/**
	 * Participants constructor.
	 */
	public function __construct()
	{
		parent::__construct(Participant::class);
	}

	/**
	 * @return array
	 */
	public function &toArray()
	{
		return $this->items;
	}
}