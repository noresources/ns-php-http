<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 * @package Http
 */
namespace NoreSources\Http\Header;

class AcceptCharsetHeaderValue implements HeaderValueInterface,
	\NoreSources\Http\QualityValueInterface
{
	use \NoreSources\Http\Traits\QualityValueTrait;

	const ANY = '*';

	public function getCharset()
	{
		return $this->charset;
	}

	public function __construct($charset)
	{
		$this->charset = $charset;
		$this->setQualityValue(1.0);
	}

	public function __toString()
	{
		$s = $this->charset;
		if (\strlen($s) && $this->getQualityValue() < 1)
			$s .= '; ' . $this->getQualityValueParameterString();
		return $s;
	}

	public static function parseFieldValueString($text)
	{
		$parser = new HeaderTokenValueParser(static::class);
		return $parser->parseText($text);
	}

	/**
	 *
	 * @var string
	 */
	private $charset;
}
