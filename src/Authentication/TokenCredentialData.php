<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Authentication;

/**
 * Credential data containing a Base64 token value
 */
class TokenCredentialData implements CredentialDataInterface
{

	/**
	 * Base64 token credential data
	 *
	 * @return string
	 */
	public function getTokenValue()
	{
		return $this->token;
	}

	/**
	 *
	 * @param string $token68
	 *        	Token68 string from Authorization header value
	 */
	public function __construct($token68)
	{
		$this->token = $token68;
	}

	public function __toString()
	{
		return $this->token;
	}

	public function setTokenValue($token68)
	{
		$this->token = $token68;
	}

	/**
	 * Base64 token credential data
	 *
	 * @var string
	 */
	private $token;
}

