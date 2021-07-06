<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Request;

use NoreSources\LiteralValueInterface;

class LiteralValueRequestBody implements LiteralValueInterface
{

	/**
	 *
	 * @param null|boolean|integer|float|string $value
	 */
	public function __construct($value)
	{
		$this->value = $value;
	}

	public function getLiteralValue()
	{
		return $this->value;
	}

	/**
	 * Literal value
	 *
	 * @var null|boolean|integer|float|string
	 */
	private $value;
}
