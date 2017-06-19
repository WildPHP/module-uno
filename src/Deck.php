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