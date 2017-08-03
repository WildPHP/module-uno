<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\Channels\Channel;
use Yoshi2889\Collections\Collection;


class Utils
{
	/**
	 * @param Channel $channel
	 * @param Collection $games
	 *
	 * @return false|Game
	 */
	public static function findGameForChannel(Channel $channel, Collection $games)
	{
		$name = $channel->getName();

		if (!$games->offsetExists($name))
			return false;

		return $games[$name];
	}

	/**
	 * @param Card $card
	 *
	 * @return string
	 */
	public static function formatCardForMessage(Card $card): string
	{
		return $card->toHumanString() . ' (' . $card->format() . ')';
	}
}