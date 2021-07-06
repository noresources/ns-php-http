<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use const Nette\PhpGenerator\Type\NULL;
use NoreSources\Container\Container;
use NoreSources\MediaType\MediaTypeInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{

	public static function fromGlobal($key)
	{
		if (!isset($_FILES))
			throw new UploadedFileException('$_FILES not set');

		if (!Container::keyExists($_FILES, $key))
			throw neew\InvalidArgumentException('Invalid key ' . $key);

		$entry = $_FILES[$key];

		return new UploadedFile(Container::keyValue($entry, 'tmp_file'),
			Container::keyValue($entry, 'size'),
			Container::keyValue($entry, 'name'),
			Container::keyValue($entry, 'type'),
			Container::keyValue($entry, 'error'));
	}

	public function getError()
	{
		return $this->errorCode;
	}

	public function getSize()
	{
		return $this->fileSize;
	}

	public function getClientFilename()
	{
		return $this->clientFilename;
	}

	public function getStream()
	{
		if ($this->flags & self::MOVED)
			throw new UploadedFileException('File moved');

		return Stream::fromFile($this->filePath);
	}

	public function getClientMediaType()
	{
		if (!\is_null($this->clientMediaType))
			return \strval($this->clientMediaType);

		return $this->clientMediaType;
	}

	public function moveTo($targetPath)
	{
		if ($this->flags & self::MOVED)
			throw new UploadedFileException('File already moved');

		if ($this->errorCode != UPLOAD_ERR_OK)
			throw new UploadedFileException('Upload error',
				$this->errorCode);

		if (!(\is_string($targetPath) && \strlen($targetPath)))
			throw new \InvalidArgumentException('Invalid target');

		if (!\is_uploaded_file($this->filePath) || (PHP_SAPI == 'cli') ||
			(PHP_SAPI == 'phpdbg'))
		{
			$output = fopen($targetPath, 'wb+');
			if ($output === false)
				throw new UploadedFileException('Failed to move file');
			$input = $this->getStream();
			$input->rewind();
			while (!$input->eof())
				fwrite($output, $input->read(4096));
			fclose($output);

			unlink($this->filePath);
		}
		else
		{
			$result = \move_uploaded_file($this->filePath, $targetPath);
			if ($result === false)
				throw new UploadedFileException(
					'Failed to move ' . $this->filePath . ' to ' .
					$targetPath);
		}

		$this->flags |= self::MOVED;
	}

	public function __construct($path, $size = NULL, $name = null,
		$mediaType = NULL, $error = UPLOAD_ERR_OK)
	{
		$this->filePath = $path;
		$this->fileSize = $size;
		$this->errorCode = $error;

		$this->clientFilename = $name;
		$this->clientMediaType = $mediaType;

		$this->flags = 0;
	}

	const MOVED = 0x01;

	/**
	 *
	 * @var string
	 */
	private $filePath;

	/**
	 *
	 * @var integer|NULL
	 */
	private $fileSize;

	/**
	 *
	 * @var integer
	 */
	private $errorCode;

	/**
	 *
	 * @var string
	 */
	private $clientFilename;

	/**
	 *
	 * @var MediaTypeInterface|string|NULL
	 */
	private $clientMediaType;

	/**
	 *
	 * @var integer
	 */
	private $flags;
}