<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;

use WildPHP\Core\Channels\Channel;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\ContainerTrait;
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
	 * @var TimeoutController
	 */
	private $timeoutController;
	/**
	 * @var Channel
	 */
	private $channel;
	/**
	 * @var HighScores
	 */
	private $highScores;

	/**
	 * Game constructor.
	 *
	 * @param ComponentContainer $container
	 * @param TimeoutController $timeoutController
	 * @param Channel $channel
	 * @param HighScores $highScores
	 */
	public function __construct(ComponentContainer $container, TimeoutController $timeoutController, Channel $channel, HighScores $highScores)
	{
		$this->setContainer($container);

		$this->participants = new Participants();
		$this->dealer = new Dealer();
		$this->timeoutController = $timeoutController;
		$this->channel = $channel;
		$this->highScores = $highScores;
	}

	public function start()
	{
		$this->playerOrder = new PlayerOrder($this->participants);
		$this->started = true;
		$this->startTime = time();
		$this->lastCard = $this->dealer->draw(1, true)[0];
		Logger::fromContainer($this->getContainer())->debug('[UNO] Game started up');
		
		Queue::fromContainer($this->getContainer())->privmsg(
			$this->channel->getName(),
			sprintf('Game for channel %s started with %d participants!',
				$this->channel->getName(),
				$this->participants->count()
			)
		);
	}

	public function stop()
	{
		$this->getTimeoutController()->resetTimers();
		$this->started = false;
		Logger::fromContainer($this->getContainer())->debug('[UNO] Game stopped');
		Queue::fromContainer($this->getContainer())->privmsg(
			$this->channel->getName(),
			sprintf('Game for channel %s stopped.',
				$this->channel->getName()
			)
		);
	}

	/**
	 * @return array
	 */
	public function draw(): array
	{
		$participant = $this->getPlayerOrder()->getCurrent();

		if (!$this->getDealer()->canDraw(1))
		{
			$this->getDealer()->repile($this->getParticipants());
			Queue::fromContainer($this->getContainer())
				->privmsg($this->channel->getName(), 'No more cards in deck; all previously played cards repiled.');
		}

		$cards = $this->getDealer()->populate($participant->getDeck(), 1);

		Queue::fromContainer($this->getContainer())
			->privmsg($this->channel->getName(), $participant->getUserObject()->getNickname() . ' drew a card.');

		$this->setCurrentPlayerHasDrawn(true);
		return $cards;
	}
	
	public function pass()
	{
		$participant = $this->getPlayerOrder()->getCurrent();
		Queue::fromContainer($this->getContainer())
			->privmsg($this->channel->getName(), $participant->getUserObject()->getNickname() . ' passed a turn.');
	}

	/**
	 * @return TimeoutController
	 */
	public function getTimeoutController(): TimeoutController
	{
		return $this->timeoutController;
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
		Announcer::announceCurrentTurn($this, $this->channel, Queue::fromContainer($this->getContainer()));
		return $nextPlayer;
	}

	/**
	 * @return Participant
	 */
	public function advanceNotify(): Participant
	{
		$nextParticipant = $this->advance();

		$ownNickname = Configuration::fromContainer($this->getContainer())['currentNickname'];

		if ($nextParticipant->getUserObject()->getNickname() == $ownNickname)
			BotParticipant::playAutomaticCard($this, $this->channel, Queue::fromContainer($this->getContainer()));
		elseif (!$this->channel->getUserCollection()->contains($nextParticipant->getUserObject()))
			BotParticipant::playAutomaticCardAndNotice($this, $this->channel, Queue::fromContainer($this->getContainer()));
		else
		{
			Announcer::noticeCards($nextParticipant, Queue::fromContainer($this->getContainer()));
			$this->getTimeoutController()->setTimer($this, $this->channel);
		}

		return $nextParticipant;
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
	 * @param Card $card
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
				$message = sprintf(GameResponses::DRAW_PLAYED, '%s', $amount);

				if (!$this->getDealer()->canDraw(1))
				{
					$this->getDealer()->repile($this->getParticipants());
					$message = sprintf(GameResponses::DRAW_PLAYED_REPILE, '%s', $amount);
				}

				$cards = $this->dealer->populate($nextParticipant->getDeck(), $amount);
				
				Announcer::notifyNewCards($nextParticipant, $cards, Queue::fromContainer($this->getContainer()));
				
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
	 * @param Card $card
	 * @param Participant $participant
	 *
	 * @return bool Suggestion whether the game should announce the next player.
	 */
	public function playCardWithChecks(Card $card, Participant $participant)
	{
		$deck = $participant->getDeck();
		$reverseState = $this->getPlayerOrder()->isReversed();
		$nickname = $participant->getUserObject()->getNickname();

		$response = $this->playCard($deck, $card);

		$friendlyCardName = $card->toHumanString();
		$formattedCard = $card->format();
		$message = sprintf('%s played %s (%s)',
			$nickname,
			$friendlyCardName,
			$formattedCard
		);

		if (!empty($response) && $response !== true)
			$message .= ' ' . $response;

		Queue::fromContainer($this->getContainer())
			->privmsg($this->channel->getName(), $message);

		if (count($deck) == 0)
		{
			$this->win($participant);
			return false;
		}

		if ($reverseState != $this->getPlayerOrder()->isReversed())
			Announcer::announceOrder($this, $this->channel, Queue::fromContainer($this->getContainer()));

		if (count($deck) == 1)
			Queue::fromContainer($this->getContainer())
				->privmsg($this->channel->getName(), $participant->getUserObject()->getNickname() . ' has UNO!');

		if (($colorChoosingParticipant = $this->getPlayerMustChooseColor()))
		{
			Queue::fromContainer($this->getContainer())
				->privmsg($this->channel->getName(), 
					sprintf(
					'%s must now choose a color (r/g/b/y) (choose using the color command)',
					$colorChoosingParticipant->getUserObject()->getNickname()
					)
				);

			return false;
		}

		return true;
	}

	/**
	 * @param Participant $participant
	 */
	public function win(Participant $participant)
	{
		$nickname = $participant->getUserObject()->getNickname();
		
		Queue::fromContainer($this->getContainer())
			->privmsg($this->channel->getName(), $nickname . ' has played their last card and won! GG!');

		$participants = $this->getParticipants();
		/** @var Participant $participant */
		$points = 0;
		foreach ($participants as $participant)
		{
			$deck = $participant->getDeck();
			$points += $this->highScores->calculatePoints($deck);
			$cards = $deck->formatAll();
			if (empty($cards))
				continue;
			Queue::fromContainer($this->getContainer())
				->privmsg($this->channel->getName(), sprintf('%s ended with these cards: %s',
					$participant->getUserObject()->getNickname(),
					implode(', ', $cards)
				));
		}
		$isHigher = $this->highScores->updateHighScore($nickname, $points);
		Queue::fromContainer($this->getContainer())
			->privmsg($this->channel->getName(), 'Altogether, ' . $nickname . ' has earned ' . $points . ' points this match.' . ($isHigher ? ' New high score!' : ''));

		$this->stop();
	}

	/**
	 * @return Dealer
	 */
	public function getDealer(): Dealer
	{
		return $this->dealer;
	}

	/**
	 * @return Participants
	 */
	public function getParticipants()
	{
		return $this->participants;
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
}