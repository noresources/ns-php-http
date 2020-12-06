<?php
/**
 * Copyright © 2012 - 2020 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package Http
 */
namespace NoreSources\Http\Server;

use NoreSources\Container;
use NoreSources\TypeDescription;
use NoreSources\Http\UploadedFile;
use NoreSources\Http\Header\ContentDispositionHeaderValue;
use NoreSources\Http\Header\ContentTypeHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderFieldMap;
use NoreSources\Http\Header\HeaderFieldParser;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\HeaderValueInterface;
use NoreSources\MediaType\MediaRange;
use NoreSources\MediaType\MediaTypeFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MultipartFormDataRequestBodyParserMiddleware implements
	MiddlewareInterface
{

	/**
	 * Field names with square bracket syntax processing
	 *
	 * @var string
	 */
	const INDEXED_FIELD_NAME = 'index';

	/**
	 * Process field names with square brackets like PHP do.
	 *
	 * This is the default behavior.
	 *
	 * @var integer
	 */
	const INDEXED_FIELD_NAME_PHP = 0;

	/**
	 * Do not process field names with trailing square brackets
	 *
	 * @var integer
	 */
	const INDEXED_FIELD_NAME_IGNORE = 1;

	const DUPLICATED_FIELD_NAME = 'duplicate';

	const DUPLICATED_FIELD_NAME_OVERRIDE = 0;

	const DUPLICATED_FIELD_NAME_ARRAY = 1;

	public function __construct()
	{
		$this->options = [];
	}

	/**
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 */
	public function setOption($key, $value)
	{
		Container::setValue($this->options, $key, $value);
		return $this;
	}

	/**
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getOption($key)
	{
		return Container::keyValue($this->options, $key, 0);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Psr\Http\Server\MiddlewareInterface::process()
	 */
	public function process(ServerRequestInterface $request,
		RequestHandlerInterface $handler): ResponseInterface
	{
		if (!$request->hasHeader(HeaderField::CONTENT_TYPE))
			return $handler->handle($request);

		/**
		 *
		 * @var ContentTypeHeaderValue $contentType
		 */
		$contentType = HeaderValueFactory::fromMessage($request,
			HeaderField::CONTENT_TYPE);

		/**
		 *
		 * @var MediaRange $mediaType
		 */
		$mediaType = $contentType->getMediaType();

		if (\strcasecmp($mediaType, 'multipart/form-data'))
			return $handler->handle($request);

		$stream = $request->getBody();
		$files = $request->getUploadedFiles();
		$fields = $request->getParsedBody();

		$boundary = $mediaType->getParameters()['boundary'];
		$boundaryLength = \strlen($boundary);

		$boundaryLine = '--' . $boundary . self::EOL;
		$boundaryLineLength = \strlen($boundaryLine);

		$stream->rewind();
		while (!$stream->eof())
		{
			// First line must be the boundary
			$line = $stream->read($boundaryLineLength);
			if ($line != $boundaryLine)
			{
				break;
			}

			// Headers
			$parser = new HeaderFieldParser('\strtolower',
				[
					HeaderValueFactory::class,
					'fromKeyValue'
				]);
			$headers = new HeaderFieldMap($parser->parse($stream));

			$this->processPart($fields, $files, $headers, $stream,
				$boundary, $this->options);
		} // eof

		return $handler->handle(
			$request->withUploadedFiles($files)
				->withParsedBody($fields));
	}

	private function processPart(&$fields, &$files,
		HeaderFieldMap $headers, StreamInterface $stream, $boundary)
	{
		$disposition = Container::keyValue($headers,
			HeaderField::CONTENT_DISPOSITION);

		if ($disposition instanceof ContentDispositionHeaderValue)
		{
			if ($disposition->getParameters()->offsetExists('filename'))
				return $this->processFilePart($files, $headers, $stream,
					$boundary, $disposition);
			else
				return $this->processFormData($fields, $headers, $stream,
					$boundary, $disposition);
		}
	}

	private function processFormData(&$fields, HeaderFieldMap $headers,
		StreamInterface $stream, $boundary,
		ContentDispositionHeaderValue $disposition)
	{
		$name = $disposition->getParameters()['name'];
		$key = null;
		$indexedFieldProcess = Container::keyValue($this->options,
			self::INDEXED_FIELD_NAME, self::INDEXED_FIELD_NAME_PHP);
		$duplicated = Container::keyValue($this->options,
			self::DUPLICATED_FIELD_NAME,
			self::DUPLICATED_FIELD_NAME_OVERRIDE);
		if ($indexedFieldProcess == self::INDEXED_FIELD_NAME_PHP)
		{
			if (\preg_match('/^(.+?)\[(.*?)\]/', $name, $m))
			{
				$name = $m[1];
				$key = $m[2];
				if (ctype_digit($key))
					$key = \intval($key);
			}
		}

		$data = null;

		$contentLength = Container::keyValue($headers,
			HeaderField::CONTENT_LENGTH);

		if ($contentLength instanceof HeaderValueInterface)
		{
			$length = $contentLength->getIntegerValue();
			$data = $stream->read($length);
		}
		else
		{
			$bl = "--" . $boundary;
			$bs = \strlen($bl);
			$t = $stream->tell();
			$offset = 0;
			$data = '';

			while (!$stream->eof())
			{
				$data .= $stream->read(1024);
				$p = \strpos($data, $bl, $offset);
				if ($p !== false)
				{
					$data = \substr($data, 0, $p - self::EOL_LENGTH);
					$stream->seek(
						$t + \strlen($data) + self::EOL_LENGTH);
					break;
				}

				$offset = max(0, $offset - $bs);
			}
		}

		if (!\is_array($fields))
			$fields = [];

		if ($key !== null)
		{
			if (Container::keyExists($fields, $name))
			{
				if (!\is_array($fields[$name]))
					$fields[$name] = [
						$fields[$name]
					];

				if (\is_string($key) && empty($key))
					$fields[$name][] = $data;
				else
					$fields[$name][$key] = $data;
			}
			else
				$fields[$name] = [
					$key => $data
				];
		}
		elseif (Container::keyExists($fields, $name) &&
			($duplicated == self::DUPLICATED_FIELD_NAME_ARRAY))
		{
			if (!\is_array($fields[$name]))
				$fields[$name] = [
					$fields[$name]
				];

			$fields[$name][] = $data;
		}
		else
			$fields[$name] = $data;
	}

	private function processFilePart(&$files, HeaderFieldMap $headers,
		StreamInterface $stream, $boundary,
		ContentDispositionHeaderValue $disposition)
	{
		$name = $disposition->getParameters()['name'];
		$filename = $disposition->getParameters()['filename'];
		$contentLength = Container::keyValue($headers,
			HeaderField::CONTENT_LENGTH);
		$contentType = Container::keyValue($headers,
			HeaderField::CONTENT_TYPE);

		$length = null;
		$mediaType = null;

		if ($contentLength instanceof HeaderValueInterface)
			$length = \intval($contentLength->getValue());

		if ($contentType instanceof ContentTypeHeaderValue)
			$mediaType = \strval($contentType->getMediaType());

		if (!\is_array($files))
			$files = [];

		$directory = \sys_get_temp_dir();

		if (!(\is_string($directory) && \is_dir($directory)))
		{
			$files[$name] = new UploadedFile(null, $length, $filename,
				$mediaType, UPLOAD_ERR_NO_TMP_DIR);
			return;
		}

		$resource = \fopen(
			\tempnam($directory,
				TypeDescription::getLocalName(static::class)), 'wb');
		if (!(\is_resource($resource) &&
			\get_resource_type($resource) == 'stream'))
		{
			$files[$name] = new UploadedFile(null, $length, $filename,
				$mediaType, UPLOAD_ERR_NO_FILE);
			return;
		}

		$metadata = stream_get_meta_data($resource);
		$uri = Container::keyValue($metadata, 'uri');

		if ($length)
		{
			$read = 0;
			for ($s = $length; $read > 0 && $s > 0; $s -= $read)
				$read = \fwrite($resource, 1024);
		}
		else
		{
			$bl = "--" . $boundary;
			$bs = \strlen($bl);
			$t = $stream->tell();
			$buffer = '';
			$length = 0;

			while (!$stream->eof())
			{
				$buffer .= $stream->read(1024);
				$p = \strpos($buffer, $bl);
				if ($p !== false)
				{
					$p = max(0, $p - self::EOL_LENGTH);
					$length += $p;
					$result = \fwrite($resource, $buffer, $p);

					if ($result === false)
					{
						$files[$name] = new UploadedFile($uri, $length,
							$filename, $mediaType, UPLOAD_ERR_CANT_WRITE);
						return;
					}

					$stream->seek($t + $length + self::EOL_LENGTH);
					break;
				}

				$bufferLength = \strlen($buffer);
				$w = max(0, $bufferLength - $bs);
				$result = \fwrite($resource, $buffer, $w);
				if ($result === false)
				{
					$files[$name] = new UploadedFile($uri, $length,
						$filename, $mediaType, UPLOAD_ERR_CANT_WRITE);
					return;
				}
				$length += $w;
				$buffer = \substr($buffer, $w);
			}
		}

		$result = \fclose($resource);
		if ($result === false)
		{
			$files[$name] = new UploadedFile($uri, $length, $filename,
				$mediaType, UPLOAD_ERR_CANT_WRITE);
			return;
		}

		if ($mediaType === null)
		{
			try
			{
				$mediaType = MediaTypeFactory::fromMedia($uri);
			}
			catch (\Exception $e)
			{}
		}

		$files[$name] = new UploadedFile($uri, $length, $filename,
			$mediaType, UPLOAD_ERR_OK);
	}

	const EOL = "\r\n";

	const EOL_LENGTH = 2;

	/**
	 *
	 * @var array
	 */
	private $options;
}