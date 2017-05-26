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


use Collections\Collection;

class Deck extends Collection
{
	protected $allowColors = true;

	public function __construct()
	{
		parent::__construct(Card::class);
	}

	public function sortCards()
	{
		$this->sort(function (Card $card1, Card $card2)
		{
			if ($card1->toString() == $card2->toString())
				return 0;

			return ($card1->toString() < $card2->toString()) ? -1 : 1;
		});
	}

	/**
	 * @return array
	 */
	public function allToString(): array
	{
		$cards = $this->toArray();
		/** @var Card $card */
		foreach ($cards as $key => $card)
		{
			$cards[$key] = $card->toString();
		}
		return $cards;
	}

	/**
	 * @return array
	 */
	public function allToHumanString(): array
	{
		$cards = $this->toArray();
		/** @var Card $card */
		foreach ($cards as $key => $card)
		{
			$cards[$key] = $card->toHumanString();
		}
		return $cards;
	}

	/**
	 * @return string[]
	 */
	public function formatAll()
	{
		$cards = $this->toArray();
		/** @var Card $card */
		foreach ($cards as $key => $card)
		{
			$cards[$key] = $card->format();
		}
		return $cards;
	}

	/**
	 * @param string $card
	 *
	 * @return Card|false
	 */
	public function findCardByString(string $card)
	{
		return $this->find(function (Card $deckCard) use ($card)
		{
			return $deckCard->toString() == $card;
		});
	}

	/**
	 * @param Card $card
	 *
	 * @return Card|false
	 */
	public function findCard(Card $card)
	{
		return $this->find(function (Card $deckCard) use ($card)
		{
			return $deckCard == $card;
		});
	}

	/**
	 * @param Card $card
	 *
	 * @return bool
	 */
	public function removeCard(Card $card)
	{
		return $this->remove(function (Card $card1) use ($card)
		{
			return $card1 == $card;
		});
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