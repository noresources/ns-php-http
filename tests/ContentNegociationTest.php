<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Request\Serializer;
use NoreSources\Container\Container;
use NoreSources\Http\Coding\ContentCoding;
use NoreSources\Http\ContentNegociation\ContentNegociationException;
use NoreSources\Http\ContentNegociation\ContentNegociator;
use NoreSources\Http\Header\AcceptCharsetAlternativeValueList;
use NoreSources\Http\Header\AcceptCharsetHeaderValue;
use NoreSources\Http\Header\AcceptEncodingAlternativeValueList;
use NoreSources\Http\Header\AcceptEncodingHeaderValue;
use NoreSources\Http\Header\AcceptLanguageAlternativeValueList;
use NoreSources\Http\Header\AcceptLanguageHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\InvalidHeaderException;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;

final class ContentNegociationtTest extends \PHPUnit\Framework\TestCase
{

	public function testLanguage()
	{
		$tests = [
			'fallback-ignore' => [
				'line' => 'Accept-Language: en, fr, de',
				'accepted' => [
					'en' => 1,
					'fr' => 1,
					'de' => 1
				],
				'available' => [
					'es'
				],
				'expected' => 'es'
			],
			'match' => [
				'line' => 'Accept-Language: en, fr, de',
				'accepted' => [
					'en' => 1,
					'fr' => 1,
					'de' => 1
				],
				'available' => [
					'fr'
				],
				'expected' => 'fr'
			],
			'qvalue ordering' => [
				'line' => 'Accept-Language: en; q=0.5, fr; q=0.9, de; q=0.6',
				'accepted' => [
					'en' => 0.5,
					'fr' => 0.9,
					'de' => 0.6
				],
				'available' => [
					'en',
					'fr'
				],
				'expected' => 'fr'
			]
		];

		$negociator = ContentNegociator::getInstance();

		foreach ($tests as $label => $test)
		{
			$headerLine = Container::keyValue($test, 'line');
			$error = Container::keyValue($test, 'error', false);
			$accepted = Container::keyValue($test, 'accepted');
			$available = Container::keyValue($test, 'available', []);
			$expected = Container::keyValue($test, 'expected');

			$parsed = null;

			try
			{
				$parsed = HeaderValueFactory::fromHeaderLine(
					$headerLine, false);
				$label .= PHP_EOL . '[' .
					TypeConversion::toString($parsed);
				$label .= '] vs [' .
					Container::implodeValues($available, ', ') . ']';
			}
			catch (InvalidHeaderException $e)
			{
				$parsed = $e;
			}

			if ($error == 'parse')
			{
				$this->assertInstanceOf(InvalidHeaderException::class,
					$parsed, $label . ' should fail to parse');
				continue;
			}

			$this->assertInstanceOf(
				AcceptLanguageAlternativeValueList::class, $parsed,
				$label . ' result');

			$this->assertCount(Container::count($accepted), $parsed,
				$label . '. Number of alternatives.');

			$i = 0;
			foreach ($parsed as $alternative)
			{
				$this->assertInstanceOf(
					AcceptLanguageHeaderValue::class, $alternative,
					$label . ' alternative class');

				/** @var AcceptLanguageHeaderValue $alternative */

				$range = $alternative->getLanguageRange();
				$strrange = \strval($range);

				$this->assertArrayHasKey($strrange, $accepted,
					$label . ' alternative ' . $i . ' range');

				$this->assertEquals($accepted[$strrange],
					$alternative->getQualityValue(),
					$label . ' alternative ' . $i . ' quality value');

				$i++;
			}

			$negociated = null;
			try
			{
				$negociated = $negociator->negociateLanguage($accepted,
					$available);
			}
			catch (ContentNegociationException $e)
			{
				$negociated = $e;
			}

			if ($error == 'negociate')
			{
				$this->assertInstanceOf(
					ContentNegociationException::class, $negociated,
					$label . ' should failed to negociate');
				continue;
			}

			$this->assertEquals($expected, $negociated,
				$label . ' negociation result');
		}
	}

