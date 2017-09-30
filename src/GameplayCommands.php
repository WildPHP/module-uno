<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use ValidationClosures\Utils as ValUtils;
use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Commands\Command;
use WildPHP\Core\Commands\CommandHandler;
use WildPHP\Core\Commands\CommandHelp;
use WildPHP\Core\Commands\ParameterStrategy;
use WildPHP\Core\Commands\StringParameter;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\Users\User;
use Yoshi2889\Collections\Collection;

class GameplayCommands
{
	use ContainerTrait;

	/**
	 * @var Collection
	 */
	private $games;

	/**
	 * GameplayCommands constructor.
	 *
	 * @param Collection $games
	 * @param ComponentContainer $container
	 */
	public function __construct(Collection $games, ComponentContainer $container)
	{
		$this->setContainer($container);
		$this->games = $games;

		CommandHandler::fromContainer($container)->registerCommand('pass',
			new Command(
				[$this, 'passCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'UNO: Pass your current turn.'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('play',
			new Command(
				[$this, 'playCommand'],
				new ParameterStrategy(1, 1, [
					'card' => new StringParameter()
				]),
				new CommandHelp([
					'UNO: Play a card. Usage: play [card]'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('color',
			new Command(
				[$this, 'colorCommand'],
				new ParameterStrategy(1, 1, [
					'color' => new StringParameter()
				]),
				new CommandHelp([
					'UNO: Choose a color. Usage: color [color]'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('draw',
			new Command(
				[$this, 'drawCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'UNO: Draw a card from the stack.'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('cards',
			new Command(
				[$this, 'cardsCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'UNO: Show your current cards in a private message.'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('validmoves',
			new Command(
				[$this, 'validmovesCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'UNO: Show all available valid moves for the current top card.'
				])
			));

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
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function cardsCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$participant = $game->getParticipants()->findForUser($user);
		
		if (!$participant)
			return;

		if ($participant == $game->getPlayerOrder()->getCurrent())
		{
			$game->getTimeoutController()->resetTimers();
			$game->getTimeoutController()->setTimer($game, $source);
		}

		Announcer::noticeCards($participant, Queue::fromContainer($container));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function colorCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
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

		$game->getTimeoutController()->resetTimers();

		$color = strtolower($args['color']);
		if (!in_array($color, ['r', 'b', 'g', 'y']))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': That is not a valid color (r/g/b/y)');
			$game->getTimeoutController()->setTimer($game, $source);

			return;
		}

		$color = new Card($color);
		$game->playCard($colorChoosingParticipant->getDeck(), $color);
		Queue::fromContainer($container)
			->privmsg($source->getName(), 'A color was picked!');

		$game->advanceNotify();
	}



	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function passCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
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

		$game->getTimeoutController()->resetTimers();

		if (!$game->currentPlayerHasDrawn())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': You need to draw a card first.');
			$game->getTimeoutController()->setTimer($game, $source);

			return;
		}

		Queue::fromContainer($container)
			->privmsg($source->getName(), $user->getNickname() . ' passed a turn.');

		$game->advanceNotify();
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function drawCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
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

		$game->getTimeoutController()->resetTimers();

		if ($game->currentPlayerHasDrawn())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': You cannot draw a card twice.');
			$game->getTimeoutController()->setTimer($game, $source);

			return;
		}

		$cards = $game->draw();
		Announcer::notifyNewCards($currentParticipant, $cards, Queue::fromContainer($container));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function validmovesCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
		if (!$game || !$game->isStarted() || !$game->getParticipants()->includes($user))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': A game has not been started or you are no participant.');

			return;
		}

		$participant = $game->getParticipants()->findForUser($user);
		$validCards = $participant->getDeck()->getValidCards($game->getLastCard());
		$currentCard = $game->getLastCard();
		$readableCard = $currentCard->__toString();

		if ($participant == $game->getPlayerOrder()->getCurrent())
		{
			$game->getTimeoutController()->resetTimers();
			$game->getTimeoutController()->setTimer($game, $source);
		}

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
		$game = Utils::findGameForChannel($source, $this->games);
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

		$game->getTimeoutController()->resetTimers();

		$card = strtolower($args['card']);
		$card = Card::fromString($card);
		if (!$currentParticipant->getDeck()->contains($card))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'You do not have that card.');
			$game->getTimeoutController()->setTimer($game, $source);

			return;
		}

		if (!$card->compatible($game->getLastCard()))
		{
			Queue::fromContainer($container)
				->notice($user->getNickname(), 'That is not a valid move.');
			$game->getTimeoutController()->setTimer($game, $source);

			return;
		}

		if ($card->__toString() == 'wd')
		{
			$cardsNoWD = $currentParticipant->getDeck()->getValidCards($game->getLastCard())->filter(ValUtils::invert(DeckFilters::wild()));

			if (!empty((array) ($cardsNoWD)))
			{
				Queue::fromContainer($container)
					->notice($user->getNickname(), 'You have other cards you can play; play those before playing a Wild Draw Four.');
				$game->getTimeoutController()->setTimer($game, $source);

				return;
			}
		}

		if ($game->playCardWithChecks($card, $currentParticipant))
		{
			$game->advanceNotify();
			return;
		}

		$game->getTimeoutController()->setTimer($game, $source);
	}
}