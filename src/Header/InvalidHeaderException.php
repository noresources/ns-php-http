<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Http\Status;
use NoreSources\Http\StatusExceptionInterface;
use NoreSources\Http\Traits\StatusExceptionTrait;

/**
 * Exception raised when a headre line content is not valid
 */
class InvalidHeaderException extends \ErrorException implements
	StatusExceptionInterface
{

	use StatusExceptionTrait;

	const INVALID_HEADER_NAME = 1;

	const INVALID_HEADER_VALUE = 2;

	const INVALID_HEADER_LINE = 3;

	public function getHeaderErrorType()
	{
		return $this->headerErrorType;
	}

	/**
	 *
	 * @param string $text
	 *        	Header line, name or value
	 * @param integer $type
	 *        	[1-3] Header component which is not valid.
	 */
	public function __construct($text, $type)
	{
		$genericTexts = [
			self::INVALID_HEADER_LINE => 'Invalid header line format',
			self::INVALID_HEADER_NAME => 'Invalid header name',
			self::INVALID_HEADER_VALUE => 'Invalid header value'
		];

		if (\array_key_exists($type, $genericTexts))
			$text = $genericTexts[$type] . ': ' . $text;

		parent::__construct($text, Status::BAD_REQUEST);
		$this->headerErrorType = $type;
	}

	private $headerErrorType;
}