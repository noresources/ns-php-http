<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Authentication;

use NoreSources\Http\RFC7235;
use NoreSources\Http\Header\InvalidHeaderException;

/**
 * Create CredentialDataInterface concrete class instance
 * according Authentication scheme
 */
class CredentialDataFactory
{

	/**
	 *
	 * @param string $scheme
	 *        	Authentication scheme
	 * @param string $stringData
	 *        	Credential data text string
	 * @throws InvalidHeaderException
	 * @return CredentialDataInterface
	 */
	public static function createFromString($scheme, $stringData)
	{
		$matches = [];
		if (\strcasecmp($scheme, AuthenticationScheme::BASIC) == 0)
			return new BasicCredentialData($stringData);
		elseif (\preg_match(
			chr(1) . '^' . RFC7235::TOKEN68_PATTERN . '$' . chr(1),
			$stringData, $matches))
			return new TokenCredentialData($stringData);
		elseif (\preg_match(chr(1),
			'^' . RFC7235::AUTH_PARAM_LIST_PATTERN . '$' . chr(1),
			$stringData, $matches))
		{
			return new ParameterMapCredentialData($stringData);
		}

		throw new InvalidHeaderException('Invalid credential data',
			InvalidHeaderException::INVALID_HEADER_VALUE);
	}
}