	public function testCharset()
	{
		$tests = [
			'bTF-8 only' => [
				'line' => 'Accept-Charset: utf-8',
				'accepted' => [
					'utf-8' => 1
				],
				'available' => [
					'text/html'
				],
				'expected' => 'text/html; charset=utf-8'
			],
			'Prefer MediaType with explicit charset' => [
				'line' => 'Accept-Charset: utf-8',
				'accepted' => [
					'utf-8' => 1
				],
				'available' => [

					'text/html',
					'text/html; charset=ascii',
					'text/xhtml; charset=utf-8'
				],
				'expected' => 'text/xhtml; charset=utf-8'
			],
			'Non-explicit charset qvalue' => [
				'line' => 'Accept-Charset: utf-8; q=0.3, *; q=0.5',
				'accepted' => [
					'utf-8' => 0.3,
					'*' => 0.5
				],
				'available' => [

					'text/html; charset=ascii',
					'text/xhtml; charset=utf-8'
				],
				'expected' => 'text/html; charset=ascii'
			]
		];

		$negociator = ContentNegociator::getInstance();

		foreach ($tests as $label => $test)
		{
			$line = $test['line'];
			$accepted = Container::keyValue($test, 'accepted', []);
			$expected = Container::keyValue($test, 'expected', null);
			$error = Container::keyValue($test, 'error', null);

			$headerValue = null;
			try
			{
				$headerValue = HeaderValueFactory::fromHeaderLine($line);
			}
			catch (\Exception $e)
			{
				$headerValue = $e;
			}

			if ($error == InvalidHeaderException::class)
			{
				$this->assertInfinite($error, $headerValue,
					$label . ' should failed to parse');
				continue;
			}

			$this->assertInstanceOf(
				AcceptCharsetAlternativeValueList::class, $headerValue,
				$label . ' header value class' .
				(($headerValue instanceof \Exception) ? ' (' .
				$headerValue->getMessage() . ')' : ''));

			/** @var AcceptCharsetAlternativeValueList $headerValue */
			$this->assertCount(\count($accepted), $headerValue,
				$label . ' alterative value');

			$index = 0;
			foreach ($accepted as $charset => $q)
			{
				/** @var AcceptCharsetHeaderValue $actual */
				$actual = $headerValue->getAlternative($index);

				$this->assertEquals($charset, $actual->getCharset(),
					$label . ' charset #' . ($index + 1));
				$this->assertEquals($q, $actual->getQualityValue(),
					$label . ' quality value #' . ($index + 1));

				$index++;
			}

			$a = Container::keyValue($test, 'available', []);
			$available = [];
			foreach ($a as $v)
			{
				$k = $v;

				try
				{
					$mediaType = new MediaType('');
					$mediaType->unserialize($v);

					$this->assertEquals($v, $mediaType->serialize(),
						$v . ' conversion to media type');

					$v = $mediaType;
					$k = $v->serialize();
				}
				catch (\Exception $e)
				{}

				$available[$k] = $v;
			}

			$negociated = $negociator->negociateCharset($accepted,
				$available);

			$serialized = $negociated;
			if ($serialized instanceof MediaTypeInterface)
				$serialized = $serialized->serialize();

			if (Container::keyExists($test, 'expected'))
			{
				$expected = Container::keyValue($test, 'expected');
				$this->assertEquals($expected, $serialized,
					$label . ' negociation result');
			}

			foreach ($available as $original => $current)
			{
				$actual = ($current instanceof \Serializable) ? $current->serialize() : $current;
				$this->assertEquals($original, $actual,
					$label .
					' available elements should not have been modified');
			}
		}
	}

