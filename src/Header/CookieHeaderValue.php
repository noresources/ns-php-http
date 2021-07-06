<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

/**
 * Cookie header value type
 */
class CookieHeaderValue extends \ArrayObject implements
	HeaderValueInterface
{

	public function __construct($cookies = array())
	{
		parent::__construct($cookies);
	}

	public function __toString()
	{
		return \http_build_query($this->getArrayCopy());
	}

	public static function parseFieldValueString($text)
	{
		$a = [];
		\parse_str($text, $a);
		return [
			new CookieHeaderValue($a),
			\strlen($text)
		];
	}
}