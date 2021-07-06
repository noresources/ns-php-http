<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Test;

use Laminas\Diactoros\Stream;
use Psr\Http\Message\StreamInterface;

class Utility
{

	/**
	 *
	 * @param string $text
	 * @return StreamInterface
	 */
	public static function createStreamFromText($text, $encode = false)
	{
		$url = 'data://text/plain,';
		if ($encode)
		{
			$url .= 'base64,';
			$text = \base64_encode($text);
		}
		$stream = \fopen($url . $text, 'r');

		assert(\is_resource($stream));

		$instance = new Stream($stream);
		$instance->rewind();
		return $instance;
	}
}