	public function testEncoding()
	{
		$tests = [
			'No encoding' => [
				'line' => 'Accept-Encoding: ',
				'accepted' => [
					ContentCoding::IDENTITY => 1
				],
				'expected' => ContentCoding::IDENTITY
			],
			'Accept gzip or identity' => [
				'line' => 'Accept-EncoDiNg: gzip, identity; q=0.5',
				'accepted' => [
					ContentCoding::IDENTITY => 0.5,
					ContentCoding::GZIP => 1
				],
				'expected' => ContentCoding::IDENTITY
			],
			'Reject identity' => [
				'line' => 'Accept-EncoDiNg: gzip, identity; q=0',
				'accepted' => [
					ContentCoding::IDENTITY => 0,
					ContentCoding::GZIP => 1
				],
				'available' => [
					ContentCoding::COMPRESS,
					ContentCoding::DEFLATE
				],
				'error' => 'negociate'
			],
			'gzip or anything else with a low value' => [
				'line' => 'Accept-EncoDiNg:*; q=0.1, gzip',
				'accepted' => [
					ContentCoding::GZIP => 1,
					AcceptEncodingHeaderValue::ANY => 0.1
				],
				'expected' => ContentCoding::IDENTITY
			],
			'gzip or anything else but with a low value (bis)' => [
				'line' => 'Accept-EncoDiNg:*; q=0.1, gzip',
				'accepted' => [
					ContentCoding::GZIP => 1,
					AcceptEncodingHeaderValue::ANY => 0.1
				],
				'available' => [
					ContentCoding::GZIP
				],
				'expected' => ContentCoding::GZIP
			]
		];

		$negociator = ContentNegociator::getInstance();

		foreach ($tests as $label => $test)
		{
			$headerLine = Container::keyValue($test, 'line');
			$coding = Container::keyValue($test, 'coding');
			$error = Container::keyValue($test, 'error', false);
			$accepted = Container::keyValue($test, 'accepted');
			$available = Container::keyValue($test, 'available',
				[
					ContentCoding::IDENTITY
				]);
			$expected = Container::keyValue($test, 'expected');

			$parsed = null;

			try
			{
				$parsed = HeaderValueFactory::fromHeaderLine(
					$headerLine, false);
				$label .= PHP_EOL . '[' .
					TypeConversion::toString($parsed);
				$label .= '] vs [' .
					Container::implodeValues($available, ', ') . ']';
			}
			catch (InvalidHeaderException $e)
			{
				$parsed = $e;
			}

			if ($error == 'parse')
			{
				$this->assertInstanceOf(InvalidHeaderException::class,
					$parsed, $label . ' should fail to parse');
				continue;
			}

			$this->assertInstanceOf(
				AcceptEncodingAlternativeValueList::class, $parsed,
				$label . ' result');

			$this->assertCount(Container::count($accepted), $parsed,
				$label . '. Number of alternatives.');

			$i = 0;
			foreach ($parsed as $alternative)
			{
				$this->assertInstanceOf(
					AcceptEncodingHeaderValue::class, $alternative,
					$label . ' alternative class');

				/** @var AcceptEncodingHeaderValue $alternative */

				$coding = $alternative->getCoding();

				$this->assertArrayHasKey($coding, $accepted,
					$label . ' alternative ' . $i . ' coding');

				$this->assertEquals($accepted[$coding],
					$alternative->getQualityValue(),
					$label . ' alternative ' . $i . ' quality value');

				$i++;
			}

			$negociated = null;
			try
			{
				$negociated = $negociator->negociateEncoding($accepted,
					$available);
			}
			catch (ContentNegociationException $e)
			{
				$negociated = $e;
			}

			if ($error == 'negociate')
			{
				$this->assertInstanceOf(
					ContentNegociationException::class, $negociated,
					$label . ' should failed to negociate');
				continue;
			}

			$this->assertEquals($expected, $negociated,
				$label . ' negociation result');
		}
	}

	public function testContentType()
	{
		/**
		 *
		 * @var ContentNegociator $negociator
		 */
		$negociator = ContentNegociator::getInstance();

		$tests = [
			'rfc7231-example' => [
				'accept' => 'text/*;q=0.3, text/html;q=0.7, ' .
				' text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5',
				'mediaTypes' => [
					'text/html;level=1' => 1,
					'text/html' => 0.7,
					'text/plain' => 0.3,
					'image/jpeg' => 0.5,
					'text/html;level=2' => 0.4,
					'text/html;level=3' => 0.7,
					'foo/bar' => 0.5
				]
			],
			'strict' => [
				'accept' => 'application/json',
				'mediaTypes' => [
					'application/json' => 1,
					'application/json; charset="utf-8"' => 1,
					'foo/bar' => -1
				]
			],
			'strict 2' => [
				'accept' => 'application/json; charset="utf-8", application/json; q=0.8',
				'mediaTypes' => [
					'application/json;  charset=utf-8' => 1,
					'application/json' => 0.8,
					'foo/bar' => -1
				]
			]
		];

		foreach ($tests as $label => $test)
		{
			$test = (object) $test;
			$accept = HeaderValueFactory::fromKeyValue(
				HeaderField::ACCEPT, $test->accept);
			$selection = null;
			$selectionQuality = -1;
			foreach ($test->mediaTypes as $mediaTypeString => $expectedQualityValue)
			{
				$contentType = HeaderValueFactory::fromKeyValue(
					HeaderField::CONTENT_TYPE, $mediaTypeString);
				$qualityValue = $negociator->getContentTypeQualityValue(
					$contentType->getMediaType(), $accept);

				$this->assertEquals($expectedQualityValue, $qualityValue,
					$label . ' vs ' . $mediaTypeString);

				if ($qualityValue > $selectionQuality)
				{
					$selectionQuality = $qualityValue;
					$selection = $contentType;
				}
			}

			$request = ServerRequestFactory::fromGlobals();
			$request = $request->withHeader(HeaderField::ACCEPT,
				$test->accept)->withHeader(HeaderField::ACCEPT_LANGUAGE,
				'fr-FR,en-US,en');

			$negociated = $negociator->negociate($request,
				[
					HeaderField::ACCEPT => \array_keys(
						$test->mediaTypes)
				]);

			$this->assertArrayHasKey(HeaderField::CONTENT_TYPE,
				$negociated);

			$this->assertEquals($selection,
				($negociated[HeaderField::CONTENT_TYPE])->serialize(),
				$label . ' content-type negociation');
		}
	}

