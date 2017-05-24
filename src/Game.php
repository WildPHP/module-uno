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
 * Time: 20:26
 */

namespace WildPHP\Modules\Uno;

use WildPHP\Core\ComponentContainer;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Logger\Logger;
use WildPHP\Core\Users\User;

class Game
{
	//@formatter:off
	public static $validCards = [
		'b0', 'b1', 'b1', 'b2', 'b2', 'b3', 'b3', 'b4', 'b4', 'b5', 'b5', 'b6', 'b6', 'b7', 'b7', 'b8', 'b8', 'b9', 'b9', 'bs', 'bs', 'br', 'br', 'bd', 'bd', // Blue stack
		'r0', 'r1', 'r1', 'r2', 'r2', 'r3', 'r3', 'r4', 'r4', 'r5', 'r5', 'r6', 'r6', 'r7', 'r7', 'r8', 'r8', 'r9', 'r9', 'rs', 'rs', 'rr', 'rr', 'rd', 'rd', // red stack
		'y0', 'y1', 'y1', 'y2', 'y2', 'y3', 'y3', 'y4', 'y4', 'y5', 'y5', 'y6', 'y6', 'y7', 'y7', 'y8', 'y8', 'y9', 'y9', 'ys', 'ys', 'yr', 'yr', 'yd', 'yd', // Yellow stack
		'g0', 'g1', 'g1', 'g2', 'g2', 'g3', 'g3', 'g4', 'g4', 'g5', 'g5', 'g6', 'g6', 'g7', 'g7', 'g8', 'g8', 'g9', 'g9', 'gs', 'gs', 'gr', 'gr', 'gd', 'gd', // Green stack
		'w', 'w', 'wd', 'wd', 'w', 'w', 'wd', 'wd' // Wild cards
	];
	//@formatter:on

	protected $availableCards = [];

	protected $started = false;

	protected $channel = '';
	protected $container = null;

	protected $participants = [];
	protected $isReversed = false;

	protected $lastCard = '';

	protected $waitingOn = null;
	protected $waitingReason = '';

	protected $drawn = false;

	public function __construct(ComponentContainer $container)
	{
		$this->availableCards = self::$validCards;
		$this->setContainer($container);
	}

	public function addParticipant(User $user)
	{
		if ($this->isParticipant($user))
			return;

		$deck = new Deck();
		$this->populateDeck($deck);
		$this->participants[$user->getNickname()] = $deck;
	}

	public function isParticipant(User $user)
	{
		return array_key_exists($user->getNickname(), $this->participants);
	}

	// !!! HACK
	public function setColor(string $color)
	{
		$this->lastCard = $color;
		$this->waitingOn = null;
		$this->waitingReason = '';
	}

	public function playCard(User $user, string $card)
	{
		if (!$this->isStarted() && !$this->isParticipant($user) || $this->waitingOn)
			return false;

		$deck = $this->getDeckForUser($user);

		if (!$deck->containsCard($card) || !$this->cardIsCompatible($this->lastCard, $card))
			return false;

		// Consider it played since we passed all validation.
		$this->lastCard = $card;
		$deck->removeCard($card);

		$color = $card[0];
		$type = $card[1] ?? '';

		$message = '';
		if ($color == 'w')
			$this->playerMustColor($user->getNickname());

		switch ($type)
		{
			case 'r':
				if (count($this->participants) > 2)
				{
					$this->reverse();
					$message = 'The order of players has been reversed';
				}
				else
				{
					$nextPlayerDeck = $this->getDeckForNextParticipant();
					$nickname = $this->getNicknameForDeck($nextPlayerDeck);
					$message = $nickname . ' skipped a turn (two-player game)';
				}

				break;

			case 'd':
				$amount = $color == 'w' ? 4 : 2;
				$nextPlayerDeck = $this->getDeckForNextParticipant();
				$this->populateDeck($nextPlayerDeck, $amount);
				$nickname = $this->getNicknameForDeck($nextPlayerDeck);
				$message = $nickname . ' drew ' . $amount . ' cards and skipped a turn';
				break;

			case 's':
				$nextPlayerDeck = $this->getDeckForNextParticipant();
				$nickname = $this->getNicknameForDeck($nextPlayerDeck);
				$message = $nickname . ' skipped a turn';
				break;
		}
		return $message;
	}

	public function getReadableCardFormat(string $card)
	{
		$color = $card[0];
		$type = $card[1] ?? '';

		if ($color == 'w' && $type == 'd')
			return 'Wild Draw Four';

		$color = CardTypes::COLORS[$color];

		if (!empty($type) && !is_numeric($type))
			$type = CardTypes::TYPES[$type];

		return trim($color . ' ' . $type);
	}

