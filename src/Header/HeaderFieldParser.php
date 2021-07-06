<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use Psr\Http\Message\StreamInterface;

/**
 * Parse HTTP message header fields
 */
class HeaderFieldParser
{

	/**
	 *
	 * @param callable $headerNameProcessor
	 * @param callable $headerValueProcessor
	 * @throws \InvalidArgumentException
	 */
	public function __construct($headerNameProcessor = null,
		$headerValueProcessor = null)
	{
		if ($headerNameProcessor !== null)
			if (!\is_callable($headerNameProcessor))
				throw new \InvalidArgumentException(
					'headerNameProcessor is not callable');

		$this->headerNameProcessor = $headerNameProcessor;

		if ($headerValueProcessor !== null)
			if (!\is_callable($headerValueProcessor))
				throw new \InvalidArgumentException(
					'headerValueProcessor is not callable');

		$this->headerValueProcessor = $headerValueProcessor;
	}

	/**
	 *
	 * @param StreamInterface $stream
	 *        	Request header stream
	 * @throws \ErrorException
	 * @return mixed[]
	 */
	public function parse(StreamInterface $stream)
	{
		$headerLines = [];
		$eoh = !$stream->eof();
		do
		{
			$line = '';
			while (!$stream->eof() && ($c = $stream->read(1)) != "\r")
				$line .= $c;

			if ($stream->eof())
				break;

			if (($c = $stream->read(1)) != "\n")
				break;

			if (\strlen($line) == 0)
				break;

			$trimmed = \ltrim($line);

			if (\strlen($trimmed) < \strlen($line))
				$headerLines[\count($headerLines) - 1] .= ' ' . $trimmed;
			else
				$headerLines[] = $trimmed;
		}
		while (\strlen($line));

		$headers = [];
		foreach ($headerLines as $headerLine)
		{
			$colon = \strpos($headerLine, ':');
			if ($colon == false)
				throw new \ErrorException('Invalid header line format');

			$headerName = \trim(\substr($headerLine, 0, $colon));
			$headerValue = \ltrim(\substr($headerLine, $colon + 1));

			if (\is_callable($this->headerNameProcessor))
				$headerName = \call_user_func(
					$this->headerNameProcessor, $headerName);

			if (\is_callable($this->headerValueProcessor))
				$headerValue = \call_user_func(
					$this->headerValueProcessor, $headerName,
					$headerValue);

			$headers[$headerName] = $headerValue;
		}

		return $headers;
	}

	/**
	 *
	 * @var callable
	 */
	private $headerNameProcessor;

	/**
	 *
	 * @var callable
	 */
	private $headerValueProcessor;
}