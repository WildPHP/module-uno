<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Uno;


class GameResponses
{
	const REVERSED = 'The order of players has been reversed.';
	const SKIPPED_TWOPLAYER = '%s skipped a turn (two-player game).';
	const DRAW_PLAYED = '%s drew %d cards and skipped a turn.';
	const DRAW_PLAYED_REPILE = '%s drew %d cards and skipped a turn. All previously played cards were repiled to the deck.';
	const SKIPPED = '%s skipped a turn.';
}