	public function getDeckForPreviousParticipant()
	{
		if (!$this->isReversed)
		{
			$result = prev($this->participants);
			if (!$result)
				end($this->participants);
		}
		else
		{
			$result = next($this->participants);
			if (!$result)
				reset($this->participants);
		}
		return $this->getCurrentParticipant();
	}

	public function getDeckForNextParticipant()
	{
		if (!$this->isReversed)
		{
			$result = next($this->participants);
			if (!$result)
				reset($this->participants);
		}
		else
		{
			$result = prev($this->participants);
			if (!$result)
				end($this->participants);
		}
		return $this->getCurrentParticipant();
	}

	public function getCurrentParticipant()
	{
		return current($this->participants);
	}

	public function getNicknameForDeck(Deck $deck)
	{
		return array_search($deck, $this->participants);
	}

	public function reverse()
	{
		$this->isReversed = !$this->isReversed;
	}

	/**
	 * @param User $user
	 *
	 * @return Deck|null
	 */
	public function getDeckForUser(User $user)
	{
		if (!$this->isParticipant($user))
			return null;

		return $this->participants[$user->getNickname()];
	}


	public function pickRandomCard($amount = 1, $excludeWild = false): array
	{
		$cards = [];
		if ($amount > count($this->availableCards))
			$amount = count($this->availableCards);

		$available = $this->availableCards;
		var_dump($available);

		if ($excludeWild)
			$available = array_filter($available, function ($card)
			{
				return $card[0] != 'w';
			});

		$keys = array_rand($available, $amount);
		if (!is_array($keys))
			$keys = [$keys];

		foreach ($keys as $key)
		{
			$cards[] = $this->availableCards[$key];
			unset($this->availableCards[$key]);
		}
		Logger::fromContainer($this->getContainer())->debug('Picked cards', ['cards' => $cards]);
		return $cards;
	}

	public function userHasLegalMoves(User $user)
	{
		$deck = $this->getDeckForUser($user);

		if ($deck == null)
			return false;

		$hasLegalMoves = false;
		foreach ($deck->getCards() as $card)
		{
			if (!$this->cardIsCompatible($this->lastCard, $card))
				continue;

			$hasLegalMoves = true;
			break;
		}

		return $hasLegalMoves;
	}

	public function deckGetValidCards(Deck $deck, string $card = '')
	{
		if (empty($card))
			$card = $this->lastCard;

		if (empty($card))
			return [];

		$cards = [];
		foreach ($deck->getCards() as $deckCard)
		{
			if (!$this->cardIsCompatible($card, $deckCard))
				continue;

			$cards[] = $deckCard;
		}
		return $cards;
	}

	public function populateDeck(Deck $deck, int $amount = 10)
	{
		$cards = $this->pickRandomCard($amount);
		$deck->addCards($cards);
		EventEmitter::fromContainer($this->getContainer())->emit('uno.populated', [$deck, $this, $cards]);
		return $cards;
	}

	public function cardIsCompatible(string $card1, string $card2)
	{
		$card1 = strtolower($card1);
		$card2 = strtolower($card2);
		if ($card1 == $card2)
			return true;

		$color1 = $card1[0];
		$color2 = $card2[0];

		if ($color1 == 'w' || $color2 == 'w' || $color1 == $color2)
			return true;

		$number1 = $card1[1];
		$number2 = $card2[1];

		return $number1 == $number2;
	}

	/**
	 * @return null
	 */
	public function getContainer()
	{
		return $this->container;
	}

	/**
	 * @param null $container
	 */
	public function setContainer($container)
	{
		$this->container = $container;
	}

	/**
	 * @return bool
	 */
	public function isStarted(): bool
	{
		return $this->started;
	}

	/**
	 * @param bool $started
	 */
	public function setStarted(bool $started)
	{
		$this->started = $started;
	}

	public function getLastCard()
	{
		return $this->lastCard;
	}

	public function setLastCard(string $card)
	{
		$this->lastCard = $card;
	}

	protected function playerMustColor(string $nickname)
	{
		$this->waitingReason = 'color';
		$this->waitingOn = $nickname;
	}

	public function waitingOnPlayerColor()
	{
		return $this->waitingReason == 'color' ? $this->waitingOn : '';
	}

	/**
	 * @return bool
	 */
	public function isDrawn(): bool
	{
		return $this->drawn;
	}

	/**
	 * @param bool $drawn
	 */
	public function setDrawn(bool $drawn)
	{
		$this->drawn = $drawn;
	}
}