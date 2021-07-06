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
use NoreSources\Http\Traits\QualityValueTrait;
use NoreSources\MediaType\MediaRange;
use NoreSources\MediaType\MediaTypeInterface;

class AcceptHeaderValue implements HeaderValueInterface,
	QualityValueInterface
{
	use QualityValueTrait;

	public function __construct(MediaRange $range = null,
		$extensions = array())
	{
		$this->setQualityValue(1.0);
		$this->mediaRange = $range;
		$this->extensions = new ParameterMap($extensions);
	}

	public function __toString()
	{
		$s = ($this->mediaRange instanceof MediaTypeInterface) ? $this->mediaRange->serialize() : '*/*';
		if ($this->getQualityValue() < 1)
			$s .= '; ' . $this->getQualityValueParameterString();
		if ($this->extensions->count())
			$s .= '; ' .
				ParameterMapSerializer::serializeParameters(
					$this->extensions);
		return $s;
	}

	/**
	 *
	 * @return \NoreSources\MediaType\MediaRange
	 */
	public function getMediaRange()
	{
		return $this->mediaRange;
	}

	/**
	 *
	 * @return \NoreSources\Http\ParameterMapInterface
	 */
	public function getExtensions()
	{
		return $this->extensions;
	}

	public static function parseFieldValueString($text)
	{
		$s = \strlen($text);
		$text = \ltrim($text);
		$length = \strlen($text);
		$consumed = $s - $length;

		$semicolon = \strpos($text, ';');
		$comma = \strpos($text, ',');

		if ($comma !== false)
		{
			if ($semicolon === false || $semicolon > $comma)
				$semicolon = false;
		}

		if ($semicolon === false)
		{
			$mediaRange = MediaRange::fromString($text, false);
			return [
				new AcceptHeaderValue($mediaRange),
				$consumed + \strlen(\strval($mediaRange))
			];
		}

		$mediaRange = new MediaRange();
		$part = \substr($text, 0, $semicolon);
		$mediaRange->unserialize(\trim($part));

		$accept = new AcceptHeaderValue($mediaRange);

		$consumed = $semicolon + 1;
		$text = \substr($text, $consumed);

		$serializer = new HeaderValueParameterMapSerializer($accept,
			$mediaRange->getParameters(), $accept->getExtensions());
		$consumed += $serializer->unserializeParameters($text);
		return [
			$accept,
			$consumed
		];
	}

	/**
	 *
	 * @var MediaRange
	 */
	private $mediaRange;

	/**
	 *
	 * @var ParameterMap
	 */
	private $extensions;
}