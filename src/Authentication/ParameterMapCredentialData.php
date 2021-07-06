<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Authentication;

use NoreSources\Container\Container;
use NoreSources\Http\ParameterMap;
use NoreSources\Http\ParameterMapProviderInterface;
use NoreSources\Http\ParameterMapProviderTrait;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Http\RFC7230;
use NoreSources\Http\RFC7235;
use NoreSources\Http\Header\InvalidHeaderException;

/**
 * Credential data made of a list of authentication parameters
 */
class ParameterMapCredentialData implements CredentialDataInterface,
	ParameterMapProviderInterface
{

	use ParameterMapProviderTrait;

	public function __construct($parameters)
	{
		$this->parameters = new ParameterMap();
		if (\is_string($parameters))
		{
			$length = \strlen($data);
			$consumed = ParameterMapSerializer::unserializeParameters(
				$this->parameters, $parameters,
				[
					ParameterMapSerializer::OPTION_DELIMITER => ',',
					ParameterMapSerializer::OPTION_WHITESPACE_PATTERN => RFC7230::BWS_PATTERN,
					ParameterMapSerializer::OPTION_PATTERN => RFC7235::AUTH_PARAM_PATTERN
				]);
		}
		elseif (Container::isTraversable($parameters))
		{
			foreach ($parameters as $key => $value)
				$this->parameters[$key] = $value;
		}
		else
			throw new InvalidHeaderException('Invalid credential data',
				InvalidHeaderException::INVALID_HEADER_VALUE);
	}

	public function __toString()
	{
		return ParameterMapSerializer::serializeParameters(
			$this->parameters, ', ');
	}
}

