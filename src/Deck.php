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

/**
 * Created by PhpStorm.
 * User: rick2
 * Date: 20-5-2017
 * Time: 20:27
 */

namespace WildPHP\Modules\Uno;


class Deck
{
	protected $cards = [];

	protected $allowColors = true;

	public function addCard(string $card)
	{
		if (!in_array($card, Game::$validCards))
			throw new \InvalidArgumentException('Invalid card');

		$this->cards[] = $card;
	}

	public function addCards(array $cards)
	{
		foreach ($cards as $card)
			$this->addCard($card);
	}

	public function removeCard(string $card)
	{
		if (!$this->containsCard($card))
			return false;

		unset($this->cards[array_search($card, $this->cards)]);
		return true;
	}

	public function containsCard(string $card)
	{
		return in_array($card, $this->cards);
	}

	public function getCards()
	{
		return $this->cards;
	}

	/**
	 * @return bool
	 */
	public function colorsAllowed(): bool
	{
		return $this->allowColors;
	}

	/**
	 * @param bool $allowColors
	 */
	public function setAllowColors(bool $allowColors)
	{
		$this->allowColors = $allowColors;
	}
}