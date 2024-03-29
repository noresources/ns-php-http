<?php
/**
 * Copyright © 2012 - 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use NoreSources\Container\Container;
use NoreSources\Http\Authentication\AuthenticationScheme;
use NoreSources\Http\Authentication\BasicCredentialData;
use NoreSources\Http\Authentication\TokenCredentialData;
use NoreSources\Http\Header\AuthorizationHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\InvalidHeaderException;

final class AuthenticationTest extends \PHPUnit\Framework\TestCase
{

	public function testParse()
	{
		$b64UserPass = \base64_encode('user:password');
		$userPassData = new BasicCredentialData($b64UserPass);
		$tests = [
			'Lonely scheme' => [
				'text' => 'LonelyScheme',
				'scheme' => 'LonelyScheme',
				'valid' => true
			],
			'Bearer (JWT)' => [
				'text' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9kYXRhLmRlcGlueGkuYmUiLCJhdWQiOiJodHRwOlwvXC9kYXRhLmRlcGlueGkuYmUiLCJpYXQiOjE2NDgxMjk1OTgsIm5iZiI6MTY0ODEyOTU5OCwiZXhwIjoxNjQ4MjE1OTk4LCJ1c2VyIjoicm9vdCJ9.sYtmW6UsxjKlHpzyx2nWhXP9dMyLJPGLWrFC_z26YyY',
				'scheme' => AuthenticationScheme::BEARER,
				'valid' => true,
				'data' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9kYXRhLmRlcGlueGkuYmUiLCJhdWQiOiJodHRwOlwvXC9kYXRhLmRlcGlueGkuYmUiLCJpYXQiOjE2NDgxMjk1OTgsIm5iZiI6MTY0ODEyOTU5OCwiZXhwIjoxNjQ4MjE1OTk4LCJ1c2VyIjoicm9vdCJ9.sYtmW6UsxjKlHpzyx2nWhXP9dMyLJPGLWrFC_z26YyY',
				'dataClass' => TokenCredentialData::class
			],
			'Bearer (with whitespaces)' => [
				'text' => 'bearer   SGVsbG8gd29ybGQK== ',
				'scheme' => 'bearer',
				'valid' => true,
				'data' => 'SGVsbG8gd29ybGQK==',
				'dataClass' => TokenCredentialData::class
			],
			'Basic' => [
				'text' => 'Basic ' . $b64UserPass,
				'scheme' => 'Basic',
				'data' => [
					'user' => 'user',
					'password' => 'password'
				],
				'valid' => true,
				'dataClass' => BasicCredentialData::class
			]
		];

		foreach ($tests as $label => $test)
		{
			$test = (object) $test;

			/**
			 *
			 * @var AuthorizationHeaderValue $value
			 */
			$value = null;
			$valid = false;
			$error = '';
			try
			{
				$value = HeaderValueFactory::createFromKeyValue(
					HeaderField::AUTHORIZATION, $test->text);
				$valid = true;
			}
			catch (InvalidHeaderException $e)
			{
				$error = $e->getMessage();
			}

			$this->assertEquals($test->valid, $valid,
				$label . ' validity ' . $error);
			if (!$valid)
				continue;

			$data = $value->getCredentialData();
			$this->assertInstanceOf(AuthorizationHeaderValue::class,
				$value);

			$this->assertEquals($test->scheme, $value->getScheme(),
				'Scheme');

			if (\property_exists($test, 'dataClass'))
				$this->assertInstanceOf($test->dataClass, $data,
					$label . 'data class');

			if (\property_exists($test, 'data'))
			{
				if ($data instanceof BasicCredentialData &&
					\is_array($test->data))
				{
					$this->assertEquals(
						Container::keyValue($test->data, 'user'),
						$data->getUser(), $label . ' user');
					$this->assertEquals(
						Container::keyValue($test->data, 'password'),
						$data->getPassword(), $label . ' password');
				}

				if (\is_string($test->data))
					$this->assertEquals($test->data, \strval($data),
						'String credential data');
				elseif (\is_array($test->data) &&
					$data instanceof ParameterMapProviderInterface)
				{
					$this->assertInstanceOf(
						ParameterMapProviderInterface::class, $data);
					$parameters = $data->getParameters();
					$this->assertEquals($test->data,
						$parameters->getArrayCopy(),
						'Credential data (params)');
				}
			}
		}
	}
}
