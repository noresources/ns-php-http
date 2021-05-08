<?php
/**
 * Copyright © 2012 - 2020 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package Http
 */
namespace NoreSources\Http\Test;

use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\PhpInputStream;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Utility
{

	/**
	 *
	 * @param string $text
	 * @return StreamInterface
	 */
	public static function createStreamFromText($text)
	{
		$stream = \fopen('data://text/plain,', 'w+');

		assert(\is_resource($stream));

		\fwrite($stream, $text);
		$instance = new Stream($stream);
		$instance->rewind();
		return $instance;
	}
}