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

namespace WildPHP\Modules\Uno;

use Collections\Collection;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Connection\IRCMessages\PART;
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
		'w', 'w', 'wd', 'wd', 'w', 'w', 'wd', 'wd', // Wild cards
		'g', 'r', 'y', 'b' // Cards used when a color is chosen.
	];
	//@formatter:on

	/**
	 * @var Deck
	 */
	protected $availableCards;

	/**
	 * @var bool
	 */
	protected $started = false;

	/**
	 * @var ComponentContainer
	 */
	protected $container = null;

	/**
	 * @var Participants
	 */
	protected $participants;

	/**
	 * @var bool
	 */
	protected $isReversed = false;

	/**
	 * @var Card
	 */
	protected $lastCard;

	/**
	 * @var Participant
	 */
	protected $currentPlayer;

	/**
	 * @var bool
	 */
	protected $currentPlayerHasDrawn = false;

	/**
	 * @var false|Participant
	 */
	protected $playerMustChooseColor = false;

	/**
	 * Game constructor.
	 *
	 * @param ComponentContainer $container
	 */
	public function __construct(ComponentContainer $container)
	{
		$this->setContainer($container);

		$this->participants = new Participants();
		$this->availableCards = new Deck();

		foreach (self::$validCards as $validCard)
		{
			$color = $validCard[0];
			$type = $validCard[1] ?? '';

			$cardObject = new Card($color, $type);
			$this->availableCards->add($cardObject);
		}
	}

	/**
	 * @param User $user
	 *
	 * @return Participant
	 */
	public function createParticipant(User $user): Participant
	{
		if ($this->isUserParticipant($user))
			return $this->findParticipantForUser($user);

		$deck = new Deck();
		$participantObject = new Participant($user, $deck);
		$this->populateDeckForParticipant($participantObject,10);
		$this->participants->add($participantObject);
		return $participantObject;
	}

	/**
	 * @param User $user
	 *
	 * @return Participant|false
	 */
	public function findParticipantForUser(User $user)
	{
		return $this->participants->find(function (Participant $participant) use ($user)
		{
			return $participant->getUserObject() === $user;
		});
	}

	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	public function isUserParticipant(User $user): bool
	{
		return !empty($this->findParticipantForUser($user));
	}

	/**
	 * @param string $card
	 *
	 * @return Card|false
	 */
	public function findAvailableCard(string $card)
	{
		return $this->availableCards->find(function (Card $availableCard) use ($card)
		{
			return $availableCard->toString() == $card;
		});
	}

	/**
	 * @param Deck $deck
	 * @param string|Card $card
	 * @param string $message
	 *
	 * @return bool
	 */
	public function playCard(Deck $deck, Card $card, string &$message): bool
	{
		$currentParticipant = $this->getCurrentPlayer();
		$nextParticipant = $this->getNextPlayer();
		$this->lastCard = $card;
		$deck->removeCard($card);
		$color = $card->getColor();
		$type = $card->getType();

		if ($color == CardTypes::WILD)
			$this->setPlayerMustChooseColor($currentParticipant);

		// When a color was chosen, don't bother to process anything else.
		if (empty($type))
			return true;

		switch ($type)
		{
			case CardTypes::REVERSE:
				if (count($this->participants) > 2)
					$message = 'The order of players has been reversed';
				else
				{
					$this->advance();
					$message = $nextParticipant->getUserObject()
							->getNickname() . ' skipped a turn (two-player game)';
				}

				$this->reverse();
				break;

			case CardTypes::DRAW:
				$amount = $color == CardTypes::WILD ? 4 : 2;

				$cards = $this->populateDeckForParticipant($nextParticipant, $amount);
				$message = $nextParticipant->getUserObject()->getNickname() . ' drew ' . $amount . ' cards and skipped a turn';
				EventEmitter::fromContainer($this->getContainer())->emit('uno.deck.populate', [$nextParticipant, $cards]);
				$this->advance();
				break;

			case 's':
				$this->advance();
				$message = $nextParticipant->getUserObject()->getNickname() . ' skipped a turn';
				break;
		}
		return true;
	}

	public function reverse()
	{
		$this->isReversed = !$this->isReversed;
	}

	/**
	 * @param int $amount
	 * @param bool $excludeWild
	 *
	 * @return Card[]
	 */
	public function drawRandomCard($amount = 1, $excludeWild = false): array
	{
		$available = $this->availableCards->toArray();
		if ($amount > count($available))
			$amount = count($available);

		$available = array_filter($available, function (Card $card) use ($excludeWild)
		{
			return ($excludeWild && $card->getColor() != 'w') || !in_array($card->toString(), ['r', 'g', 'b', 'y']);
		});

		$cardKeys = array_rand($available, $amount);

		if (!is_array($cardKeys))
			$cardKeys = [$cardKeys];

		$cards = [];
		foreach ($cardKeys as $cardKey)
		{
			$card = $available[$cardKey];
			$this->availableCards->removeCard($card);
			$cards[] = $card;
		}
		Logger::fromContainer($this->getContainer())->debug('Picked cards', ['cards' => $cards]);
		return $cards;
	}

	/**
	 * @param Participant $participant
	 *
	 * @return bool
	 */
	public function participantHasLegalMoves(Participant $participant)
	{
		return !empty($this->deckGetValidCards($participant->getDeck()));
	}

	/**
	 * @param Deck $deck
	 *
	 * @return array
	 */
	public function deckGetValidCards(Deck $deck): array
	{
		if (empty($card))
			$card = $this->lastCard;

		if (empty($card))
			return [];

		$cards = [];
		/** @var Card $deckCard */
		foreach ($deck->toArray() as $deckCard)
		{
			if (!$deckCard->compatible($this->getLastCard()))
				continue;

			$cards[] = $deckCard;
		}
		return $cards;
	}

	/**
	 * @param Participant $participant
	 * @param int $amount
	 *
	 * @return mixed
	 */
	public function populateDeckForParticipant(Participant $participant, int $amount = 10)
	{
		$deck = $participant->getDeck();
		$cards = $this->drawRandomCard($amount);
		$deck->addRange($cards);
		return $cards;
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
	public function isReversed(): bool
	{
		return $this->isReversed;
	}

	/**
	 * @param bool $isReversed
	 */
	public function setIsReversed(bool $isReversed)
	{
		$this->isReversed = $isReversed;
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

	/**
	 * @return Card
	 */
	public function getLastCard(): Card
	{
		return $this->lastCard;
	}

	/**
	 * @param Card $lastCard
	 */
	public function setLastCard(Card $lastCard)
	{
		$this->lastCard = $lastCard;
	}

	/**
	 * @return Participant
	 */
	public function getCurrentPlayer(): Participant
	{
		return current($this->getParticipants()->toArray());
	}

	/**
	 * @return Participant
	 */
	public function getNextPlayer(): Participant
	{
		$participants = $this->getParticipants()->toArray();

		if (!$this->isReversed())
		{
			$result = next($participants);
			if (!$result)
				$result = reset($participants);
		}
		else
		{
			$result = prev($participants);
			if (!$result)
				$result = end($participants);
		}
		return $result;
	}

	/**
	 * @return Participant
	 */
	public function getPreviousPlayer(): Participant
	{
		$participants = $this->getParticipants()->toArray();

		if ($this->isReversed())
		{
			$result = next($participants);
			if (!$result)
				$result = reset($participants);
		}
		else
		{
			$result = prev($participants);
			if (!$result)
				$result = end($participants);
		}
		return $result;
	}

	/**
	 * @return Participant
	 */
	public function advance(): Participant
	{
		$participants = &$this->getParticipants()->toArray();

		if (!$this->isReversed())
		{
			$result = next($participants);
			if (!$result)
				$result = reset($participants);
		}
		else
		{
			$result = prev($participants);
			if (!$result)
				$result = end($participants);
		}
		return $result;
	}

	/**
	 * @return Participant
	 */
	public function rewind(): Participant
	{
		$participants = &$this->getParticipants()->toArray();

		if ($this->isReversed())
		{
			$result = next($participants);
			if (!$result)
				$result = reset($participants);
		}
		else
		{
			$result = prev($participants);
			if (!$result)
				$result = end($participants);
		}
		return $result;
	}

	/**
	 * @return bool
	 */
	public function currentPlayerHasDrawn(): bool
	{
		return $this->currentPlayerHasDrawn;
	}

	/**
	 * @param bool $currentPlayerHasDrawn
	 */
	public function setCurrentPlayerHasDrawn(bool $currentPlayerHasDrawn)
	{
		$this->currentPlayerHasDrawn = $currentPlayerHasDrawn;
	}

	/**
	 * @return bool|Participant
	 */
	public function playerMustChooseColor()
	{
		return $this->playerMustChooseColor;
	}

	/**
	 * @param Participant|false $playerMustChooseColor
	 */
	public function setPlayerMustChooseColor($playerMustChooseColor)
	{
		$this->playerMustChooseColor = $playerMustChooseColor;
	}

	/**
	 * @return Collection
	 */
	public function getParticipants()
	{
		return $this->participants;
	}
}