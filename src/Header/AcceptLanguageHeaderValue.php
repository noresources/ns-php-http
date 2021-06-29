<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Http\QualityValueInterface;
use NoreSources\Http\Traits\QualityValueTrait;
use NoreSources\Language\LanguageRange;

class AcceptLanguageHeaderValue implements HeaderValueInterface,
	QualityValueInterface
{
	use QualityValueTrait;

	/**
	 *
	 * @param LanguageRange|string $languageRange
	 */
	public function __construct($languageRange = '*')
	{
		if (\is_string($languageRange))
			$languageRange = LanguageRange::fromString($languageRange,
				LanguageRange::TYPE_BASIC);
		$this->languageRange = $languageRange;
		$this->setQualityValue(1.0);
	}

	public function __toString()
	{
		$s = \strval($this->languageRange);
		if ($this->getQualityValue() < 1)
			$s .= '; ' . $this->getQualityValueParameterString();
		return $s;
	}

	/**
	 *
	 * @return LanguageRange
	 */
	public function getLanguageRange()
	{
		return $this->languageRange;
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
		$p = \NoreSources\Language\Constants::RANGE_BASIC_PATTERN;
		$p = '^' . $p . '$';
		return \preg_match(chr(1) . $p . chr(1), $token);
	}

	private $languageRange;
}