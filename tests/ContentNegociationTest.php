<?php
/**
 * Copyright © 2012 - 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Request\Serializer;
use Laminas\Diactoros\Response\TextResponse;
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
use NoreSources\Http\Header\ContentTypeHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\InvalidHeaderException;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;

final class ContentNegociationTest extends \PHPUnit\Framework\TestCase
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
					'text/html; charset=us-ascii',
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

					'text/html; charset=us-ascii',
					'text/xhtml; charset=utf-8'
				],
				'expected' => 'text/html; charset=us-ascii'
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
					$mediaType = MediaType::createFromString($v, true);
					$this->assertEquals($v, $mediaType->jsonSerialize(),
						$v . ' conversion to media type');

					$v = $mediaType;
					$k = $v->jsonSerialize();
				}
				catch (\Exception $e)
				{}

				$available[$k] = $v;
			}

			$negociated = $negociator->negociateCharset($accepted,
				$available);

			$serialized = $negociated;
			if ($serialized instanceof MediaTypeInterface)
				$serialized = $serialized->jsonSerialize();

			if (Container::keyExists($test, 'expected'))
			{
				$expected = Container::keyValue($test, 'expected');
				$this->assertEquals($expected, $serialized,
					$label . ' negociation result');
			}

			foreach ($available as $original => $current)
			{
				$actual = $current;
				if ($current instanceof MediaTypeInterface)
					$actual = $current->jsonSerialize();
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
				'availableMediaTypes' => [
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
				'availableMediaTypes' => [
					'application/json; charset="utf-8"' => 1,
					'application/json' => 1,
					'foo/bar' => -1
				]
			],
			'Non-maching parameters' => [
				'accept' => 'application/json; style=pretty' .
				', */*;q=0.1',
				'availableMediaTypes' => [
					'application/json; charset="utf-8"' => 1,
					'application/json' => 1,
					'foo/bar' => 0.1
				]
			]
		];

		foreach ($tests as $label => $test)
		{
			$test = (object) $test;
			$accept = $test->accept;
			if (\is_string($accept))
				$accept = \explode(',', $accept);
			$acceptValue = HeaderValueFactory::createFromKeyValue(
				HeaderField::ACCEPT, \implode(', ', $accept));
			$selection = null;
			$selectionQuality = -1;
			foreach ($test->availableMediaTypes as $mediaTypeString => $expectedQualityValue)
			{
				/**
				 *
				 * @var ContentTypeHeaderValue $contentType
				 */
				$contentType = HeaderValueFactory::createFromKeyValue(
					HeaderField::CONTENT_TYPE, $mediaTypeString);
				$qualityValue = $negociator->getContentTypeQualityValue(
					$contentType->getMediaType(), $acceptValue);

				$this->assertEquals($expectedQualityValue, $qualityValue,
					$label . ' vs ' . $mediaTypeString);

				if ($qualityValue > $selectionQuality)
				{
					$selectionQuality = $qualityValue;
					$selection = $contentType->getMediaType();
				}
			}

			$request = ServerRequestFactory::fromGlobals();
			$request = $request->withHeader(HeaderField::ACCEPT,
				$test->accept)->withHeader(HeaderField::ACCEPT_LANGUAGE,
				'fr-FR,en-US,en');

			$negociated = $negociator->negociate($request,
				[
					HeaderField::CONTENT_TYPE => \array_keys(
						$test->availableMediaTypes)
				]);

			$this->assertArrayHasKey(HeaderField::CONTENT_TYPE,
				$negociated);

			$actual = $negociated[HeaderField::CONTENT_TYPE];

			$this->assertInstanceOf(MediaTypeInterface::class, $actual,
				$label);

			$this->assertEquals($selection->serializeToString(),
				$actual->serializeToString(),
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
				'negociated' => [],
				'expected-vary' => []
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
				],
				'expected-vary' => [
					HeaderField::ACCEPT
				]
			],
			'Nothing from server' => [
				'headers' => [
					'User-Agent' => 'NoreBrowser',
					'accept-Language' => 'de',
					'accEPT' => 'application/json'
				],
				'expected' => [],
				'expected-vary' => []
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
					HeaderField::ACCEPT_ENCODING => [
						// Same as
						// HeaderField::CONTENT_ENCODING => [
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
				],
				'expected-vary' => [
					HeaderField::ACCEPT,
					HeaderField::ACCEPT_ENCODING,
					HeaderField::ACCEPT_LANGUAGE
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
						$serialized[$header][$k] = HeaderValueFactory::stringifyValue(
							$v, $header);
					}
				}
				else
					$serialized[$header] = HeaderValueFactory::stringifyValue(
						$result, $header);
			}

			$this->assertEquals($expected, $serialized,
				$label . 'Negociation result');

			if (($vary = Container::keyValue($test, 'expected-vary')))
			{
				$vary = Container::implodeValues($vary, ' ');
				$response = new TextResponse('Response');
				$response = $negociator->populateResponse($response,
					$negociated, $availables);
				$headers = $response->getHeader(HeaderField::VARY);
				$this->assertCount(\strlen($vary) ? 1 : 0, $headers,
					'Vary header presence');
				if (Container::count($headers))
				{
					$this->assertEquals($vary,
						Container::firstValue($headers),
						'Vary header value');
				}
			}
		}
	}
}