<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use NoreSources\Http\Header\AcceptAlternativeValueList;
use NoreSources\Http\Header\HeaderValueFactory;

final class AcceptTest extends \PHPUnit\Framework\TestCase
{

	public function testParse()
	{
		$strangeParameters = [
			'charset' => 'utf-8',
			'delimiter' => 'comma, or semicolon; delimiter'
		];
		$strangeExtensions = [
			'ext' => 'What ? "If" !'
		];

		$allParameters = \array_merge($strangeParameters, [
			'q' => 0.5
		], $strangeExtensions);

		$strangeParameterString = ParameterMapSerializer::serializeParameters(
			$allParameters);

		$tests = [
			'Basic' => [
				'string' => 'text/plain',
				'alternatives' => [
					[
						'mediaRange' => 'text/plain',
						'quality' => 1.0,
						'parameters' => [],
						'extensions' => []
					]
				],
				'valid' => true
			],
			'with charset' => [
				'string' => 'text/html; charset=utf-8',
				'alternatives' => [
					[
						'mediaRange' => 'text/html',
						'quality' => 1.0,
						'parameters' => [
							'charset' => 'utf-8'
						],
						'extensions' => []
					]
				],
				'valid' => true
			],
			'with param and quality' => [
				'string' => 'text/html; p=Pee;foo="Bar"; q=0.2',
				'alternatives' => [
					[
						'mediaRange' => 'text/html',
						'quality' => 0.2,
						'parameters' => [
							'p' => 'Pee',
							'foo' => 'Bar'
						],
						'extensions' => []
					]
				],
				'valid' => true
			],
			'Uncommon parameter values' => [
				'string' => 'foo/bar; ' . $strangeParameterString,
				'valid' => true,
				'alternatives' => [
					[
						'mediaRange' => 'foo/bar',
						'quality' => 0.5,
						'parameters' => $strangeParameters,
						'extensions' => $strangeExtensions
					]
				]
			],
			'Basic list' => [
				'string' => 'text/plain , text/html',
				'alternatives' => [
					[
						'mediaRange' => 'text/plain',
						'quality' => 1.0,
						'parameters' => [],
						'extensions' => []
					],
					[
						'mediaRange' => 'text/html',
						'quality' => 1.0,
						'parameters' => [],
						'extensions' => []
					]
				],
				'valid' => true
			],
			'A real use case' => [
				'string' => 'text/javascript, */*; q=0.01',
				'valid' => true,
				'alternatives' => [
					[
						'mediaRange' => 'text/javascript',
						'quality' => 1.0,
						'parameters' => [],
						'extensions' => []
					],
					[
						'mediaRange' => '*/*',
						'quality' => 0.01,
						'parameters' => [],
						'extensions' => []
					]
				]
			]
		];

		foreach ($tests as $label => $test)
		{
			$test = (object) $test;
			$valid = false;
			try
			{
				$accept = HeaderValueFactory::fromKeyValue('accept',
					$test->string);
				$valid = true;
			}
			catch (\Exception $e)
			{
				if ($test->valid)
					throw $e;
				continue;
			}

			$this->assertEquals($test->valid, $valid,
				$label . ' is ' . ($test->valid ? '' : 'not ') . 'valid');

			$this->assertInstanceOf(AcceptAlternativeValueList::class,
				$accept, $label . ', class type');
			$this->assertInstanceOf(\Countable::class, $accept);

			$this->assertCount(\count($test->alternatives), $accept,
				$label . ', alternative count');

			foreach ($test->alternatives as $index => $alternative)
			{
				$expected = (object) $alternative;
				$actual = $accept->getAlternative($index);
				$lbl = $label . ' alternative [' . $index . ']';

				$this->assertEquals($expected->mediaRange,
					strval($actual->getMediaRange()),
					$lbl . ' media range');

				$this->assertEquals($expected->quality,
					$actual->getQualityValue(), $lbl . ' quality value');

				$this->assertCount(\count($expected->parameters),
					$actual->getMediaRange()
						->getParameters(),
					$lbl . ' media range parameter count');

				foreach ($expected->parameters as $k => $v)
				{
					$this->assertEquals($v,
						$actual->getMediaRange()
							->getParameters()[$k],
						$lbl . ' parameter ' . $k . ' value');
				}

				$this->assertCount(\count($expected->extensions),
					$actual->getExtensions(), $lbl . ' extensions count');

				foreach ($expected->extensions as $k => $v)
				{
					$this->assertArrayHasKey($k,
						$actual->getExtensions(),
						$lbl . ' extension ' . $k . ' presence');

					if ($actual->getExtensions()->offsetExists($k))
						$this->assertEquals($v,
							$actual->getExtensions()
								->offsetGet($k),
							$lbl . ' extension ' . $k . ' value');
				}
			}
		}
	}
}
