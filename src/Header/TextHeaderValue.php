<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Type\TypeConversion;

/**
 * Arbitrary string header value
 */
class TextHeaderValue implements HeaderValueInterface
{

	/**
	 *
	 * @param unknown $value
	 *        	Any stringyfiable value
	 */
	public function __construct($value)
	{
		$this->stringValue = TypeConversion::toString($value);
	}

	public function __toString()
	{
		return $this->stringValue;
	}

	/**
	 *
	 * @param string $text
	 * @return \NoreSources\Http\Header\TextHeaderValue
	 */
	public static function fromString($text)
	{
		return new TextHeaderValue($text);
	}

	private $stringValue;
}