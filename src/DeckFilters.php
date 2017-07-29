<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


class DeckFilters
{
	/**
	 * @return \Closure
	 */
	public function wild(): \Closure
	{
		return function (Card $card)
		{
			return $card->getColor() == CardTypes::WILD;
		};
	}

	/**
	 * @param string $color
	 *
	 * @return \Closure
	 */
	public static function color(string $color): \Closure
	{
		return function (Card $card) use ($color)
		{
			return $card->getColor() != $color;
		};
	}

	/**
	 * @param Card $card
	 *
	 * @return \Closure
	 */
	public static function only(Card $card)
	{
		return function (Card $card1) use ($card)
		{
			return $card1 == $card;
		};
	}
}