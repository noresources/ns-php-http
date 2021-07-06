<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Container\Container;
use NoreSources\Http\ParameterMapInterface;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Http\QualityValueInterface;

class HeaderValueParameterMapSerializer
{

	/**
	 *
	 * @var HeaderValueInterface
	 */
	public $headerValue;

	/**
	 *
	 * @var ParameterMapInterface
	 */
	public $parameters;

	/**
	 *
	 * @var ParameterMapInterface
	 */
	public $extensions;

	/**
	 *
	 * @var string
	 */
	public $parameterSeparator = ';';

	public function __construct(HeaderValueInterface $headerValue,
		ParameterMapInterface $parameters,
		ParameterMapInterface $extensions = null)
	{
		$this->headerValue = $headerValue;
		$this->parameters = $parameters;
		$this->extensions = $extensions;
	}

	/**
	 *
	 * @param unknown $text
	 * @return number
	 */
	public function unserializeParameters($text)
	{
		$length = \strlen($text);
		$text = \ltrim($text);

		$consumed = $length - \strlen($text);

		$hasQualityValue = false;
		$consumed += ParameterMapSerializer::unserializeParameters(
			$this->parameters, $text,
			function ($name, $value) use (&$hasQualityValue) {

				if (!($this->headerValue instanceof QualityValueInterface))
					return ParameterMapSerializer::ACCEPT;

				if ($hasQualityValue)
				{
					if ($this->extensions instanceof ParameterMapInterface)
						Container::setValue($this->extensions, $name,
							$value);
					return ParameterMapSerializer::IGNORE;
				}

				if (\strcasecmp($name, 'q') == 0)
				{
					$this->headerValue->setQualityValue(
						\floatval($value));
					$hasQualityValue = true;
					return ParameterMapSerializer::IGNORE;
				}

				return ParameterMapSerializer::ACCEPT;
			});

		return $consumed;
	}
}
