<?php
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response\TextResponse;
use NoreSources\Container\Container;
use NoreSources\Http\StreamManager;
use NoreSources\Http\ContentNegociation\ContentNegociationException;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Server\SerializationManagerResponsePopulator;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\Test\DerivedFileTestTrait;
?>
w<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
class SerializationManagerResponsePopulatorTest extends \PHPUnit\Framework\TestCase
{
	use DerivedFileTestTrait;

	public function setUp(): void
	{
		$this->initializeDerivedFileTest(__DIR__ . '/..');
	}

	public function tearDown(): void
	{
		$this->cleanupDerivedFileTest();
	}

	public function testException()
	{
		$populator = new SerializationManagerResponsePopulator();
		$request = ServerRequestFactory::fromGlobals();
		$request = $request->withHeader(HeaderField::ACCEPT,
			'text/alien-format');
		$data = '???? Ack Ack Ack !!!';
		$response = new TextResponse('');
		$this->expectException(ContentNegociationException::class,
			'No supported media type');
		$response = $populator->populateResponse($response, $request,
			$data);
	}

	public function testOutput()
	{
		$tests = [
			'json' => [
				'accept' => 'application/json; unknown=no; style=pretty; undefined=yes',
				'acceptParameters' => [
					'style',
					'undefined'
				]
			],
			'text-art' => [
				'accept' => 'text/vnd.ascii-art; charset=us-ascii; max-row-length=80; not=supported',
				'acceptParameters' => [
					'charset',
					'max-row-length'
				]
			],
			'csv' => [
				'accept' => 'text/csv; separator="|";foo=bar',
				'acceptParameters' => [
					'separator',
					'foo'
				]
			]
		];

		$populator = new SerializationManagerResponsePopulator();
		$serializer = $populator->getSerializer();
		$sm = StreamManager::getInstance();
		$method = __METHOD__;

		foreach ($tests as $format => $test)
		{
			$mediaType = MediaTypeFactory::getInstance()->createFromString(
				$test['accept'], true);
			$acceptParameters = Container::keyValue($test,
				'acceptparameters', []);
			foreach ($acceptParameters as $name)
			{
				$parameters = $mediaType->getParameters();
				$this->assertTrue($parameters->has($name),
					\strval($mediaType) . ' has ' . $name . ' parameter');
			}

			if (!$serializer->isMediaTypeSerializable($mediaType))
			{
				continue;
			}

			$data = [
				'body' => [
					'type' => 'object',
					'format' => $format
				],
				'request' => [
					'type' => 'acceptable',
					'format' => 'HTTP'
				]
			];

			$suffix = $format;

			$body = $sm->createFileStream('php://temp', 'w');
			$stream = $sm->getStreamResource($body);

			$response = new TextResponse('');
			$response = $response->withBody($body);

			$request = ServerRequestFactory::fromGlobals();
			$request = $request->withHeader(HeaderField::ACCEPT,
				$mediaType->serializeToString());

			{
				$requestText = \Laminas\Diactoros\Request\Serializer::toString(
					$request);
				$extension = 'request';
				$this->assertDerivedFile($requestText . PHP_EOL, $method,
					$suffix, $extension);
			}

			$response = $populator->populateResponse($response, $request,
				$data);

			//

			{
				$extension = 'response';
				if ($response->getBody()->isSeekable())
					$response->getBody()->rewind();
				$responseText = \Laminas\Diactoros\Response\Serializer::toString(
					$response);
				$this->assertDerivedFile($responseText . PHP_EOL,
					$method, $suffix, $extension);
			}
		}
	}
}
