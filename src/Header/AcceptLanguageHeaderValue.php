<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Http\ParameterMap;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Http\QualityValueInterface;
use NoreSources\Http\RFC7230;
use NoreSources\Http\Traits\QualityValueTrait;

class AcceptLanguageHeaderValue implements HeaderValueInterface,
	QualityValueInterface
{
	use QualityValueTrait;

	public function __construct($languageRange = '*')
	{
		$this->languageRange = $languageRange;
		$this->setQualityValue(1.0);
	}

	public function __toString()
	{
		$s = $this->languageRange;
		if ($this->getQualityValue() < 1)
			$s .= '; ' . $this->getQualityValueParameterString();
		return $s;
	}

	public function getLanguageRange()
	{
		return $this->languageRange;
	}

	public static function parseFieldValueString($text)
	{
		$semicolon = \strpos($text, ';');
		if ($semicolon === false)
		{
			$match = [];
			if (!\preg_match(
				chr(1) . RFC7230::OWS_PATTERN . '(' .
				RFC7230::TOKEN_PATTERN . ')' . chr(1), $text, $match))
				throw new \InvalidArgumentException(
					$text . ' ns not a valid Language range');

			return [
				new AcceptLanguageHeaderValue(\trim($match[1])),
				\strlen($match[0])
			];
		}

		$range = \trim(\substr($text, 0, $semicolon));
		$consumed = $semicolon + 1;
		$text = \substr($text, $consumed);

		$value = new AcceptLanguageHeaderValue($range);

		$q = new ParameterMap();
		$consumed += ParameterMapSerializer::unserializeParameters($q,
			$text,
			function ($n, $v) {

				return (\strcasecmp($n, 'q') == 0) ? ParameterMapSerializer::ACCEPT : ParameterMapSerializer::ABORT;
			});

		if ($q->has('q'))
			$value->setQualityValue(\floatval($q->get('q')));

		return [
			$value,
			$consumed
		];
	}

	private $languageRange;
}