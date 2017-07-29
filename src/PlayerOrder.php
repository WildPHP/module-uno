<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


class PlayerOrder
{
	/**
	 * @var array
	 */
	protected $participants;

	/**
	 * @var bool
	 */
	protected $reversed = false;

	/**
	 * PlayerOrder constructor.
	 *
	 * @param Participants $participants
	 */
	public function __construct(Participants $participants)
	{
		$this->participants = $participants->getArrayCopy();
	}

	/**
	 * @param int $times
	 *
	 * @return Participant
	 */
	public function advance(int $times = 1): Participant
	{
		if ($this->isReversed())
			return $this->rewind($times);
		
		for ($i = 0; $i < $times; $i++)
		{
			$result = next($this->participants);
			if (!$result)
				$result = reset($this->participants);
		}
		
		return $result ?? null;
	}

	/**
	 * There's no valid reason to rewind a game of Uno.
	 * @param int $times
	 *
	 * @return Participant
	 */
	protected function rewind(int $times = 1): Participant
	{
		for ($i = 0; $i < $times; $i++)
		{
			$result = prev($this->participants);
			if (!$result)
				$result = end($this->participants);
		}

		return $result ?? null;
	}

	/**
	 * @return Participant
	 */
	public function getNext(): Participant
	{		
		// Create a copy to avoid changing the pointer here.
		$participants = $this->participants;

		if (!$this->isReversed())
		{
			$result = next($participants);
			if (!$result)
				$result = reset($participants);
		}
		else
		{
			$result = prev($participants);
			if (!$result)
				$result = end($participants);
		}
		
		return $result ?? null;
	}

	/**
	 * @return Participant
	 */
	public function getPrevious(): Participant
	{
		// Create a copy to avoid changing the pointer here.
		$participants = $this->participants;

		if (!$this->isReversed())
		{
			$result = prev($participants);
			if (!$result)
				$result = end($participants);
		}
		else
		{
			$result = next($participants);
			if (!$result)
				$result = reset($participants);
		}

		return $result;
	}

	/**
	 * @return Participant
	 */
	public function getCurrent(): Participant
	{
		return current($this->participants);
	}

	public function reverse()
	{
		$this->reversed = !$this->reversed;
	}

	/**
	 * @return bool
	 */
	public function isReversed(): bool
	{
		return $this->reversed;
	}

	/**
	 * @return Participant[]
	 */
	public function toArray()
	{
		if ($this->isReversed())
			return array_reverse($this->participants);
		
		return $this->participants;
	}
}