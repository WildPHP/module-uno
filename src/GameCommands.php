<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Commands\Command;
use WildPHP\Core\Commands\CommandHandler;
use WildPHP\Core\Commands\CommandHelp;
use WildPHP\Core\Commands\ParameterStrategy;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\Users\User;
use Yoshi2889\Collections\Collection;

class GameCommands
{
	use ContainerTrait;

	/**
	 * @var Collection
	 */
	protected $games;
	/**
	 * @var HighScores
	 */
	private $highScores;

	/**
	 * GameCommands constructor.
	 *
	 * @param Collection $games
	 * @param HighScores $highScores
	 * @param ComponentContainer $container
	 */
	public function __construct(Collection $games, HighScores $highScores, ComponentContainer $container)
	{
		$this->setContainer($container);
		$this->games = $games;
		$this->highScores = $highScores;

		CommandHandler::fromContainer($container)->registerCommand('newgame',
			new Command(
				[$this, 'newgameCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'Initiates a new game of UNO. No arguments.'
				]),
				'newgame'
			));

		CommandHandler::fromContainer($container)->registerCommand('start',
			new Command(
				[$this, 'startCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'Starts the initiated game of UNO. Use after all participants have joined. No arguments.'
				]),
				'newgame'
			));

		CommandHandler::fromContainer($container)->registerCommand('botenter',
			new Command(
				[$this, 'botenterCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'Instruct the bot to join the current joinable game of UNO. No arguments.'
				]),
				'botenter'
			));

		CommandHandler::fromContainer($container)->registerCommand('stop',
			new Command(
				[$this, 'stopCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'Stops the running game of UNO. No arguments.'
				]),
				'newgame'
			));

		CommandHandler::fromContainer($container)->registerCommand('enter',
			new Command(
				[$this, 'enterCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'Enter as a participant in the joinable game of UNO. No arguments.'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('togglecolors',
			new Command(
				[$this, 'togglecolorsCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'UNO: Toggle the displaying of colors in your private messages for the current session. No arguments.'
				])
			));

		CommandHandler::fromContainer($container)->registerCommand('unorules',
			new Command(
				[$this, 'unorulesCommand'],
				new ParameterStrategy(0, 0),
				new CommandHelp([
					'UNO: List basic game rules. No arguments.'
				])
			));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 *
	 * @return void
	 */
	public function newgameCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
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

		$game = new Game(
			$container,
			new TimeoutController(
				[BotParticipant::class, 'playAutomaticCardAndNotice'],
				$container->getLoop(),
				Queue::fromContainer($container)
			),
			$source,
			$this->highScores
		);
		
		$participant = $game->createParticipant($user);

		$prefix = Configuration::fromContainer($container)['prefix'];

		Queue::fromContainer($container)
			->privmsg($source->getName(),
				'A game has been opened and you have joined. Use ' . $prefix . 'enter to join, ' . $prefix . 'start to start the game.');

		Announcer::noticeCards($participant, Queue::fromContainer($container));
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
		$game = Utils::findGameForChannel($source, $this->games);
		
		if (!$game)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game is already in progress in this channel.');

			return;
		}
		
		if ($game->isStarted())
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has already been started in this channel.');

			return;
		}
		
		if (count($game->getParticipants()) == 1)
			BotParticipant::addToGame($game, $source, $this->getContainer());

		$game->start();

		Announcer::announceCurrentTurn($game, $source, Queue::fromContainer($container));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function stopCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
		if (!$game)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'A game has not been started in this channel.');

			return;
		}

		$this->stopGame($source);
	}
	
	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function enterCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
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

		Announcer::noticeCards($participant, Queue::fromContainer($container));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function botenterCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
		$ownNickname = Configuration::fromContainer($this->getContainer())['currentNickname'];
		$botObject = $source->getUserCollection()->findByNickname($ownNickname);
		if (!$game || $game->isStarted() || $game->getParticipants()->includes($botObject))
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(),
					$user->getNickname() . ': A game has not been started, is already running, or I am already a participant.');

			return;
		}

		BotParticipant::addToGame($game, $source, $this->getContainer());
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

		$lines = [
			'You will be assigned 10 cards when you join the game. The objective is to get rid of all your cards first. To play a card, use the ' .
			$prefix . 'play command (alias ' . $prefix . 'pl).',
			'A card can be played if either the color (first letter) or type (second letter) match.',
			'If you cannot play a valid card (check with ' . $prefix . 'validmoves (alias ' . $prefix . 'vm), you must draw a card (' . $prefix .
			'draw/' . $prefix . 'dr)',
			'If after drawing a card you still cannot play, pass your turn with ' . $prefix . 'pass/' . $prefix .
			'pa. Special cards are: #r: Reverse, #s: Skip, #d: Draw Two, w: Wildcard, wd: Wild Draw Four.',
			'Lastly, you may not play a Wild Draw Four card if you have other playable cards.'
		];

		foreach ($lines as $line)
			Queue::fromContainer($container)->notice($user->getNickname(), $line);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function togglecolorsCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$game = Utils::findGameForChannel($source, $this->games);
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
	 */
	public function stopGame(Channel $source)
	{
		$this->games[$source->getName()]->stop();
		unset($this->games[$source->getName()]);
	}
}