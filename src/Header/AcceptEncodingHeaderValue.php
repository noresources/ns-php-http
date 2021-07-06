<?php

/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Http
 */
namespace NoreSources\Http\Header;

use NoreSources\Container\Container;
use NoreSources\Http\Coding\ContentCoding;
use NoreSources\Http\Traits\QualityValueTrait;

class AcceptEncodingHeaderValue implements HeaderValueInterface,
	\NoreSources\Http\QualityValueInterface
{
	use QualityValueTrait;

	const ANY = '*';

	public function __construct($coding = ContentCoding::IDENTITY)
	{
		$this->coding = $coding;
		$this->setQualityValue(1.0);
	}

	public function __toString()
	{
		$s = $this->coding;
		if (\strlen($s) && $this->getQualityValue() < 1)
			$s .= '; ' . $this->getQualityValueParameterString();
		return $s;
	}

	public function getCoding()
	{
		return (empty($this->coding) ? ContentCoding::IDENTITY : $this->coding);
	}

	public static function parseFieldValueString($text)
	{
		$parser = new HeaderTokenValueParser(static::class,
			[
				static::class,
				'validateToken'
			]);
		return $parser->parseText($text);
	}

	public static function validateToken($token)
	{
		$constants = new \ReflectionClass(ContentCoding::class);
		$valid = Container::values($constants->getConstants());
		$valid[] = self::ANY;
		$valid[] = '';
		return Container::valueExists($valid, \strtolower($token));
	}

	private $coding;
}
