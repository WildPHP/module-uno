<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;

use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Commands\CommandHandler;
use WildPHP\Core\Commands\CommandHelp;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\Connection\TextFormatter;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Modules\BaseModule;
use WildPHP\Core\Users\User;

class Uno extends BaseModule
{
	use ContainerTrait;

	/**
	 * @var array
	 */
	protected $games = [];

	/**
	 * Uno constructor.
	 *
	 * @param ComponentContainer $container
	 */
	public function __construct(ComponentContainer $container)
	{
		$commandHelp = new CommandHelp();
		$commandHelp->append('Initiates a new game of UNO. No arguments.');
		CommandHandler::fromContainer($container)
			->registerCommand('newgame', [$this, 'newgameCommand'], $commandHelp, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->append('Starts the initiated game of UNO. Use after all participants have joined. No arguments.');
		CommandHandler::fromContainer($container)
			->registerCommand('start', [$this, 'startCommand'], $commandHelp, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->append('Instruct the bot to join the current running game.');
		CommandHandler::fromContainer($container)
			->registerCommand('botenter', [$this, 'botenterCommand'], $commandHelp, 0, 0, 'botenter');

		$commandHelp = new CommandHelp();
		$commandHelp->append('Stops the running game of UNO.');
		CommandHandler::fromContainer($container)
			->registerCommand('stop', [$this, 'stopCommand'], $commandHelp, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->append('Enter as a participant in the running game of UNO.');
		CommandHandler::fromContainer($container)
			->registerCommand('enter', [$this, 'enterCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Pass your current turn.');
		CommandHandler::fromContainer($container)
			->registerCommand('pass', [$this, 'passCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Play a card. Usage: play [card]');
		CommandHandler::fromContainer($container)
			->registerCommand('play', [$this, 'playCommand'], $commandHelp, 1, 1);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Choose a color. Usage: color [color]');
		CommandHandler::fromContainer($container)
			->registerCommand('color', [$this, 'colorCommand'], $commandHelp, 1, 1);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Draw a card from the stack.');
		CommandHandler::fromContainer($container)
			->registerCommand('draw', [$this, 'drawCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Show your current cards.');
		CommandHandler::fromContainer($container)
			->registerCommand('cards', [$this, 'cardsCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Toggle the displaying of colors in your private messages for the current session.');
		CommandHandler::fromContainer($container)
			->registerCommand('togglecolors', [$this, 'togglecolorsCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Show all available valid moves.');
		CommandHandler::fromContainer($container)
			->registerCommand('validmoves', [$this, 'validmovesCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: List basic game rules.');
		CommandHandler::fromContainer($container)
			->registerCommand('unorules', [$this, 'unorulesCommand'], $commandHelp, 0, 0);

		CommandHandler::fromContainer($container)
			->alias('play', 'pl');
		CommandHandler::fromContainer($container)
			->alias('pass', 'pa');
		CommandHandler::fromContainer($container)
			->alias('pass', 'ps');
		CommandHandler::fromContainer($container)
			->alias('draw', 'dr');
		CommandHandler::fromContainer($container)
			->alias('color', 'c');
		CommandHandler::fromContainer($container)
			->alias('validmoves', 'vm');
		CommandHandler::fromContainer($container)
			->alias('cards', 'lsc');

		EventEmitter::fromContainer($container)->on('uno.deck.populate', [$this, 'notifyNewCards']);

		$this->setContainer($container);
	}

	/**
	 * @param Participant $participant
	 * @param array $cards
	 */
	public function notifyNewCards(Participant $participant, array $cards)
	{
		$diff = new Deck();
		foreach ($cards as $card)
			$diff->append($card);
		$diff->sortCards();
		$nickname = $participant->getUserObject()->getNickname();

		if ($participant->getDeck()->colorsAllowed())
			$cards = $diff->formatAll();
		else
			$cards = $diff->allToString();

		$cards = implode(', ', $cards);
		Queue::fromContainer($this->getContainer())
			->notice($nickname, 'These cards were added to your deck: ' . $cards);
	}

	/**
	 * @param Participant $participant
	 */
	public function noticeCards(Participant $participant)
	{
		$deck = $participant->getDeck();
		$nickname = $participant->getUserObject()->getNickname();
		$deck->sortCards();

		if ($deck->colorsAllowed())
			$cards = $deck->formatAll();
		else
			$cards = $deck->allToString();

		$cards = implode(', ', $cards);

		Queue::fromContainer($this->getContainer())
			->notice($nickname, 'You have the following cards: ' . $cards);

		return;
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 *
	 * @return Participant
	 */
	public function advanceGame(Game $game, Channel $source): Participant
	{
		$nextParticipant = $game->advance();
		
		$ownNickname = Configuration::fromContainer($this->getContainer())['currentNickname'];
		
		$this->announceTurn($game, $source);
		
		if ($nextParticipant->getUserObject()->getNickname() == $ownNickname)
			$this->playAutomaticCard($game, $source);
		else
			$this->noticeCards($nextParticipant);
		
		return $nextParticipant;
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function announceTurn(Game $game, Channel $source)
	{
		if (!$game->isStarted())
			return;

		$nextParticipant = $game->getPlayerOrder()->getCurrent();
		$nickname = $nextParticipant->getUserObject()->getNickname();
		$lastCard = $game->getLastCard();
		$lastCardFormatted = $lastCard->format();
		$lastCardReadable = $lastCard->toHumanString();

		Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $nickname . ' is up! The current card is ' . $lastCardReadable . ' (' . $lastCardFormatted . ')');
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function playAutomaticCard(Game $game, Channel $source)
	{
		$participant = $game->getPlayerOrder()->getCurrent();
		$validCards = $participant->getDeck()->getValidCards($game->getLastCard());

		// Got no cards, but can still draw
		if (empty((array) $validCards) && !$game->currentPlayerHasDrawn())
		{
			$this->drawCardInGame($game, $source);
			$validCards = $participant->getDeck()
				->getValidCards($game->getLastCard());
		}
		
		// Got no cards, cannot draw again
		if (empty((array) $validCards) && $game->currentPlayerHasDrawn())
		{
			$this->passTurnInGame($game, $source);
			$this->advanceGame($game, $source);
			return;
		}

		// Sort cards from lowest to highest.
		$validCards->sortCards();
		
		$withoutWildsOrOtherColors = $validCards->filter(DeckFilters::color($game->getLastCard()->getColor()));
		if (!empty((array) $withoutWildsOrOtherColors))
		{
			$withoutWildsOrOtherColors = (array) $withoutWildsOrOtherColors;
			shuffle($withoutWildsOrOtherColors);
			$validCards = new Deck($withoutWildsOrOtherColors);
		}

		$card = reset($validCards);
		$result = $this->playCardInGame($game, $card, $participant, $source);

		// If we played the last card and won, stop.
		if (!$game->isStarted())
			return;

		// Must we choose a color?
		if (!$result && $game->getPlayerMustChooseColor())
		{
			$deck = $participant->getDeck();

			$count['g'] = count($deck->filter(DeckFilters::color(CardTypes::GREEN)));
			$count['b'] = count($deck->filter(DeckFilters::color(CardTypes::BLUE)));
			$count['y'] = count($deck->filter(DeckFilters::color(CardTypes::YELLOW)));
			$count['r'] = count($deck->filter(DeckFilters::color(CardTypes::RED)));

			$count = array_flip($count);
			ksort($count);
			$color = end($count);
			$color = new Card($color);
			$game->playCard($deck, $color);
			$readableColor = $color->toHumanString();
			$formattedColor = $color->format();
			Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $participant->getUserObject()->getNickname() . ' picked color ' . $readableColor . ' (' . $formattedColor . ')');
		}
		$this->advanceGame($game, $source);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function announceOrder(Game $game, Channel $source)
	{
		$participants = $game->getPlayerOrder()->toArray();

		/** @var Participant $participant */
		$nicknames = [];
		foreach ($participants as $participant)
		{
			$nicknames[] = TextFormatter::bold($participant->getUserObject()->getNickname());
		}

		$message = 'The current order is: ' . implode(' -> ', $nicknames);
		Queue::fromContainer($this->getContainer())
			->privmsg($source->getName(), $message);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function newgameCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if ($game && $game->isStarted())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game is already in progress in this channel.');

			return;
		}
		elseif ($game && !$game->isStarted())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has already been started but you can still join with the enter command!');

			return;
		}

		$game = new Game($container);
		$participant = $game->createParticipant($user);

		$prefix = Configuration::fromContainer($container)['prefix'];

		Queue::fromContainer($container)
			->privmsg($source->getName(),
				'A game has been opened and you have joined. Use ' . $prefix . 'enter to join, ' . $prefix . 'start to start the game.');

		$this->noticeCards($participant);
		$this->games[$source->getName()] = $game;
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function startCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has not been started in this channel.');

			return;
		}
		if ($game->isStarted())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game is already in progress in this channel.');

			return;
		}
		if (count($game->getParticipants()) == 1)
			$this->addBotPlayer($game, $source);

		$game->start();

		Queue::fromContainer($this->getContainer())
			->privmsg($source->getName(), 'Game started!');

		$this->announceTurn($game, $source);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function addBotPlayer(Game $game, Channel $source)
	{
		$ownNickname = Configuration::fromContainer($this->getContainer())['currentNickname'];
		$botObject = $source->getUserCollection()->findByNickname($ownNickname);
		$game->createParticipant($botObject);
		Queue::fromContainer($this->getContainer())->privmsg($source->getName(), 'I also entered the game. Brace yourself ;)');
		
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function stopCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has not been started in this channel.');

			return;
		}

		$game->stop();
		unset($this->games[$source->getName()]);
		Queue::fromContainer($this->getContainer())
			->privmsg($source->getName(), 'Game stopped.');
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function togglecolorsCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has not been started or you are not added to it. Colors are toggled on a per-game basis.');

			return;
		}

		$deck = $game->getParticipants()->findForUser($user)->getDeck();
		$deck->setAllowColors(!$deck->colorsAllowed());
		$allowed = $deck->colorsAllowed();
		Queue::fromContainer($container)
			->notice($user->getNickname(),
				'Your color preferences have been updated. Colors in personal messages are now ' . ($allowed ? 'enabled' : 'disabled') . ' for UNO.');
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function botenterCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		$ownNickname = Configuration::fromContainer($this->getContainer())['currentNickname'];
		$botObject = $source->getUserCollection()->findByNickname($ownNickname);
		if (!$game || $game->isStarted() || $game->getParticipants()->includes($botObject))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(),
					$user->getNickname() . ': A game has not been started, is already running, or I am already a participant.');

			return;
		}

		$this->addBotPlayer($game, $source);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function enterCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || $game->isStarted() || $game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(),
					$user->getNickname() . ': A game has not been started, is already running, or you are already a participant.');

			return;
		}

		$participant = $game->createParticipant($user);
		Queue::fromContainer($container)
			->privmsg($source->getName(), $user->getNickname() .
				': You have joined the UNO game. Please take a moment to read the basic rules by entering the unorules command.');

		$this->noticeCards($participant);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function cardsCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$participant = $game->getParticipants()->findForUser($user);
		$this->noticeCards($participant);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function passCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$currentParticipant = $game->getPlayerOrder()->getCurrent();
		$userParticipant = $game->getParticipants()->findForUser($user);

		if (($colorChoosingParticipant = $game->getPlayerMustChooseColor()))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': Waiting for ' . $colorChoosingParticipant->getUserObject()->getNickname() . ' to pick a color (r/g/b/y)');

			return;
		}

		if ($currentParticipant !== $userParticipant)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': It is not your turn.');

