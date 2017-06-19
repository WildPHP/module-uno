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
 * Time: 21:27
 */

namespace WildPHP\Modules\Uno;


use Collections\Collection;

class Participants extends Collection
{
	public function __construct()
	{
		parent::__construct(Participant::class);
	}

	public function &toArray()
	{
		return $this->items;
	}
}