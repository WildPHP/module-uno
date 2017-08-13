<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;

use ValidationClosures\Types;
use WildPHP\Core\Channels\Channel;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\ContainerTrait;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Modules\BaseModule;
use Yoshi2889\Collections\Collection;


class Uno extends BaseModule
{
	use ContainerTrait;

	/**
	 * @var Collection
	 */
	protected $games;

	/**
	 * @var HighScores
	 */
	protected $highScores;

	/**
	 * Uno constructor.
	 *
	 * @param ComponentContainer $container
	 */
	public function __construct(ComponentContainer $container)
	{
		$this->setContainer($container);
		$this->games = new Collection(Types::instanceof(Game::class));
		$this->highScores = new HighScores();
		
		new GameCommands($this->games, $this->highScores, $this->getContainer());
		new GameplayCommands($this->games, $this->getContainer());
		new HighScoreCommands($this->highScores, $this->getContainer());
		
		EventEmitter::fromContainer($container)->on('uno.game.stopped', [$this, 'cleanupGame']);
	}

	/**
	 * @return string
	 */
	public static function getSupportedVersionConstraint(): string
	{
		return '^3.0.0';
	}

	/**
	 * @param Game $game
	 * @param Channel $channel
	 */
	public function cleanupGame(Game $game, Channel $channel)
	{
		if (!$this->games->contains($game) || !$this->games->offsetExists($channel->getName()))
			return;
		
		$this->games->offsetUnset($channel->getName());
	}
}