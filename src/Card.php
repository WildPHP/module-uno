<?php
/**
 * WildPHP - an advanced and easily extensible IRC bot written in PHP
 * Copyright (C) 2017 WildPHP
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace WildPHP\Modules\Uno;


use WildPHP\Core\Connection\TextFormatter;

class Card
{
	protected const COLORMAP = [
		'r' => 'red',
		'g' => 'green',
		'b' => 'teal',
		'y' => 'yellow'
	];

	protected const STRINGMAP = [
		'color' => [
			'r' => 'Red',
			'g' => 'Green',
			'b' => 'Blue',
			'y' => 'Yellow',
			'w' => 'Wild'
		],
		'type' => [
			'd' => 'Draw Two',
			's' => 'Skip',
			'r' => 'Reverse'
		]
	];

	/**
	 * @var string
	 */
	protected $color = '';

	/**
	 * @var int|string
	 */
	protected $type = 0;

	/**
	 * Card constructor.
	 *
	 * @param string $color
	 * @param string $type
	 */
	public function __construct(string $color, string $type = '')
	{
		$this->setColor($color);
		$this->setType($type);
	}

	/**
	 * @return string
	 */
	public function getColor(): string
	{
		return $this->color;
	}

	/**
	 * @param string $color
	 */
	public function setColor(string $color)
	{
		$this->color = $color;
	}

	/**
	 * @return string|int
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param string|int $type
	 */
	public function setType($type)
	{
		$this->type = $type;
	}

	/**
	 * @param Card $card
	 *
	 * @return bool
	 */
	public function compatible(Card $card)
	{
		if ($this->getColor() == 'w' || $card->getColor() == 'w')
			return true;

		return $this->getColor() == $card->getColor() || $this->getType() == $card->getType();
	}

	/**
	 * @return string
	 */
	public function toString(): string
	{
		return $this->getColor() . $this->getType();
	}

	/**
	 * @return string
	 */
	public function format()
	{
		$color = $this->getColor();

		if ($color == 'w' || !array_key_exists($color, self::COLORMAP))
			return TextFormatter::bold($this->toString());

		$color = self::COLORMAP[$color];
		return TextFormatter::bold(TextFormatter::color($this->toString(), $color));
	}

	/**
	 * @return string
	 */
	public function toHumanString()
	{
		if ($this->toString() == 'wd')
			return 'Wild Draw Four';

		$color = $this->getColor();
		$type = $this->getType();
		$color = self::STRINGMAP['color'][$color] ?? $color;
		$type = self::STRINGMAP['type'][$type] ?? $type;
		return $color . (!empty($type) ? ' ' . $type : '');
	}

	/**
	 * @param string $card
	 *
	 * @return Card
	 */
	public static function fromString(string $card): Card
	{
		$color = $card[0];
		$type = $card[1] ?? '';
		$card = new self($color, $type);
		return $card;
	}
}