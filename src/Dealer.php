<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use ValidationClosures\Utils as ValUtils;

class Dealer
{
	//@formatter:off
	public static $validCards = [
		'b0', 'b1', 'b1', 'b2', 'b2', 'b3', 'b3', 'b4', 'b4', 'b5', 'b5', 'b6', 'b6', 'b7', 'b7', 'b8', 'b8', 'b9', 'b9', 'bs', 'bs', 'br', 'br', 'bd', 'bd', // Blue stack
		'r0', 'r1', 'r1', 'r2', 'r2', 'r3', 'r3', 'r4', 'r4', 'r5', 'r5', 'r6', 'r6', 'r7', 'r7', 'r8', 'r8', 'r9', 'r9', 'rs', 'rs', 'rr', 'rr', 'rd', 'rd', // red stack
		'y0', 'y1', 'y1', 'y2', 'y2', 'y3', 'y3', 'y4', 'y4', 'y5', 'y5', 'y6', 'y6', 'y7', 'y7', 'y8', 'y8', 'y9', 'y9', 'ys', 'ys', 'yr', 'yr', 'yd', 'yd', // Yellow stack
		'g0', 'g1', 'g1', 'g2', 'g2', 'g3', 'g3', 'g4', 'g4', 'g5', 'g5', 'g6', 'g6', 'g7', 'g7', 'g8', 'g8', 'g9', 'g9', 'gs', 'gs', 'gr', 'gr', 'gd', 'gd', // Green stack
		'w', 'w', 'wd', 'wd', 'w', 'w', 'wd', 'wd', // Wild cards
	];
	//@formatter:on

	/**
	 * @var Deck
	 */
	protected $availableCards;

	/**
	 * Dealer constructor.
	 */
	public function __construct()
	{
		$this->availableCards = new Deck();
		
		foreach (self::$validCards as $card)
			$this->availableCards->append(new Card($card[0], $card[1] ?? ''));
	}

	/**
	 * @param int $amount
	 * @param bool $excludeWild
	 *
	 * @return Card[]
	 */
	public function draw($amount = 1, $excludeWild = false): array
	{
		$available = $this->availableCards;
		if ($amount > $available->count())
			$amount = $available->count();

		if ($excludeWild)
			$available = $available->filter(ValUtils::invert(DeckFilters::color(CardTypes::WILD)));

		$cardKeys = array_rand((array) $available, $amount);

		if (!is_array($cardKeys))
			$cardKeys = [$cardKeys];

		$cards = [];
		foreach ($cardKeys as $cardKey)
		{
			$card = $available[$cardKey];
			$this->availableCards->removeFirst($card);
			$cards[] = $card;
		}
		return $cards;
	}

	/**
	 * @param Participants $participants
	 */
	public function repile(Participants $participants)
	{
		$deckCards = [];
		/** @var Participant $participant */
		foreach ($participants as $participant)
		{
			$deckCards = array_merge($deckCards, $participant->getDeck()
				->allToString());
		}

		$allCards = self::$validCards;		
		$newDeck = [];
		$lookup = array_count_values($deckCards);
		foreach ($allCards as $card)
		{
			if ($lookup[$card] ?? 0)
			{
				$lookup[$card]--;
			}
			else
			{
				$newDeck[] = Card::fromString($card);
			}
		}
		$this->availableCards->exchangeArray($newDeck);
	}

	/**
	 * @param int $amount
	 *
	 * @return bool
	 */
	public function canDraw(int $amount): bool
	{
		return count($this->availableCards) >= $amount;
	}

	/**
	 * @param Deck $deck
	 * @param int $amount
	 *
	 * @return Card[]
	 */
	public function populate(Deck $deck, int $amount = 10)
	{
		$cards = $this->draw($amount);
		foreach ($cards as $card)
			$deck->append($card);
		return $cards;
	}
}