			return;
		}

		if (!$game->currentPlayerHasDrawn())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': You need to draw a card first.');

			return;
		}

		Queue::fromContainer($container)
			->privmsg($source->getName(), $user->getNickname() . ' passed a turn.');
		
		$this->advanceGame($game, $source);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function drawCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$currentParticipant = $game->getPlayerOrder()->getCurrent();
		$userParticipant = $game->getParticipants()->findForUser($user);

		if (($colorChoosingParticipant = $game->getPlayerMustChooseColor()))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': Waiting for ' . $colorChoosingParticipant->getUserObject()->getNickname() . ' to pick a color (r/g/b/y)');

			return;
		}

		if ($currentParticipant !== $userParticipant)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': It is not your turn.');

			return;
		}

		if ($game->currentPlayerHasDrawn())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': You cannot draw a card twice.');

			return;
		}

		$cards = $this->drawCardInGame($game, $source);
		$this->notifyNewCards($currentParticipant, $cards);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param bool $announce
	 *
	 * @return array
	 */
	public function drawCardInGame(Game $game, Channel $source, bool $announce = true): array
	{
		$participant = $game->getPlayerOrder()->getCurrent();
		$cards = $game->getDealer()->populate($participant->getDeck(), 1);

		if ($announce)
			Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $participant->getUserObject()->getNickname() . ' drew a card.');

		$game->setCurrentPlayerHasDrawn(true);
		return $cards;
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param bool $announce
	 */
	public function passTurnInGame(Game $game, Channel $source, bool $announce = true)
	{
		$participant = $game->getPlayerOrder()->getCurrent();
		if ($announce)
			Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $participant->getUserObject()->getNickname() . ' passed a turn.');
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function validmovesCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$participant = $game->getParticipants()->findForUser($user);
		$validCards = $participant->getDeck()->getValidCards($game->getLastCard());
		$currentCard = $game->getLastCard();
		$readableCard = $currentCard->toString();

		if ($participant->getDeck()->colorsAllowed())
			$currentCard = $currentCard->format();

		$currentCard = $readableCard . ' (' . $currentCard . ')';

		if (empty($validCards))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'You do not have any valid moves against ' . $currentCard);

			return;
		}

		if ($participant->getDeck()->colorsAllowed())
		{
			$deck = new Deck();
			foreach ($validCards as $validCard)
				$deck->append($validCard);
			$validCards = $deck->formatAll();
		}

		Queue::fromContainer($container)
			->notice($user->getNickname(), 'Valid moves against ' . $currentCard . ' are: ' . implode(', ', $validCards));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function playCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$currentParticipant = $game->getPlayerOrder()->getCurrent();
		$userParticipant = $game->getParticipants()->findForUser($user);

		if (($colorChoosingParticipant = $game->getPlayerMustChooseColor()))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': Waiting for ' . $colorChoosingParticipant->getUserObject()->getNickname() . ' to pick a color (r/g/b/y)');

			return;
		}

		if ($currentParticipant !== $userParticipant)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': It is not your turn.');

			return;
		}

		$card = strtolower($args[0]);
		$card = Card::fromString($card);
		if (!$currentParticipant->getDeck()->contains($card))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'You do not have that card.');

			return;
		}

		if (!$card->compatible($game->getLastCard()))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'That is not a valid move.');

			return;
		}

		if ($this->playCardInGame($game, $card, $currentParticipant, $source))
		{
			$this->advanceGame($game, $source);
		}
	}

	/**
	 * @param Game $game
	 * @param Card $card
	 * @param Participant $participant
	 * @param Channel $channel
	 *
	 * @return bool Suggestion whether the game should announce the next player.
	 */
	public function playCardInGame(Game $game, Card $card, Participant $participant, Channel $channel)
	{
		$deck = $participant->getDeck();
		$reverseState = $game->getPlayerOrder()->isReversed();
		$nickname = $participant->getUserObject()->getNickname();

		$response = $game->playCard($deck, $card);

		$friendlyCardName = $card->toHumanString();
		$formattedCard = $card->format();
		$message = sprintf('%s played %s (%s)', 
			$nickname,
			$friendlyCardName,
			$formattedCard
		);
		
		if (!empty($response))
			$message .= ' ' . $response;

		Queue::fromContainer($this->getContainer())
			->privmsg($channel->getName(), $message);

		if (count($deck) == 0)
		{
			Queue::fromContainer($this->getContainer())
				->privmsg($channel->getName(), $nickname . ' has played their last card and won! GG!');

			$participants = $game->getParticipants();
			/** @var Participant $participant */
			foreach ($participants as $participant)
			{
				$deck = $participant->getDeck();
				$cards = $deck->formatAll();
				if (empty($cards))
					continue;
				Queue::fromContainer($this->getContainer())
					->privmsg($channel->getName(), $participant->getUserObject()->getNickname() . ' ended with these cards: ' . implode(', ', $cards));
			}
			$this->stopGame($channel);

			return false;
		}

		if ($reverseState != $game->getPlayerOrder()->isReversed())
			$this->announceOrder($game, $channel);

		if (count($deck) == 1)
			Queue::fromContainer($this->getContainer())
				->privmsg($channel->getName(), $participant->getUserObject()->getNickname() . ' has UNO!');

		if (($colorChoosingParticipant = $game->getPlayerMustChooseColor()))
		{
			Queue::fromContainer($this->getContainer())
				->privmsg($channel->getName(), $colorChoosingParticipant->getUserObject()->getNickname() . ' must now choose a color (r/g/b/y) (choose using the color command)');

			return false;
		}

		return true;
	}

	/**
	 * @param Channel $source
	 */
	public function stopGame(Channel $source)
	{
		$this->games[$source->getName()]->stop();
		unset($this->games[$source->getName()]);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function unorulesCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$prefix = Configuration::fromContainer($container)['prefix'];
		Queue::fromContainer($container)
			->notice($user->getNickname(),
				'You will be assigned 10 cards when you join the game. The objective is to get rid of all your cards first. To play a card, use the ' .
				$prefix . 'play command (alias ' . $prefix . 'pl).');
		Queue::fromContainer($container)
			->notice($user->getNickname(), 'A card can be played if either the color (first letter) or type (second letter) match.');
		Queue::fromContainer($container)
			->notice($user->getNickname(),
				'If you cannot play a valid card (check with ' . $prefix . 'validmoves (alias ' . $prefix . 'vm), you must draw a card (' . $prefix .
				'draw/' . $prefix . 'dr)');
		Queue::fromContainer($container)
			->notice($user->getNickname(), 'If after drawing a card you still cannot play, pass your turn with ' . $prefix . 'pass/' . $prefix .
				'pa. Special cards are: #r: Reverse, #s: Skip, #d: Draw Two, w: Wildcard, wd: Wild Draw Four.');
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function colorCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$colorChoosingParticipant = $game->getPlayerMustChooseColor();
		if (!$colorChoosingParticipant)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A color cannot be picked now.');

			return;
		}

		$userParticipant = $game->getParticipants()->findForUser($user);

		if ($colorChoosingParticipant !== $userParticipant)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $colorChoosingParticipant->getUserObject()->getNickname() . ' must choose a new color.');

			return;
		}

		$color = $args[0];
		if (!in_array($color, ['r', 'b', 'g', 'y']))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': That is not a valid color (r/g/b/y)');

			return;
		}

		$color = new Card($color);
		$game->playCard($colorChoosingParticipant->getDeck(), $color);
		Queue::fromContainer($container)
			->privmsg($source->getName(), 'A color was picked!');
		
		$this->advanceGame($game, $source);
	}

	/**
	 * @param Channel $channel
	 *
	 * @return bool|Game
	 */
	protected function findGameForChannel(Channel $channel)
	{
		$name = $channel->getName();

		if (!array_key_exists($name, $this->games))
			return false;

		return $this->games[$name];
	}

	/**
	 * @return string
	 */
	public static function getSupportedVersionConstraint(): string
	{
		return '^3.0.0';
	}
}