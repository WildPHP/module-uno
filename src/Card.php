<?php
/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
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

		return ($this->getColor() == $card->getColor()) || ($this->getType() == $card->getType());
	}

	/**
	 * @return string
	 */
	public function __toString(): string
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
			return TextFormatter::bold($this->__toString());

		$color = self::COLORMAP[$color];
		return TextFormatter::bold(TextFormatter::color($this->__toString(), $color));
	}

	/**
	 * @return string
	 */
	public function toHumanString()
	{
		if ($this->__toString() == 'wd')
			return 'Wild Draw Four';

		$color = $this->getColor();
		$type = $this->getType();
		$color = self::STRINGMAP['color'][$color] ?? $color;
		$type = self::STRINGMAP['type'][$type] ?? $type;
		return trim($color . ($type !== false ? ' ' . $type : ''));
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