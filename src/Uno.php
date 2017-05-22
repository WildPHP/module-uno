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

use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Commands\CommandHandler;
use WildPHP\Core\Commands\CommandHelp;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\Connection\TextFormatter;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Users\User;

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
			->registerCommand('newgame', [$this, 'startGameCommand'], null, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Starts the initiated game of UNO. Use after all participants have joined. No arguments.');
		CommandHandler::fromContainer($container)
			->registerCommand('start', [$this, 'startCommand'], null, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Stops the running game of UNO.');
		CommandHandler::fromContainer($container)
			->registerCommand('stop', [$this, 'stopCommand'], null, 0, 0, 'newgame');

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('Enter as a participant in the running game of UNO.');
		CommandHandler::fromContainer($container)
			->registerCommand('enter', [$this, 'enterCommand'], null, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Pass your current turn.');
		CommandHandler::fromContainer($container)
			->registerCommand('pass', [$this, 'passCommand'], null, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Play a card. Usage: play [card]');
		CommandHandler::fromContainer($container)
			->registerCommand('play', [$this, 'playCommand'], null, 1, 1);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Choose a color. Usage: color [color]');
		CommandHandler::fromContainer($container)
			->registerCommand('color', [$this, 'colorCommand'], null, 1, 1);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Draw a card from the stack.');
		CommandHandler::fromContainer($container)
			->registerCommand('draw', [$this, 'drawCommand'], null, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Show your current cards.');
		CommandHandler::fromContainer($container)
			->registerCommand('cards', [$this, 'cardsCommand'], null, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Toggle the displaying of colors in your private messages for the current session.');
		CommandHandler::fromContainer($container)
			->registerCommand('togglecolors', [$this, 'togglecolorsCommand'], null, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: Show all available valid moves.');
		CommandHandler::fromContainer($container)
			->registerCommand('validmoves', [$this, 'validmovesCommand'], null, 0, 0);

		$commandHelp = new CommandHelp();
		$commandHelp->addPage('UNO: List basic game rules.');
		CommandHandler::fromContainer($container)
			->registerCommand('unorules', [$this, 'unorulesCommand'], null, 0, 0);

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

		EventEmitter::fromContainer($container)
			->on('uno.populated', [$this, 'notifyNewCards']);

		$this->setContainer($container);
	}

	/**
	 * @param string $card
	 *
	 * @return string
	 */
	public function formatCard(string $card): string
	{
		$colormap = [
			'r' => 'red',
			'g' => 'green',
			'b' => 'teal',
			'y' => 'yellow',
			'w' => 'wild',
		];
		if (empty($card))
			return '';

		$color = $card[0];

		if ($color == 'w' || !array_key_exists($color, $colormap))
			return $card;

		return TextFormatter::bold(TextFormatter::color($card, $colormap[$color]));
	}

	/**
	 * @param Deck $deck
	 * @param Game $game
	 * @param array $cards
	 */
	public function notifyNewCards(Deck $deck, Game $game, array $cards)
	{
		$nickname = $game->getNicknameForDeck($deck);

		sort($cards);

		if ($deck->colorsAllowed())
			foreach ($cards as $key => $card)
			{
				$cards[$key] = $this->formatCard($card);
			}

		$cards = implode(', ', $cards);
		Queue::fromContainer($this->getContainer())
			->notice($nickname, 'These cards were added to your deck: ' . $cards);
	}

	/**
	 * @param string $nickname
	 * @param Deck $deck
	 */
	public function noticeCardsToUser(string $nickname, Deck $deck)
	{
		$cards = $deck->getCards();

		if (!empty($cards))
		{
			sort($cards);

			if ($deck->colorsAllowed())
				foreach ($cards as $key => $card)
				{
					$cards[$key] = $this->formatCard($card);
				}

			$cards = implode(', ', $cards);

			Queue::fromContainer($this->getContainer())
				->notice($nickname, 'You have the following cards: ' . $cards);

			return;
		}

		Queue::fromContainer($this->getContainer())
			->notice($nickname, 'You have no cards. (wat)');
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function announceNextTurn(Game $game, Channel $source)
	{
		$game->setDrawn(false);
		$nextDeck = $game->getDeckForNextParticipant();
		$nickname = $game->getNicknameForDeck($nextDeck);
		$lastCard = $game->getLastCard();
		$lastCardReadable = $game->getReadableCardFormat($lastCard);
		$lastCard = $this->formatCard($lastCard);

		Queue::fromContainer($this->getContainer())
			->privmsg($source->getName(), 'It is ' . $nickname . '\'s turn. The current card is ' . $lastCardReadable . ' (' . $lastCard . ')');
		$this->noticeCardsToUser($nickname, $nextDeck);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function startGameCommand(Channel $source, User $user, array $args, ComponentContainer $container)
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
		$game->addParticipant($user);
		$prefix = Configuration::fromContainer($container)
			->get('prefix')
			->getValue();
		Queue::fromContainer($container)
			->privmsg($source->getName(),
				'A game has been opened and you have joined. Use ' . $prefix . 'enter to join, ' . $prefix . 'start to start the game.');
		$this->noticeCardsToUser($user->getNickname(), $game->getDeckForUser($user));
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
		$game->setStarted(true);
		$game->getDeckForPreviousParticipant();
		$game->setLastCard($game->pickRandomCard(1, true)[0]);
		Queue::fromContainer($this->getContainer())
			->privmsg($source->getName(), 'Game started!');
		$this->announceNextTurn($game, $source);
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
		if (!$game || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has not been started or you are not added to it. Colors are toggled on a per-game basis.');

			return;
		}

		$deck = $game->getDeckForUser($user);
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
	public function enterCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = $this->findGameForChannel($source);
		if (!$game || $game->isStarted() || $game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(),
					$user->getNickname() . ': A game has not been started, is already running, or you are already a participant.');

			return;
		}

		$game->addParticipant($user);
		Queue::fromContainer($container)
			->privmsg($source->getName(), $user->getNickname() .
				': You have joined the UNO game. Please take a moment to read the basic rules by entering the unorules command.');

		$this->noticeCardsToUser($user->getNickname(), $game->getDeckForUser($user));
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
		if (!$game || !$game->isStarted() || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$deck = $game->getDeckForUser($user);
		$this->noticeCardsToUser($user->getNickname(), $deck);
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
		if (!$game || !$game->isStarted() || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		if (($nickname = $game->waitingOnPlayerColor()))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': Waiting for ' . $nickname . ' to pick a color (r/g/b/y)');

			return;
		}

		$deck = $game->GetCurrentParticipant();
		if ($game->getNicknameForDeck($deck) != $user->getNickname())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': It is not your turn.');

			return;
		}

		if (!$game->isDrawn())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': You need to draw a card first.');

			return;
		}

		Queue::fromContainer($container)
			->privmsg($source->getName(), $user->getNickname() . ' passed a turn.');
		$this->announceNextTurn($game, $source);
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
		if (!$game || !$game->isStarted() || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		if (($nickname = $game->waitingOnPlayerColor()))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': Waiting for ' . $nickname . ' to pick a color (r/g/b/y)');

			return;
		}

		$deck = $game->GetCurrentParticipant();
		if ($game->getNicknameForDeck($deck) != $user->getNickname())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': It is not your turn.');

			return;
		}

		if ($game->isDrawn())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': You cannot draw a card twice.');

			return;
		}

		$game->populateDeck($deck, 1);
		Queue::fromContainer($container)
			->privmsg($source->getName(), $user->getNickname() . ' drew a card.');
		$game->setDrawn(true);
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
		if (!$game || !$game->isStarted() || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$deck = $game->getDeckForUser($user);
		$validCards = $game->deckGetValidCards($deck);
		$currentCard = $game->getLastCard();
		$readableCard = $game->getReadableCardFormat($currentCard);

		if ($deck->colorsAllowed())
			$currentCard = $this->formatCard($currentCard);

		$currentCard = $readableCard . ' (' . $currentCard . ')';

		if (empty($validCards))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'You do not have any valid moves against ' . $currentCard);

			return;
		}

		if ($deck->colorsAllowed())
			foreach ($validCards as $key => $card)
			{
				$validCards[$key] = $this->formatCard($card);
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
		if (!$game || !$game->isStarted() || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		if (($nickname = $game->waitingOnPlayerColor()))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': Waiting for ' . $nickname . ' to pick a color (r/g/b/y)');

			return;
		}

		$deck = $game->GetCurrentParticipant();
		if ($game->getNicknameForDeck($deck) != $user->getNickname())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': It is not your turn.');

			return;
		}

		$card = strtolower($args[0]);
		if (!$deck->containsCard($card))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'You do not have that card.');

			return;
		}

		if (!$game->cardIsCompatible($game->getLastCard(), $card))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'That is not a valid move.');

			return;
		}

		$response = $game->playCard($user, $card);
		$friendlyCardName = $game->getReadableCardFormat($card);
		$card = $this->formatCard($card);
		$message = $user->getNickname() . ' played ' . $friendlyCardName . ' (' . $card . ')!';
		if (!empty($response))
			$message .= ' ' . $response;

		Queue::fromContainer($container)
			->privmsg($source->getName(), $message);

		if (count($deck->getCards()) == 0)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ' has played their last card and won! GG!');
			$this->stopGame($source);

			return;
		}

		if (count($deck->getCards()) == 1)
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ' has UNO!');


		if ($game->waitingOnPlayerColor())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ' must now choose a color (r/g/b/y) (choose using the color command)');

			return;
		}

		$this->announceNextTurn($game, $source);
	}

	/**
	 * @param Channel $source
	 */
	public function stopGame(Channel $source)
	{
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
		if (!$game || !$game->isStarted() || !$game->isParticipant($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		if (!$game->waitingOnPlayerColor())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A color cannot be picked now.');

			return;
		}

		if ($game->waitingOnPlayerColor() != $user->getNickname())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $game->waitingOnPlayerColor() . ' must choose a new color.');

			return;
		}

		$color = $args[0];
		if (!in_array($color, ['r', 'b', 'g', 'y']))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': That is not a valid color (r/g/b/y)');

			return;
		}

		$game->setColor($color);
		Queue::fromContainer($container)
			->privmsg($source->getName(), 'A color was picked!');
		$this->announceNextTurn($game, $source);
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