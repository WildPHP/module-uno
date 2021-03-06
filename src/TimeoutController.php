<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use React\EventLoop\LoopInterface;
use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Connection\Queue;
use Yoshi2889\Tasks\CallbackTask;
use Yoshi2889\Tasks\TaskController;

class TimeoutController
{
	/**
	 * @var callable
	 */
	protected $automaticPlayCallback;

	/**
	 * @var TaskController
	 */
	protected $taskController;
	/**
	 * @var Queue
	 */
	private $queue;

	/**
	 * TimeoutController constructor.
	 *
	 * @param callable $automaticPlayCallback
	 * @param LoopInterface $loop
	 * @param Queue $queue
	 */
	public function __construct($automaticPlayCallback, LoopInterface $loop, Queue $queue)
	{
		$this->automaticPlayCallback = $automaticPlayCallback;
		$this->taskController = new TaskController($loop);
		$this->queue = $queue;
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 */
	public function setTimer(Game $game, Channel $source)
	{
		$this->taskController->add(new CallbackTask($this->automaticPlayCallback, 120, [$game, $source, $this->queue]));
	}

	public function resetTimers()
	{
		$this->taskController->removeAll();
	}
}