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

class Deck extends Collection
{
	protected $allowColors = true;

	/**
	 * Deck constructor.
	 */
	public function __construct()
	{
		parent::__construct(Types::instanceof(Card::class));
	}

	public function sortCards()
	{
		$array = $this->getArrayCopy();
		usort($array, function (Card $card1, Card $card2)
		{
			if ($card1->toString() == $card2->toString())
				return 0;

			return ($card1->toString() < $card2->toString()) ? -1 : 1;
		});
		$this->exchangeArray($array);
	}

	/**
	 * @return array
	 */
	public function allToString(): array
	{
		$cards = (array) $this;
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
		$cards = (array) $this;
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
		$cards = (array) $this;
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
		$filtered = $this->filter(function (Card $deckCard) use ($card)
		{
			return $deckCard->toString() == $card;
		});
		
		if (!empty((array) $filtered))
			return reset($filtered);
		
		return false;
	}

	/**
	 * @param Card $card
	 *
	 * @return Card|false
	 */
	public function findCard(Card $card)
	{
		return $this->findCardByString($card->toString());
	}

	/**
	 * @param Card $card
	 *
	 * @return bool
	 */
	public function removeCard(Card $card)
	{
		return $this->removeAll(function (Card $card1) use ($card)
		{
			/** @noinspection PhpNonStrictObjectEqualityInspection */
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