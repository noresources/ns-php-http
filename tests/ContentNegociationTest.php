<?php
/**
 * Copyright © 2012 - 2020 by Rena Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package Http
 */
namespace NoreSources\Http;

use Laminas\Diactoros\ServerRequestFactory;
use NoreSources\Http\ContentNegociation\ContentNegociator;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;

final class ContentNegociationtTest extends \PHPUnit\Framework\TestCase
{

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

			$this->assertArrayHasKey(HeaderField::ACCEPT, $negociated);

			$this->assertEquals($selection,
				($negociated[HeaderField::ACCEPT])->serialize(),
				$label . ' content-type negociation');
		}
	}
}