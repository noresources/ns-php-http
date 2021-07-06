<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Http\ParameterMap;
use NoreSources\Http\ParameterMapProviderInterface;
use NoreSources\Http\ParameterMapProviderTrait;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Http\RFC7230;

class ContentDispositionHeaderValue implements HeaderValueInterface,
	ParameterMapProviderInterface
{
	use ParameterMapProviderTrait;

	const INLINE = 'inline';

	const ATTACHEMENT = 'attachement';

	public function __construct($type, $parameters = array())
	{
		$this->dispositionType = $type;
		$this->parameters = new ParameterMap($parameters);
	}

	public function __toString()
	{
		$s = $this->dispositionType;
		if ($this->getParameters()->count())
			$s .= '; ' .
				ParameterMapSerializer::serializeParameters(
					$this->getParameters());
		return $s;
	}

	/**
	 *
	 * @param string $type
	 * @return boolean
	 */
	public function isType($type)
	{
		return \strcasecmp($this->dispositionType, $type) == 0;
	}

	public static function parseFieldValueString($text)
	{
		$match = [];
		$pattern = RFC7230::OWS_PATTERN . '(' . RFC7230::TOKEN_PATTERN .
			')';
		if (!\preg_match(chr(1) . $pattern . chr(1), $text, $match))
			throw new \InvalidArgumentException(
				'Invalid content disposition type');

		$type = $match[1];
		$consumed = \strlen($match[0]);
		$headerValue = new ContentDispositionHeaderValue($type);

		$semicolon = \strpos($text, ';', $consumed);
		if ($semicolon !== false)
		{
			$consumed = $semicolon + 1;
			$parameters = $headerValue->getParameters();
			$consumed += ParameterMapSerializer::unserializeParameters(
				$parameters, \substr($text, $consumed));
		}

		return [
			$headerValue,
			$consumed
		];
	}

	/**
	 *
	 * @var string
	 */
	private $dispositionType;
}