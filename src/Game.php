<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;

use WildPHP\Core\ComponentContainer;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Logger\Logger;
use WildPHP\Core\Users\User;

class Game
{
	use ContainerTrait;
	/**
	 * @var bool
	 */
	protected $started = false;

	/**
	 * @var Participants
	 */
	protected $participants;

	/**
	 * @var Card
	 */
	protected $lastCard;

	/**
	 * @var bool
	 */
	protected $currentPlayerHasDrawn = false;

	/**
	 * @var false|Participant
	 */
	protected $playerMustChooseColor = false;

	/**
	 * @var int
	 */
	protected $startTime = 0;

	/**
	 * @var PlayerOrder
	 */
	protected $playerOrder;

	/**
	 * @var Dealer
	 */
	protected $dealer;

	/**
	 * Game constructor.
	 *
	 * @param ComponentContainer $container
	 */
	public function __construct(ComponentContainer $container)
	{
		$this->setContainer($container);

		$this->participants = new Participants();
		$this->dealer = new Dealer();
	}

	public function start()
	{
		$this->playerOrder = new PlayerOrder($this->participants);
		$this->started = true;
		$this->startTime = time();
		$this->lastCard = $this->dealer->draw(1, true)[0];
		Logger::fromContainer($this->getContainer())->debug('[UNO] Game started up');
	}

	public function stop()
	{
		$this->started = false;
		Logger::fromContainer($this->getContainer())->debug('[UNO] Game stopped');
	}

	/**
	 * @return Participant
	 */
	public function advance()
	{
		$this->playerMustChooseColor = false;
		$this->currentPlayerHasDrawn = false;
		$nextPlayer =$this->playerOrder->advance();
		
		Logger::fromContainer($this->getContainer())->debug('[UNO] Advancing game; next player is ' . $nextPlayer->getUserObject()->getNickname());
		return $nextPlayer;
	}

	/**
	 * @param User $user
	 *
	 * @return Participant
	 */
	public function createParticipant(User $user): Participant
	{
		if ($this->participants->includes($user))
			return $this->participants->findForUser($user);

		$deck = new Deck();
		$participantObject = new Participant($user, $deck);
		$this->dealer->populate($deck,10);
		$this->participants->append($participantObject);
		
		Logger::fromContainer($this->getContainer())->debug('[UNO] Adding participant ' . $participantObject->getUserObject()->getNickname());
		return $participantObject;
	}
	
	/**
	 * @param Deck $deck
	 * @param string|Card $card
	 *
	 * @return bool|string
	 */
	public function playCard(Deck $deck, Card $card)
	{
		$currentParticipant = $this->playerOrder->getCurrent();
		$nextParticipant = $this->playerOrder->getNext();
		
		$this->lastCard = $card;
		$deck->removeFirst($card);
		$color = $card->getColor();
		$type = $card->getType();

		if ($color == CardTypes::WILD)
			$this->playerMustChooseColor = $currentParticipant;

		// When a color was chosen (or w was played), don't bother to process anything else.
		if (empty($type))
			return true;

		$message = '';
		switch ($type)
		{
			case CardTypes::REVERSE:
				if (count($this->participants) > 2)
				{
					$this->playerOrder->reverse();
					$message = GameResponses::REVERSED;
				}
				else
				{
					$this->playerOrder->advance();
					$message = GameResponses::SKIPPED_TWOPLAYER;
				}
				break;

			case CardTypes::DRAW:
				$amount = $color == CardTypes::WILD ? 4 : 2;

				$cards = $this->dealer->populate($nextParticipant->getDeck(), $amount);
				$message = sprintf(GameResponses::DRAW_PLAYED, '%s', $amount);
				
				EventEmitter::fromContainer($this->getContainer())->emit('uno.deck.populate', [$nextParticipant, $cards]);
				
				$this->playerOrder->advance();
				break;

			case CardTypes::SKIP:
				$this->playerOrder->advance();
				$message = GameResponses::SKIPPED;
				break;
		}
		
		return sprintf($message, $nextParticipant->getUserObject()->getNickname());
	}

	/**
	 * @return bool
	 */
	public function isStarted(): bool
	{
		return $this->started;
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
	 * @return false|Participant
	 */
	public function getPlayerMustChooseColor()
	{
		return $this->playerMustChooseColor;
	}

	/**
	 * @return Card
	 */
	public function getLastCard(): Card
	{
		return $this->lastCard;
	}

	/**
	 * @return Participants
	 */
	public function getParticipants()
	{
		return $this->participants;
	}

	/**
	 * @return int
	 */
	public function getStartTime(): int
	{
		return $this->startTime;
	}

	/**
	 * @return PlayerOrder
	 */
	public function getPlayerOrder(): PlayerOrder
	{
		return $this->playerOrder;
	}

	/**
	 * @return Dealer
	 */
	public function getDealer(): Dealer
	{
		return $this->dealer;
	}
}