<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Container\Container;
use NoreSources\Http\RFC7235;
use NoreSources\Http\Authentication\AuthenticationScheme;
use NoreSources\Http\Authentication\CredentialDataFactory;
use NoreSources\Http\Authentication\CredentialDataInterface;

class AuthorizationHeaderValue implements HeaderValueInterface
{

	/**
	 *
	 * @param string $text
	 * @throws InvalidHeaderException
	 * @return \NoreSources\Http\Header\AuthorizationHeaderValue
	 */
	public static function fromString($text)
	{
		$mainPattern = '(?<scheme>' . RFC7235::AUTH_SCHEME_PATTERN .
			')(?:\x20+(?<data>.+))?';
		$matches = [];
		if (!\preg_match(chr(1) . $mainPattern . chr(1), $text, $matches))
			throw new InvalidHeaderException(
				'Unrecognized credentials pattern',
				InvalidHeaderException::INVALID_HEADER_VALUE);

		$scheme = $matches['scheme'];
		$credentialData = null;
		if (($data = Container::keyValue($matches, 'data')))
			$credentialData = CredentialDataFactory::createFromString(
				$scheme, $data);

		return new AuthorizationHeaderValue($scheme, $credentialData);
	}

	/**
	 *
	 * @param string $scheme
	 *        	Authentication scheme
	 * @param string|array $data
	 *        	Deserialized credential data
	 */
	public function __construct($scheme = AuthenticationScheme::BASIC,
		$data = null)
	{
		$this->scheme = $scheme;
		$this->credentialData = $data;
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \NoreSources\StringRepresentation::__toString()
	 */
	public function __toString()
	{
		$s = $this->scheme;
		if ($this->credentialData instanceof CredentialDataInterface)
			$s .= ' ' . \strval($this->credentialData);
		return $s;
	}

	/**
	 *
	 * @param string $scheme
	 *        	Authentication scheme
	 * @return boolean
	 */
	public function isScheme($scheme)
	{
		return \strcasecmp($this->scheme, $scheme) == 0;
	}

	/**
	 *
	 * @return string
	 */
	public function getScheme()
	{
		return $this->scheme;
	}

	/**
	 *
	 * @return \NoreSources\Http\Authentication\CredentialDataInterface
	 */
	public function getCredentialData()
	{
		return $this->credentialData;
	}

	/**
	 * Credential scheme
	 *
	 * @var string
	 */
	private $scheme;

	/**
	 * Credentinal data
	 *
	 * @var CredentialDataInterface
	 */
	private $credentialData;
}
