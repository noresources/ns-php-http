<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeInterface;

/**
 * Content-Type header value
 *
 * @see https://tools.ietf.org/html/rfc7231#section-3.1.1.5
 *
 */
class ContentTypeHeaderValue implements HeaderValueInterface
{

	public function __construct(MediaType $mediaType = null)
	{
		$this->mediaType = $mediaType;
	}

	public function __toString()
	{
		if ($this->mediaType instanceof MediaTypeInterface)
			return $this->mediaType->serialize();
		return '';
	}

	/**
	 *
	 * @return \NoreSources\MediaType\MediaType
	 */
	public function getMediaType()
	{
		return $this->mediaType;
	}

	/**
	 *
	 * @param string $text
	 *        	Header value
	 * @return \NoreSources\Http\Header\ContentTypeHeaderValue[]|number[] Array containing The
	 *         HeaderValue and the consumned bytes
	 */
	public static function parseFieldValueString($text)
	{
		$mediaType = new MediaType(null, null);
		$mediaType->unserialize(\trim($text));
		return [
			new ContentTypeHeaderValue($mediaType),
			\strlen($text)
		];
	}

	/**
	 *
	 * @var MediaType
	 */
	private $mediaType;
}