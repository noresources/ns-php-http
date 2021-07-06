<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

class StreamException extends \RuntimeException
{

	const ERROR_GENERIC = 0;

	const ERROR_OPEN = 1;

	const ERROR_SEEK = 2;

	const ERROR_READ = 3;

	const ERROR_WRITE = 4;

	public function __construct($message, $code = self::ERROR_GENERIC)
	{
		parent::__construct($message, $code);
	}
}