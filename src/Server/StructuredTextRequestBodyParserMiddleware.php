<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Server;

use NoreSources\Http\Header\ContentTypeHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Request\LiteralValueRequestBody;
use NoreSources\Text\StructuredText;
use NoreSources\Type\TypeConversionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class StructuredTextRequestBodyParserMiddleware implements
	MiddlewareInterface
{

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
		$format = StructuredText::mediaTypeFormat(
			\strval($contentType->getMediaType()));

		if ($format === false)
			return $handler->handle($request);

		try
		{
			$request->getBody()->rewind();
			$data = StructuredText::parseText(
				$request->getBody()->getContents(), $format);

			if (!(\is_object($data) || \is_array($data)))
				$data = new LiteralValueRequestBody($data);

			return $handler->handle($request->withParsedBody($data));
		}
		catch (TypeConversionException $e)
		{
			return $handler->handle($request);
		}
	}
}
