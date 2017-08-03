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
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\Connection\TextFormatter;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\Users\User;

class HighScoreCommands
{
	use ContainerTrait;

	/**
	 * @var HighScores
	 */
	private $highScores;

	/**
	 * HighScoreCommands constructor.
	 *
	 * @param HighScores $highScores
	 * @param ComponentContainer $container
	 */
	public function __construct(HighScores $highScores, ComponentContainer $container)
	{
		$this->setContainer($container);
		$this->highScores = $highScores;

		$commandHelp = new CommandHelp();
		$commandHelp->append('UNO: Show high scores.');
		CommandHandler::fromContainer($container)
			->registerCommand('unohs', [$this, 'unohsCommand'], $commandHelp, 0, 0);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param array $args
	 * @param ComponentContainer $container
	 */
	public function unohsCommand(Channel $source, User $user, array $args, ComponentContainer $container)
	{
		$highScores = $this->highScores->getHighScores();

		if (empty($highScores))
		{
			Queue::fromContainer($container)->privmsg($source->getName(), 'There are no registered high scores.');
			return;
		}

		arsort($highScores);
		$highScores = array_slice($highScores, 0, 5);

		$msg = 'Top 5 high scores: ';

		$i = 1;
		$scoreStrings = [];
		foreach ($highScores as $nickname => $points)
		{
			$scoreStrings[] = $i . '. ' . TextFormatter::bold($nickname) . ' (' . $points . ' points)';
			$i++;
		}
		$msg .= implode(' - ', $scoreStrings);
		Queue::fromContainer($container)->privmsg($source->getName(), $msg);
	}
}