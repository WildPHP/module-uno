<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\DataStorage\DataStorageFactory;

class HighScores
{
	/**
	 * @var array
	 */
	private $highscores;

	/**
	 * HighScores constructor.
	 */
	public function __construct()
	{
		$dataStorage = DataStorageFactory::getStorage('unohighscores');
		
		$this->highscores = $dataStorage->getAll();
	}

	public function save()
	{
		$dataStorage = DataStorageFactory::getStorage('unohighscores');
		
		foreach ($this->highscores as $nickname => $points)
			$dataStorage->set($nickname, $points);
	}

	/**
	 * @param string $nickname
	 * @param int $points
	 */
	public function setHighScore(string $nickname, int $points)
	{
		$this->highscores[$nickname] = $points;
		$this->save();
	}

	public function updateHighScore(string $nickname, int $newPoints)
	{
		if ($newPoints <= $this->getHighScore($nickname))
			return false;
		
		$this->setHighScore($nickname, $newPoints);
		return true;
	}

	/**
	 * @param string $nickname
	 *
	 * @return int
	 */
	public function getHighScore(string $nickname): int
	{
		return $this->highscores[$nickname] ?? 0;
	}

	/**
	 * @return array
	 */
	public function getHighScores(): array
	{
		return $this->highscores;
	}

	/**
	 * @param Deck $deck
	 *
	 * @return int
	 */
	public function calculatePoints(Deck $deck): int
	{
		$points = 0;
		
		/** @var Card $card */
		foreach ($deck->getIterator() as $card)
			$points += $this->calculatePointsForCard($card);
		
		return $points;
	}

	/**
	 * @param Card $card
	 *
	 * @return int
	 */
	public function calculatePointsForCard(Card $card): int
	{
		if ($card->toString() == CardTypes::WILD || $card->toString() == 'wd')
			return 50;
		
		switch ($card->getType())
		{
			case CardTypes::SKIP:
			case CardTypes::REVERSE:
			case CardTypes::DRAW:
				return 20;
				break;
				
			default:
				return (int) $card->getType();
		}
	}
}