<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\Connection\TextFormatter;

class Announcer
{
	/**
	 * @param Game $game
	 * @param Channel $channel
	 * @param Queue $queue
	 */
	public static function announceCurrentTurn(Game $game, Channel $channel, Queue $queue)
	{
		$currentParticipant = $game->getPlayerOrder()
			->getCurrent();
		$currentCard = $game->getLastCard();
		$cardString = Utils::formatCardForMessage($currentCard);

		$message = sprintf('%s is up! The current card is %s', $currentParticipant->getUserObject()
			->getNickname(), $cardString);

		$queue->privmsg($channel->getName(), $message);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param Queue $queue
	 */
	public static function announceOrder(Game $game, Channel $source, Queue $queue)
	{
		$participants = $game->getPlayerOrder()->toArray();

		/** @var Participant $participant */
		$nicknames = [];
		foreach ($participants as $participant)
		{
			$nicknames[] = TextFormatter::bold($participant->getUserObject()->getNickname());
		}

		$message = 'The current order is: ' . implode(' -> ', $nicknames);
		$queue->privmsg($source->getName(), $message);
	}

	/**
	 * @param Participant $participant
	 * @param Queue $queue
	 */
	public static function noticeCards(Participant $participant, Queue $queue)
	{
		$deck = $participant->getDeck();
		$formattedDeck = $deck->colorsAllowed() ? $deck->formatAll() : $deck->allToString();
		$cardsString = implode(', ', $formattedDeck);

		$message = sprintf('These cards are in your deck: %s', $cardsString);
		$queue->notice($participant->getUserObject()
			->getNickname(), $message);
	}

	/**
	 * @param Participant $participant
	 * @param array $cards
	 * @param Queue $queue
	 */
	public static function notifyNewCards(Participant $participant, array $cards, Queue $queue)
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
		$queue->notice($nickname, 'These cards were added to your deck: ' . $cards);
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param Queue $queue
	 */
	public static function noticeInactivePlayer(Game $game, Channel $source, Queue $queue)
	{
		$game->getTimeoutController()->resetTimers();
		$currentParticipant = $game->getPlayerOrder()->getCurrent();
		$queue->privmsg($source->getName(),
			$currentParticipant->getUserObject()->getNickname() . ' did not take action in the last 2 minutes or has left the channel, autoplaying...');
	}
}