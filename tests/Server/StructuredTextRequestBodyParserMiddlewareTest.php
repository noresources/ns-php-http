<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response\JsonResponse;
use NoreSources\Http\Server\StructuredTextRequestBodyParserMiddleware;
use NoreSources\Http\Test\ClosureRequestHandler;
use NoreSources\Http\Test\Utility;
use Psr\Http\Message\ServerRequestInterface;

final class StructuredTextParserTest extends \PHPUnit\Framework\TestCase
{

	public function testStructuredTextBodyParser()
	{
		$data = [
			'Hellow' => 'world'
		];
		$json = \json_encode($data);

		$body = Utility::createStreamFromText($json);

		$factory = new ServerRequestFactory();
		$request = $factory->createServerRequest('POST', '/foo/bar')
			->withHeader('Content-Type', 'application/json')
			->withBody($body);

		$this->assertInstanceOf(ServerRequestInterface::class, $request);

		$this->assertEquals($json, $request->getBody()
			->getContents());

		$mw = new StructuredTextRequestBodyParserMiddleware();

		$handler = new ClosureRequestHandler(
			function (ServerRequestInterface $request) use ($data) {
				$this->assertEquals($data, $request->getParsedBody());
				return new JsonResponse($request->getParsedBody());
			});
		$response = $mw->process($request, $handler);

		$body->close();
	}
}
