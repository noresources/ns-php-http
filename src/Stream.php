<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use NoreSources\Container;
use NoreSources\MediaType\MediaTypeInterface;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 StreamInterface implementation
 *
 * Largely "inspired" by laminas-diactoros implementation.
 */
class Stream implements StreamInterface
{

	const DATA_INPUT_RAW = 'raw';

	const DATA_INPUT_URLENCODED = 'urlencoded';

	const DATA_INPUT_BASE64 = 'base64';

	/**
	 * Create a Stream from binary data
	 *
	 * @param string $data
	 * @param string $dataEncoding
	 *        	Describe how $data is encoded
	 * @param string $mode
	 * @param string|MediaTypeInterface $mediaType
	 * @return StreamInterface
	 */
	public static function fromData($data,
		$dataEncoding = self::DATA_INPUT_RAW, $mode = 'rb',
		$mediaType = 'text/plain')
	{
		if ($mediaType instanceof MediaTypeInterface)
			$mediaType = $mediaType->serialize();

		$uri = 'data://' . $mediaType;

		if ($dataEncoding == self::DATA_INPUT_BASE64)
			$uri .= ';base64,' . $data;
		elseif ($dataEncoding == self::DATA_INPUT_URLENCODED)
			$uri .= ',' . $data;
		else
			$uri .= ';base64,' . \base64_encode($data);

		$resource = \fopen($uri, $mode);
		return new Stream($resource);
	}

	/**
	 *
	 * @param string $filename
	 *        	Filename
	 * @param string $mode
	 *        	File open mode
	 * @return StreamInterface
	 */
	public static function fromFile($filename, $mode = 'r')
	{
		$resource = @\fopen($filename, $mode);
		if (!self::isValidResource($resource))
			throw new StreamException(
				'Failed to open "' . $filename . '" (' . $mode . ')',
				StreamException::ERROR_OPEN);

		return new Stream($resource);
	}

	/**
	 *
	 * @param resource $stream
	 */
	public function __construct($stream)
	{
		if (!self::isValidResource($stream))
			throw new StreamException('Invalid resource');

		$this->resource = $stream;
	}

	public function getMetadata($key = null)
	{
		if (!self::isValidResource($this->resource))
			throw new StreamException('Invalid resource');

		if ($key === null)
			return \stream_get_meta_data($this->resource);

		$m = \stream_get_meta_data($this->resource);

		if (!\array_key_exists($key, $m))
			throw new \InvalidArgumentException($key);

		return $m[$key];
	}

	public function isSeekable()
	{
		if (!self::isValidResource($this->resource))
			return false;

		return Container::keyValue(
			\stream_get_meta_data($this->resource), 'seekable', false);
	}

	public function read($length)
	{
		if (!self::isValidResource($this->resource))
			throw new StreamException('Invalid resource');

		if (!$this->isReadable())
			throw new StreamException('Not readable');

		$result = \fread($this->resource, $length);

		if ($result === false)
			throw new StreamException('Failed to read');

		return $result;
	}

	public function tell()
	{
		if (!self::isValidResource($this->resource))
			throw new StreamException('Invalid stream');

		$result = \ftell($this->resource);
		if (!\is_int($result))
			throw new StreamException('Failed to get pointer position');

		return $result;
	}

	public function isWritable()
	{
		if (!self::isValidResource($this->resource))
			return false;

		$meta = \stream_get_meta_data($this->resource);
		$mode = $meta['mode'];

		return ((\strpos($mode, 'x') !== false) ||
			(\strpos($mode, 'w') !== false) ||
			(\strpos($mode, 'c') !== false) ||
			(\strpos($mode, 'a') !== false) ||
			(\strpos($mode, '+') !== false));
	}

	public function seek($offset, $whence = SEEK_SET)
	{
		if (!self::isValidResource($this->resource))
			throw new StreamException('Invalid resource');

		if (!$this->isSeekable())
			throw new StreamException('Not seekable');

		$result = \fseek($this->resource, $offset, $whence);
		if ($result !== 0)
			throw new StreamException('Failed to seek');
	}

	public function __toString()
	{
		if (!self::isValidResource($this->resource))
			return '';

		try
		{
			if ($this->isSeekable())
				$this->rewind();

			return $this->getContents();
		}
		catch (\Exception $e)
		{
			return '';
		}
	}

	public function getSize()
	{
		if (!self::isValidResource($this->resource))
			return null;

		$stats = \fstat($this->resource);
		if ($stats !== false)
			return $stats['size'];

		return null;
	}

	public function rewind()
	{
		$this->seek(0);
	}

	public function detach()
	{
		$resource = $this->resource;
		$this->resource = null;
		return $resource;
	}

	public function getContents()
	{
		if (!self::isValidResource($this->resource))
			throw new StreamException('Invalid resource');

		$result = \stream_get_contents($this->resource);
		if ($result === false)
			throw new StreamException('Failed to read stream content');

		return $result;
	}

	public function close()
	{
		if (!self::isValidResource($this->resource))
			return;

		$resource = $this->detach();
		fclose($resource);
	}

	public function eof()
	{
		if (!self::isValidResource($this->resource))
			return true;

		return \feof($this->resource);
	}

	public function write($string)
	{
		if (!self::isValidResource($this->resource))
			throw new StreamException('Invalid resource');

		if (!$this->isWritable())
			throw new StreamException('Not writable');

		$result = \fwrite($this->resource, $string);

		if ($result === false)
			throw new StreamException('Failed to write');

		return $result;
	}

	public function isReadable()
	{
		if (!self::isValidResource($this->resource))
			return false;

		$meta = \stream_get_meta_data($this->resource);
		$mode = $meta['mode'];

		return ((\strpos($mode, 'r') !== false) ||
			(\strpos($mode, '+') !== false));
	}

	protected static function isValidResource($resource)
	{
		return \is_resource($resource) &&
			(\get_resource_type($resource) == 'stream');
	}

	/**
	 * Stream resource
	 *
	 * @var resource
	 */
	private $resource;
}