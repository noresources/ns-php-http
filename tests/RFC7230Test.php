<?php
/**
 * Copyright © 2012 - 2020 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use NoreSources\Container\Container;

final class RFC7230Test extends \PHPUnit\Framework\TestCase
{

	public function testRegex()
	{
		$tests = [
			'param-value token' => [
				'pattern' => RFC7230::PARAMETER_VALUE_PATTERN,
				'subject' => 'a-token',
				'match' => true,
				'groups' => [
					'a-token',
					'a-token'
				]
			],
			'param-value quoted' => [
				'pattern' => RFC7230::PARAMETER_VALUE_PATTERN,
				'subject' => '"some \"quoted\" text"',
				'match' => true,
				'groups' => [
					0 => '"some \"quoted\" text"',
					1 => '',
					2 => 'some \"quoted\" text'
				]
			],
			'param quoted' => [
				'pattern' => RFC7230::PARAMETER_PATTERN,
				'subject' => 'parameter="anoter \\quoted 	text"',
				'match' => true,
				'groups' => [
					0 => 'parameter="anoter \\quoted 	text"',
					1 => 'parameter',
					2 => '',
					3 => 'anoter \\quoted 	text'
				]
			]
		];

		foreach ($tests as $label => $test)
		{
			$test = (object) $test;

			$match = @preg_match(chr(1) . $test->pattern . chr(1),
				$test->subject, $groups);
			$error = error_get_last();
			if ($match === false)
				throw new \Exception($error['message']);

			$match = ($match ? true : false);
			$this->assertEquals($test->match, $match, $label . ' match');
			if ($test->match)
			{
				$this->assertEquals($test->groups, $groups,
					$label . ' number of capturing groups');
			}
		}
	}

	public function testParseParameterMap()
	{
		$tests = [
			'quality' => [
				'text' => 'q=0.8',
				'parameters' => [
					'q' => '0.8'
				],
				'toString' => 'q=0.8'
			],
			'quality etc.' => [
				'text' => '  q=0.8 ;level=min; text="some text"',
				'parameters' => [
					'q' => '0.8',
					'level' => 'min',
					'text' => 'some text'
				],
				'toString' => 'q=0.8; level=min; text="some text"'
			],
			'Skip level' => [
				'text' => '  q=0.8 ;level=min; text="some text"',
				'accept' => function ($name, $value) {
					return (($name == 'level') ? 0 : 1);
				},
				'parameters' => [
					'q' => '0.8',
					'text' => 'some text'
				],
				'toString' => 'q=0.8; text="some text"'
			],
			'accept only quality' => [
				'text' => '  q=0.8 ;level=min; text="some text"',
				'accept' => function ($name, $value) {
					return (($name == 'q') ? 1 : -1);
				},
				'consumed' => 9,
				'parameters' => [
					'q' => '0.8'
				],
				'toString' => 'q=0.8'
			]
		];

		foreach ($tests as $label => $test)
		{
			$test['consumed'] = Container::keyValue($test, 'consumed',
				\strlen($test['text']));
			$test['accept'] = Container::keyValue($test, 'accept', null);
			$test = (object) $test;

			$parameters = new ParameterMap();
			$consumed = ParameterMapSerializer::unserializeParameters(
				$parameters, $test->text,
				($test->accept ? $test->accept : []));

			$this->assertEquals($test->consumed, $consumed,
				$label . ' consumed bytes');

			$this->assertCount(\count($test->parameters), $parameters,
				$label . ' parameter count');

			$this->assertEquals($test->toString,
				ParameterMapSerializer::serializeParameters($parameters),
				$label . ' convert back to string');
		}
	}
}
