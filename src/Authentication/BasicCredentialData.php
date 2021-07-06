<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 *
 * @package HTTP
 */
namespace NoreSources\Http\Authentication;

use NoreSources\Http\Header\InvalidHeaderException;

/**
 * Basic authentication scheme user-password credential data
 */
class BasicCredentialData extends TokenCredentialData
{

	/**
	 *
	 * @param string $token68
	 *        	Token68 string from Authorization header value
	 */
	public function __construct($token68)
	{
		parent::__construct($token68);
		list ($this->user, $this->password) = self::parseUserPassword(
			$token68);
	}

	public function setTokenValue($token68)
	{
		parent::setTokenValue($token68);
		list ($this->user, $this->password) = self::parseUserPassword(
			$token68);
	}

	/**
	 *
	 * @return string
	 */
	public function getUser()
	{
		return $this->user;
	}

	/**
	 *
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 *
	 * @param string $user
	 * @param string $password
	 */
	public function setUserPassword($user, $password)
	{
		$this->user = $user;
		$this->password = $password;
		parent::setTokenValue(
			\base64_encode($this->user . ';' . $this->password));
	}

	/**
	 *
	 * @param string $token68
	 *        	Token68 string from Authorization header value
	 * @throws InvalidHeaderException
	 * @return string[] Decoded user and password
	 */
	public static function parseUserPassword($token68)
	{
		$s = \base64_decode($token68);
		$p = \strpos($s, ':');
		if ($p === false)
			throw new InvalidHeaderException(
				'Invalid userpass string (missing colon)',
				InvalidHeaderException::INVALID_HEADER_VALUE);
		return [
			\substr($s, 0, $p),
			\substr($s, $p + 1)
		];
	}

	/**
	 *
	 * @var string
	 */
	private $user;

	/**
	 *
	 * @var string
	 */
	private $password;
}

