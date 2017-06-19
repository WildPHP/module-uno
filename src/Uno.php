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
use WildPHP\Core\Logger\Logger;
use WildPHP\Core\Users\User;
use WildPHP\Core\Users\UserCollection;

class Uno
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
		$commandHelp->addPage('Initiates a new game of UNO. No arguments.');
		CommandHandler::fromContainer($container)
			->registerCommand('newgame', [$this, 'newgameCommand'], $commandHelp, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Starts the initiated game of UNO. Use after all participants have joined. No arguments.');
		CommandHandler::fromContainer($container)
			->registerCommand('start', [$this, 'startCommand'], $commandHelp, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Instruct the bot to join the current running game.');
		CommandHandler::fromContainer($container)
			->registerCommand('botenter', [$this, 'botenterCommand'], $commandHelp, 0, 0, 'botenter');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Stops the running game of UNO.');
		CommandHandler::fromContainer($container)
			->registerCommand('stop', [$this, 'stopCommand'], $commandHelp, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Enter as a participant in the running game of UNO.');
		CommandHandler::fromContainer($container)
			->registerCommand('enter', [$this, 'enterCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Pass your current turn.');
		CommandHandler::fromContainer($container)
			->registerCommand('pass', [$this, 'passCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Play a card. Usage: play [card]');
		CommandHandler::fromContainer($container)
			->registerCommand('play', [$this, 'playCommand'], $commandHelp, 1, 1);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Choose a color. Usage: color [color]');
		CommandHandler::fromContainer($container)
			->registerCommand('color', [$this, 'colorCommand'], $commandHelp, 1, 1);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Draw a card from the stack.');
		CommandHandler::fromContainer($container)
			->registerCommand('draw', [$this, 'drawCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Show your current cards.');
		CommandHandler::fromContainer($container)
			->registerCommand('cards', [$this, 'cardsCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Toggle the displaying of colors in your private messages for the current session.');
		CommandHandler::fromContainer($container)
			->registerCommand('togglecolors', [$this, 'togglecolorsCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Show all available valid moves.');
		CommandHandler::fromContainer($container)
			->registerCommand('validmoves', [$this, 'validmovesCommand'], $commandHelp, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: List basic game rules.');
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
		$diff->addRange($cards);
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
	 */
	public function advanceGame(Game $game, Channel $source)
	{
		$game->setCurrentPlayerHasDrawn(false);
		$game->setPlayerMustChooseColor(false);
		$nextParticipant = $game->advance();
		$botUserObject = UserCollection::fromContainer($this->getContainer())->getSelf();
		if ($nextParticipant->getUserObject() === $botUserObject)
			$this->playAutomaticCard($game, $source);
		else
			$this->noticeCards($nextParticipant);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function announceNextTurn(Game $game, Channel $source)
	{
		if (!$game->isStarted())
			return;

		$nextParticipant = $game->getNextPlayer();
		$nickname = $nextParticipant->getUserObject()->getNickname();
		$lastCard = $game->getLastCard();
		$lastCardFormatted = $lastCard->format();
		$lastCardReadable = $lastCard->toHumanString();

		Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $nickname . ' is up! The current card is ' . $lastCardReadable . ' (' . $lastCardFormatted . ')');
	}

	public function playAutomaticCard(Game $game, Channel $source)
	{
		$participant = $game->getCurrentPlayer();
		$validCards = $game->deckGetValidCards($participant->getDeck());

		if (empty($validCards) && !$game->currentPlayerHasDrawn())
			$this->drawCardInGame($game, $source);

		$validCards = $game->deckGetValidCards($participant->getDeck());
		if (empty($validCards) && $game->currentPlayerHasDrawn())
		{
			$this->passTurnInGame($game, $source);
			$this->announceNextTurn($game, $source);
			$this->advanceGame($game, $source);
			return;
		}

		usort($validCards, function (Card $card1, Card $card2)
		{
			if ($card1->toString() == $card2->toString())
				return 0;

			return ($card1->toString() > $card2->toString()) ? -1 : 1;
		});

		// First play the current color
		$currentCard = $game->getLastCard();
		$cardsCurrentColor = array_filter($validCards, function (Card $card) use ($currentCard)
		{
			return $card->getColor() == $currentCard->getColor();
		});

		// Avoid changing the color all the time...
		if (empty($cardsCurrentColor))
		{
			$cardsNoWild = array_filter($validCards, function (Card $card)
			{
				return $card->getColor() != 'w';
			});
			if (!empty($cardsNoWild))
				$validCards = $cardsNoWild;
		}
		else
			$validCards = $cardsCurrentColor;

		$card = array_shift($validCards);
		$result = $this->playCardInGame($game, $card, $participant, $source);

		if (!$game->isStarted())
			return;

		if (!$result && $game->playerMustChooseColor())
		{
			$deck = $participant->getDeck();

			$count['g'] = count($deck->findAll(function (Card $card)
			{
				return $card->getColor() == CardTypes::GREEN;
			}));

			$count['b'] = count($deck->findAll(function (Card $card)
			{
				return $card->getColor() == CardTypes::BLUE;
			}));

			$count['y'] = count($deck->findAll(function (Card $card)
			{
				return $card->getColor() == CardTypes::YELLOW;
			}));

			$count['r'] = count($deck->findAll(function (Card $card)
			{
				return $card->getColor() == CardTypes::RED;
			}));

			$count = array_flip($count);
			ksort($count);
			$color = end($count);
			$color = new Card($color);
			$message = '';
			$game->playCard($deck, $color, $message);
			$readableColor = $color->toHumanString();
			$formattedColor = $color->format();
			Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $participant->getUserObject()->getNickname() . ' picked color ' . $readableColor . ' (' . $formattedColor . ')');
		}
		$this->announceNextTurn($game, $source);
		$this->advanceGame($game, $source);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function announceOrder(Game $game, Channel $source)
	{
		$participants = $game->getParticipants()->toArray();

		if ($game->isReversed())
			$participants = array_reverse($participants);

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

		$prefix = Configuration::fromContainer($container)
			->get('prefix')
			->getValue();

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

		$game->setStarted(true);
		$game->setStartTime(time());
		$game->rewind();
		$game->setLastCard($game->drawRandomCard(1, true)[0]);

		Queue::fromContainer($this->getContainer())
			->privmsg($source->getName(), 'Game started!');

		$this->announceNextTurn($game, $source);
		$this->advanceGame($game, $source);
	}

	public function addBotPlayer(Game $game, Channel $source)
	{
		$botObject = UserCollection::fromContainer($this->getContainer())->getSelf();
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
		if (!$game || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has not been started or you are not added to it. Colors are toggled on a per-game basis.');

			return;
		}

		$deck = $game->findParticipantForUser($user)->getDeck();
		$deck->setAllowColors(!$deck->colorsAllowed());
		$allowed = $deck->colorsAllowed();
		Queue::fromContainer($container)
			->notice($user->getNickname(),
				'Your color preferences have been updated. Colors in personal messages are now ' . ($allowed ? 'enabled' : 'disabled') . ' for UNO.');
	}

	public function botenterCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		$botObject = UserCollection::fromContainer($this->getContainer())->getSelf();
		if (!$game || $game->isStarted() || $game->isUserParticipant($botObject))
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
		if (!$game || $game->isStarted() || $game->isUserParticipant($user))
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
		if (!$game || !$game->isStarted() || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$participant = $game->findParticipantForUser($user);
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
		if (!$game || !$game->isStarted() || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$currentParticipant = $game->getCurrentPlayer();
		$userParticipant = $game->findParticipantForUser($user);

		if (($colorChoosingParticipant = $game->playerMustChooseColor()))
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
		$this->announceNextTurn($game, $source);
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
		if (!$game || !$game->isStarted() || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$currentParticipant = $game->getCurrentPlayer();
		$userParticipant = $game->findParticipantForUser($user);

		if (($colorChoosingParticipant = $game->playerMustChooseColor()))
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

	public function drawCardInGame(Game $game, Channel $source, bool $announce = true): array
	{
		$participant = $game->getCurrentPlayer();
		$cards = $game->populateDeckForParticipant($participant, 1);

		if ($announce)
			Queue::fromContainer($this->getContainer())
				->privmsg($source->getName(), $participant->getUserObject()->getNickname() . ' drew a card.');

		$game->setCurrentPlayerHasDrawn(true);
		return $cards;
	}

	public function passTurnInGame(Game $game, Channel $source, bool $announce = true)
	{
		$participant = $game->getCurrentPlayer();
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
		if (!$game || !$game->isStarted() || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$participant = $game->findParticipantForUser($user);
		$validCards = $game->deckGetValidCards($participant->getDeck());
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
			$deck->addRange($validCards);
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
		if (!$game || !$game->isStarted() || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$currentParticipant = $game->getCurrentPlayer();
		$userParticipant = $game->findParticipantForUser($user);

		if (($colorChoosingParticipant = $game->playerMustChooseColor()))
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
		if (!$currentParticipant->getDeck()->findCard($card))
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
			$this->announceNextTurn($game, $source);
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
		$reverseState = $game->isReversed();
		$nickname = $participant->getUserObject()->getNickname();

		$response = '';
		$game->playCard($deck, $card, $response);

		$friendlyCardName = $card->toHumanString();
		$formattedCard = $card->format();
		$message = $nickname . ' played ' . $friendlyCardName . ' (' . $formattedCard . ')!';
		if (!empty($response))
			$message .= ' ' . $response;

		Queue::fromContainer($this->getContainer())
			->privmsg($channel->getName(), $message);

		if (count($deck->toArray()) == 0)
		{
			Queue::fromContainer($this->getContainer())
				->privmsg($channel->getName(), $nickname . ' has played their last card and won! GG!');

			$participants = $game->getParticipants()->toArray();
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

		if ($reverseState != $game->isReversed())
			$this->announceOrder($game, $channel);

		if (count($deck->toArray()) == 1)
			Queue::fromContainer($this->getContainer())
				->privmsg($channel->getName(), $participant->getUserObject()->getNickname() . ' has UNO!');

		if (($colorChoosingParticipant = $game->playerMustChooseColor()))
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
		$this->games[$source->getName()]->setStarted(false);
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
		$prefix = Configuration::fromContainer($container)
			->get('prefix')
			->getValue();
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
		if (!$game || !$game->isStarted() || !$game->isUserParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$colorChoosingParticipant = $game->playerMustChooseColor();
		if (!$colorChoosingParticipant)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A color cannot be picked now.');

			return;
		}

		$userParticipant = $game->findParticipantForUser($user);

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
		$message = '';
		$game->playCard($colorChoosingParticipant->getDeck(), $color, $message);
		Queue::fromContainer($container)
			->privmsg($source->getName(), 'A color was picked!');
		$this->announceNextTurn($game, $source);
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


}