<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response\TextResponse;
use NoreSources\Http\Server\MultipartFormDataRequestBodyParserMiddleware;
use NoreSources\Http\Test\ClosureRequestHandler;
use NoreSources\Http\Test\Utility;
use Psr\Http\Message\ServerRequestInterface;

final class MultipartParserTest extends \PHPUnit\Framework\TestCase
{

	public function testMultipartBodyParser()
	{
		$data = \file_get_contents(
			__DIR__ . '/../data/multipart-1.crlf');
		$body = Utility::createStreamFromText($data);

		$factory = new ServerRequestFactory();
		$request = $factory->createServerRequest('POST', '/foo/bar')
			->withHeader('Content-Type',
			'multipart/form-data; boundary=------------------------5b03f006c07b7c87')
			->withBody($body);

		$this->assertInstanceOf(ServerRequestInterface::class, $request);

		$this->assertEquals($data, $request->getBody()
			->getContents());

		$mw = new MultipartFormDataRequestBodyParserMiddleware();

		$resultRequest = null;
		$response = $mw->process($request,
			new ClosureRequestHandler(
				function (ServerRequestInterface $request) use (
				&$resultRequest) {
					$resultRequest = $request;
					return new TextResponse('OK');
				}));

		$body->close();

		$this->assertInstanceOf(ServerRequestInterface::class,
			$resultRequest);

		$fields = $resultRequest->getParsedBody();
		$this->assertEquals([
			'key' => 'value'
		], $fields, 'Request fields');

		$files = $resultRequest->getUploadedFiles();
		if (false)
		{
			$this->assertCount(1, $files, 'Uploaded file count');
			$this->assertArrayHasKey('file', $files);
			$this->assertArrayHasKey('tmp_name', $files['file']);

			$expectedContent = \file_get_contents(
				__DIR__ . '/../data/multipart-1.file.crlf');
			$content = \file_get_contents($files['file']['tmp_name']);

			$this->assertEquals($expectedContent, $content);
		}
	}

	public function testMultipartBodyParserArrayField()
	{
		$data = \file_get_contents(
			__DIR__ . '/../data/multipart-arrayfield.crlf');
		$body = Utility::createStreamFromText($data);

		$factory = new ServerRequestFactory();
		$request = $factory->createServerRequest('POST', '/foo/bar')
			->withHeader('Content-Type',
			'multipart/form-data; boundary=------------------------5b03f006c07b7c87')
			->withBody($body);

		$this->assertInstanceOf(ServerRequestInterface::class, $request);

		$this->assertEquals($data, $request->getBody()
			->getContents());

		$mw = new MultipartFormDataRequestBodyParserMiddleware();
		$mw->setOption(
			MultipartFormDataRequestBodyParserMiddleware::DUPLICATED_FIELD_NAME,
			MultipartFormDataRequestBodyParserMiddleware::DUPLICATED_FIELD_NAME_ARRAY);

		$this->assertEquals(
			MultipartFormDataRequestBodyParserMiddleware::DUPLICATED_FIELD_NAME_ARRAY,
			$mw->getOption(
				MultipartFormDataRequestBodyParserMiddleware::DUPLICATED_FIELD_NAME));

		$resultRequest = null;
		$response = $mw->process($request,
			new ClosureRequestHandler(
				function (ServerRequestInterface $request) use (
				&$resultRequest) {
					$resultRequest = $request;
					return new TextResponse('OK');
				}));

		$body->close();

		$this->assertInstanceOf(ServerRequestInterface::class,
			$resultRequest);

		$fields = $resultRequest->getParsedBody();

		$this->arrayHasKey('array', $fields, 'Array field');
		$this->assertCount(2, $fields['array'],
			'Array field value count');
		$this->assertEquals([
			0 => 'Zero',
			3 => 'Three'
		], $fields['array']);

		$this->assertEquals([
			"value",
			'othervalue'
		], $fields['key']);
	}
}