	public function testNegociate()
	{
		$baseRequest = ServerRequestFactory::fromGlobals();
		$serializer = new Serializer();
		$negociator = ContentNegociator::getInstance();

		$tests = [
			'Nothing requested' => [
				'headers' => [
					'User-Agent' => 'NoreBrowser'
				],
				'availables' => [],
				'negociated' => []
			],
			'Nothing from client' => [
				'headers' => [
					'User-Agent' => 'NoreBrowser'
				],
				'availables' => [
					HeaderField::CONTENT_TYPE => [
						'text/html',
						'text/xhtml'
					]
				],
				'expected' => [
					HeaderField::CONTENT_TYPE => 'text/html'
				]
			],
			'Nothing from server' => [
				'headers' => [
					'User-Agent' => 'NoreBrowser',
					'accept-Language' => 'de',
					'accEPT' => 'application/json'
				],
				'expected' => []
			],
			'Basic' => [
				'headers' => [
					'Accept' => 'text/plain; q=0.5, text/html, text/xhtml',
					'accept-encoding' => 'gzip, *; q=0.5'
				],
				'availables' => [
					HeaderField::CONTENT_TYPE => [
						'application/json',
						'text/plain',
						'text/xhtml'
					],
					HeaderField::CONTENT_ENCODING => [
						ContentCoding::DEFLATE,
						ContentCoding::IDENTITY
					],
					HeaderField::CONTENT_LANGUAGE => [
						'en',
						'fr',
						'de'
					]
				],
				'expected' => [
					HeaderField::CONTENT_TYPE => 'text/xhtml',
					HeaderField::CONTENT_ENCODING => 'deflate',
					HeaderField::CONTENT_LANGUAGE => 'en'
				]
			]
		];

		foreach ($tests as $label => $test)
		{
			$request = clone $baseRequest;
			$headers = Container::keyValue($test, 'headers', []);
			$availables = Container::keyValue($test, 'availables', []);
			$expected = Container::keyValue($test, 'expected', []);
			$error = Container::keyValue($test, 'error');

			foreach ($headers as $header => $value)
			{
				$request = $request->withHeader($header, $value);
			}

			$label = $serializer->toString($request) . PHP_EOL . PHP_EOL .
				$label . PHP_EOL;

			$negociated = null;

			$negociated = $negociator->negociate($request, $availables);
			try
			{}
			catch (\Exception $e)
			{
				$negociated = $e;
			}

			if ($negociated instanceof \Exception)
			{
				$this->assertEquals('string',
					TypeDescription::getName($error),
					$label . 'Error (' . $e->getMessage() .
					') is expected');

				$this->assertInstanceOf($error, $negociated,
					$label . 'Negociation should fail');
				continue;
			}

			$this->assertEquals('array',
				TypeDescription::getName($negociated),
				$label . 'Negociation result type');

			$this->assertCount(\count($expected), $negociated,
				$label . 'Negociated element count');

			/*
			 * Convert negociation results for comparison
			 */
			$serialized = [];
			foreach ($negociated as $header => $result)
			{
				;
				if (\is_array($result))
				{
					foreach ($result as $k => $v)
					{
						if ($v instanceof \Serializable)
							$serialized[$header][$k] = $v->serialize();
						else
							$serialized[$header][$k] = TypeConversion::toString(
								$v);
					}
				}
				else
					if ($result instanceof \Serializable)
						$serialized[$header] = $result->serialize();
					else
						$serialized[$header] = TypeConversion::toString(
							$result);
			}

			$this->assertEquals($expected, $serialized,
				$label . 'Negociation result');
		}
	}
}