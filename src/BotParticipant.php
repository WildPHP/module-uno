<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\Channels\Channel;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\Queue;

class BotParticipant
{
	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param ComponentContainer $container
	 */
	public static function addToGame(Game $game, Channel $source, ComponentContainer $container)
	{
		$ownNickname = Configuration::fromContainer($container)['currentNickname'];
		$botObject = $source->getUserCollection()->findByNickname($ownNickname);
		$game->createParticipant($botObject);
		Queue::fromContainer($container)->privmsg($source->getName(), 'I also entered the game. Brace yourself ;)');
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param Queue $queue
	 */
	public static function playAutomaticCard(Game $game, Channel $source, Queue $queue)
	{
		$game->getTimeoutController()->resetTimers();

		$participant = $game->getPlayerOrder()->getCurrent();
		$validCards = $participant->getDeck()->getValidCards($game->getLastCard());

		// Got no cards, but can still draw
		if (empty((array) $validCards) && !$game->currentPlayerHasDrawn())
		{
			$game->getDealer()->populate($participant->getDeck(), 1);
			$validCards = $participant->getDeck()
				->getValidCards($game->getLastCard());
		}

		// Got no cards, cannot draw again
		if (empty((array) $validCards) && $game->currentPlayerHasDrawn())
		{
			$game->pass();
			$game->advanceNotify();
			return;
		}

		// Sort cards from lowest to highest.
		$validCards->sortCards();

		$withoutWildsOrOtherColors = $validCards->filter(DeckFilters::color($game->getLastCard()->getColor()))->getArrayCopy();
		if (!empty($withoutWildsOrOtherColors))
		{
			shuffle($withoutWildsOrOtherColors);
			$validCards = new Deck($withoutWildsOrOtherColors);
		}

		$card = reset($validCards);
		$result = $game->playCardWithChecks($card, $participant);

		// If we played the last card and won, stop.
		if (!$game->isStarted())
			return;

		// Must we choose a color?
		if (!$result && $game->getPlayerMustChooseColor())
		{
			$deck = $participant->getDeck();

			$count['g'] = count((array) $deck->filter(DeckFilters::color(CardTypes::GREEN)));
			$count['b'] = count((array) $deck->filter(DeckFilters::color(CardTypes::BLUE)));
			$count['y'] = count((array) $deck->filter(DeckFilters::color(CardTypes::YELLOW)));
			$count['r'] = count((array) $deck->filter(DeckFilters::color(CardTypes::RED)));

			$count = array_flip($count);
			ksort($count);
			$color = end($count);
			$color = new Card($color);
			$game->playCard($deck, $color);
			$readableColor = $color->toHumanString();
			$formattedColor = $color->format();
			$queue->privmsg(
				$source->getName(),
				$participant->getUserObject()->getNickname() . ' picked color ' . $readableColor . ' (' . $formattedColor . ')'
			);
		}
		
		$game->advanceNotify();
	}

	/**
	 * @param Game $game
	 * @param Channel $source
	 * @param Queue $queue
	 */
	public static function playAutomaticCardAndNotice(Game $game, Channel $source, Queue $queue)
	{
		Announcer::noticeInactivePlayer($game, $source, $queue);
		static::playAutomaticCard($game, $source, $queue);
	}
}