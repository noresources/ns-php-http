<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use NoreSources\Container\Container;
use NoreSources\Http\Header\AcceptEncodingHeaderValue;
use NoreSources\Http\Header\AlternativeValueListInterface;
use NoreSources\Http\Header\HeaderTokenValueParser;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\HeaderValueInterface;
use NoreSources\Http\Header\InvalidHeaderException;
use NoreSources\Http\Header\TextHeaderValue;
use NoreSources\Type\TypeDescription;

final class HeaderValueFactoryTest extends \PHPUnit\Framework\TestCase
{

	public function testHeaderTokenValueParser()
	{
		$tests = [
			'Basic' => [
				'constructor' => [
					TextHeaderValue::class
				],
				'input' => 'hello'
			],
			'Basic with whitespaces' => [
				'constructor' => [
					TextHeaderValue::class
				],
				'input' => ' hello  ',
				'token' => 'hello',
				'consumed' => 6
			],
			'Unexpected qvalue' => [
				'constructor' => [
					TextHeaderValue::class
				],
				'input' => 'hello; q=0.5',
				'token' => false
			],
			'Token with qvalue' => [
				'constructor' => [
					AcceptEncodingHeaderValue::class
				],
				'input' => 'identity; q=0.5',
				'token' => 'identity',
				'q' => 0.5
			]
		];
		$parserClass = new \ReflectionClass(
			HeaderTokenValueParser::class);
		foreach ($tests as $label => $test)
		{
			$ctor = Container::keyValue($test, 'constructor');
			$input = Container::keyValue($test, 'input');
			$token = Container::keyValue($test, 'token', $input);
			$consumed = Container::keyValue($test, 'consumed',
				\strlen($input));

			/**
			 *
			 * @var HeaderTokenValueParser $parser
			 */
			$parser = $parserClass->newInstanceArgs($ctor);
			$result = null;
			try
			{
				$result = $parser->parseText($input);
			}
			catch (\Exception $e)
			{
				$result = $e;
			}

			if ($token === false)
			{
				$this->assertInstanceOf(
					\InvalidArgumentException::class, $result,
					$label . ' parsing should failed');
				continue;
			}

			$this->assertEquals('array',
				TypeDescription::getName($result),
				$label . ' parser result type');

			$headerValue = $result[0];

			$this->assertEquals($consumed, $result[1],
				$label . ' consumed length');

			$expectedClass = Container::keyValue($ctor, 0,
				HeaderValueInterface::class);
			$this->assertInstanceOf($expectedClass, $headerValue,
				$label . ' header value class');
		}
	}

	public function testFactory()
	{
		$tests = [
			'generic' => [
				'line' => 'Foo: bar',
				'name' => 'foo',
				'value' => 'bar',
				'error' => 0
			],
			'invalid generic' => [
				'line' => 'Foo bar',
				'name' => null,
				'value' => null,
				'error' => InvalidHeaderException::INVALID_HEADER_LINE
			],
			'content-type' => [
				'line' => 'Content-Type:  text/html',
				'name' => 'content-type',
				'value' => 'text/html',
				'error' => 0
			],
			'content-type with invalid media type' => [
				'line' => 'Content-Type:  te=xt/html',
				'name' => null,
				'value' => null,
				'error' => InvalidHeaderException::INVALID_HEADER_VALUE
			],
			'accept-encoding' => [
				'line' => 'Accept-Encoding: identity',
				'name' => 'accept-encoding',
				'value' => 'identity',
				'error' => 0
			]
		];

		foreach ($tests as $label => $test)
		{
			$test = (object) $test;
			$error = 0;
			$name = null;
			$value = null;
			try
			{
				list ($name, $value) = HeaderValueFactory::fromHeaderLine(
					$test->line, true);
			}
			catch (InvalidHeaderException $e)
			{
				$error = $e->getHeaderErrorType();
			}

			if ($test->error !== 0)
			{
				$this->assertEquals($test->error, $error,
					$label . ' error code');
				continue;
			}

			$this->assertEquals($test->name, \strtolower($name),
				$label . ' name');
			$this->assertTrue(
				($value instanceof HeaderValueInterface ||
				$value instanceof AlternativeValueListInterface),
				$label . ' value is a ' . HeaderValueInterface::class .
				' or a ' . AlternativeValueListInterface::class);
			$this->assertEquals($test->value, \strval($value),
				$label . ' value');
		}
	}
}
