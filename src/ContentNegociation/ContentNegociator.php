<?php
/**
 * Copyright © 2012 - 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\ContentNegociation;

use NoreSources\Bitset;
use NoreSources\NotComparableException;
use NoreSources\SingletonTrait;
use NoreSources\Container\Container;
use NoreSources\Http\QualityValueInterface;
use NoreSources\Http\Coding\ContentCoding;
use NoreSources\Http\Header\AcceptCharsetHeaderValue;
use NoreSources\Http\Header\AcceptEncodingHeaderValue;
use NoreSources\Http\Header\AcceptHeaderValue;
use NoreSources\Http\Header\AcceptLanguageHeaderValue;
use NoreSources\Http\Header\AlternativeValueListInterface;
use NoreSources\Http\Header\ContentTypeHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Language\LanguageRange;
use NoreSources\Language\LanguageRangeFilter;
use NoreSources\MediaType\MediaRange;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Traversable;

class ContentNegociator
{
	use SingletonTrait;

	/**
	 * String mode negociation flags.
	 *
	 * Always honor the Accept[-*] header and raise ContentNegociationException
	 * even if the receommanded behavior is to ignore the header on negociation failure.
	 *
	 * Applies to
	 * <ul>
	 * <li>Language negociation</li>
	 * <li>Charset negotiation</li>
	 * </ul>
	 */
	const STRICT = Bitset::BIT_01;

	/**
	 * Negociation flag.
	 *
	 * Negociation methods will return an array containing all
	 * available entries that fullfil the Accept[-*] header conditions
	 * instead of returning the best match only.
	 */
	const ALL_MATCH = Bitset::BIT_02;

	/**
	 *
	 * @param RequestInterface $request
	 * @param
	 *        	array<string, mixed> $availables
	 * @return array<string, mixed>
	 */
	public function negociate(RequestInterface $request,
		$availables = array(), $flags = 0)
	{
		$negociated = [];

		$map = [
			HeaderField::ACCEPT => [
				'field' => HeaderField::CONTENT_TYPE,
				'negociator' => [
					$this,
					'negociateContentType'
				],
				'flags' => $flags | self::ALL_MATCH,
				'normalizer' => [
					$this,
					'normalizeMediaType'
				]
			],
			HeaderField::ACCEPT_ENCODING => [
				'field' => HeaderFIeld::CONTENT_ENCODING,
				'negociator' => [
					$this,
					'negociateEncoding'
				]
			],
			HeaderField::ACCEPT_LANGUAGE => [
				'field' => HeaderFIeld::CONTENT_LANGUAGE,
				'negociator' => [
					$this,
					'negociateLanguage'
				]
			]
		];

		foreach ($map as $requestHeaderField => $properties)
		{
			$responseHeaderField = $properties['field'];
			$negociator = $properties['negociator'];
			$negociationFlags = Container::keyValue($properties, 'flags',
				$flags);
			$normalizer = Container::keyValue($properties, 'normalizer',
				[
					TypeConversion::class,
					'toString'
				]);

			$available = Container::keyValue($availables,
				$responseHeaderField,
				Container::keyValue($availables, $requestHeaderField,
					null));

			if ($available === null)
				continue;

			if (!\is_array($available))
				$available = [
					$available
				];

			$list = HeaderValueFactory::createFromMessage($request,
				$requestHeaderField);
			if (!Container::isTraversable($available))
				$available = [
					$available
				];

			$available = Container::map($available,
				function ($k, $v) use ($normalizer) {
					return \call_user_func($normalizer, $v);
				});

			$result = null;
			if ($list instanceof AlternativeValueListInterface)
			{
				$result = \call_user_func($negociator, $list, $available,
					$negociationFlags, $requestHeaderField);
			}
			elseif ($negociationFlags & self::ALL_MATCH)
				$result = $available;
			else
				$result = Container::firstValue($available);

			$negociated[$responseHeaderField] = $result;
		}

		/*
		 * CAccept-Charset special case. Act a a supplementary filter
		 * on Content-Type
		 */

		$list = HeaderValueFactory::createFromMessage($request,
			HeaderField::ACCEPT_CHARSET);

		if (($available = Container::keyValue($negociated,
			HeaderField::CONTENT_TYPE)))
		{
			if ($list instanceof AlternativeValueListInterface)
			{
				$negociated[HeaderField::CONTENT_TYPE] = $this->negociateCharset(
					$list, $available, $flags);
			}
			elseif (($flags & self::ALL_MATCH) == 0)
			{
				$negociated[HeaderField::CONTENT_TYPE] = Container::firstValue(
					$available);
			}
		}

		return $negociated;
	}

	/**
	 *
	 * @param ResponseInterface $response
	 *        	The response to alter
	 * @param array $negociatied
	 *        	Result of negociation
	 * @param array $availables
	 *        	Same list of available alternative given during negociation.
	 *        	List of header name is also accepted.
	 * @return \Psr\Http\Message\ResponseInterface A new response, copy of the response given in
	 *         parameter filled with negociation headers.
	 */
	public function populateResponse(ResponseInterface $response,
		$negociatied, $availables = null)
	{
		$vary = [];
		if (Container::isTraversable($availables))
		{
			if (Container::isIndexed($availables))
				$vary = $availables;
			else
			{
				static $headerMap = [
					HeaderField::ACCEPT => HeaderField::CONTENT_TYPE,
					HeaderField::ACCEPT_ENCODING => HeaderField::CONTENT_ENCODING,
					HeaderField::ACCEPT_LANGUAGE => HeaderField::CONTENT_LANGUAGE
				];

				$vary = [];
				foreach ($headerMap as $requestHeader => $responseHeader)
				{
					if (!Container::keyExists($negociatied,
						$responseHeader))
						continue;
					$vary[] = $requestHeader;
				}
			}
		}

		if (Container::count($vary))
			$response = $response->withHeader(HeaderField::VARY,
				Container::implodeValues($vary, ' '));

		foreach ($negociatied as $h => $v)
		{
			$response = $response->withoutHeader($h)->withHeader($h,
				HeaderValueFactory::stringifyValue($v, $h));
		}

		return $response;
	}

	/**
	 *
	 * @param \Traversable $accepted
	 *        	List of AcceptHeaderValue or MediaRange with quality value parameter.
	 * @param MediaTypeInterface[] $available
	 *        	List of available media type
	 * @return MediaTypeInterface
	 */
	public function negociateContentType($accepted, $available,
		$flags = 0)
	{
		if (!\is_array($available))
			$available = [
				$available
			];
		$qvalues = [];
		$filtered = [];

		foreach ($available as $key => $contentType)
		{
			if (!($contentType instanceof MediaTypeInterface))
				throw new \InvalidArgumentException(
					MediaTypeInterface::class . ' expected. Got ' .
					TypeDescription::getName($contentType));

			$qvalue = $this->getContentTypeQualityValue($contentType,
				$accepted);
			if ($qvalue < 0)
				continue;

			$qvalues[$key] = $qvalue;
			$filtered[$key] = $contentType;
		}

		if (Container::count($filtered) == 0)
			throw new ContentNegociationException(
				HeaderField::CONTENT_TYPE);

		Container::uksort($filtered,
			function ($a, $b) use ($qvalues, $filtered) {
				$qa = $qvalues[$a];
				$qb = $qvalues[$b];
				if ($qa != $qb)
					return (\intval($qb * 100)) - (\intval($qa * 100));

				$ma = $filtered[$a];
				$mb = $filtered[$b];
				try
				{
					$c = $mb->compare($ma);
					if ($c != 0)
						return $c;
				}
				catch (NotComparableException $e)
				{
					return 0;
				}

				$ca = $ma->getParameters()->count();
				$cb = $mb->getParameters()->count();

				return ($cb - $ca);
			});

		if ($flags & self::ALL_MATCH)
			return $filtered;

		return Container::firstValue($filtered);
	}

	/**
	 *
	 * @param \Traversable $accepted
	 *        	List of accepted coding by the user agent.
	 *        	The list can be one of the following types:
	 *        	<ul>
	 *        	<li>A AcceptEncodingAlternativeValueList</li>
	 *        	<li>A list of AcceptEncodingHeaderValue</li>
	 *        	<li>A list of coding-qvalue pair</li>
	 *        	<li>A list of coding</li>
	 *        	</ul>
	 * @param \Traversable $available
	 *        	List of available coding supported by the server
	 * @return string|mixed|array|unknown[]|\Iterator[]|mixed[]|NULL[]|array[]|\ArrayAccess[]|\Psr\Container\ContainerInterface[]|\Traversable[]
	 * @see https://datatracker.ietf.org/doc/html/rfc7231#section-5.3.4
	 */
	public function negociateEncoding($accepted, $available, $flags = 0)
	{
		if (!Container::isTraversable($available))
			throw new \InvalidArgumentException(
				'Available media types argument is not traversable.');
		$hasAny = false;
		$explicitelyAccepted = Container::map($accepted,
			function ($k, $a) use (&$hasAny) {
				$coding = null;
				if ($a instanceof AcceptEncodingHeaderValue)
					$coding = $a->getCoding();
				elseif (\is_string($k) && \is_numeric($a))
					$coding = $k;
				else
					$coding = TypeConversion::toString($a);
				if ($coding == AcceptEncodingHeaderValue::ANY)
				{
					$hasAny = true;
					$coding = null;
				}
				return $coding;
			});
		$explicitelyAccepted = Container::values($explicitelyAccepted);
		$explicitelyAccepted = \array_filter($explicitelyAccepted,
			'\is_string');

		$scores = [
			ContentCoding::IDENTITY => 1
		];

		foreach ($available as $a)
			$scores[$a] = 1;

		foreach ($accepted as $k => $a)
		{
			$q = 1;
			$coding = null;
			if ($a instanceof AcceptEncodingHeaderValue)
			{
				$s = $a->getQualityValue();
				$coding = $a->getCoding();
			}
			elseif (\is_string($k) && \is_numeric($a))
			{
				$coding = $k;
				$q = $a;
			}
			else
				$coding = TypeConversion::toString($a);

			if ($coding == AcceptEncodingHeaderValue::ANY)
			{
				foreach ($scores as $c => $score)
				{
					if (!Container::valueExists($explicitelyAccepted, $c))
						$scores[$c] = min($q, $scores[$c]);
				}

				continue;
			}

			if (!Container::keyExists($scores, $coding))
				continue;

			$scores[$coding] = min($q, $scores[$coding]);
		}

		$filtered = Container::filter($scores,
			function ($k, $v) use ($hasAny, $explicitelyAccepted) {
				if ($v < 0.001)
					return false;
				if ($hasAny)
					return true;
				return Container::valueExists($explicitelyAccepted, $k);
			});

		/**
		 * None of the available codings are acceptable
		 * AND the identity "coding" was explicitely rejected.
		 */
		if (Container::count($filtered) == 0)
			throw new ContentNegociationException(
				HeaderField::CONTENT_ENCODING);

		Container::asort($filtered);

		if ($flags & self::ALL_MATCH)
			return \array_keys(\array_reverse($filtered));

		return Container::firstKey(\array_reverse($filtered));
	}

	public function negociateCharset($accepted, $available, $flags = 0)
	{
		if (!\is_array($available))
			$available = [
				$available
			];
		$preferences = [];
		$preferredCharset = null;
		$preferredCharsetValue = 0;

		$defaultValue = 0;
		foreach ($accepted as $k => $a)
		{
			$charset = $a;
			$q = 1;
			if ($a instanceof AcceptCharsetHeaderValue)
			{
				$q = $a->getQualityValue();
				$charset = $a->getCharset();
			}
			elseif (\is_string($k) && \is_numeric($a))
			{
				$q = $a;
				$charset = $k;
			}

			if ($charset == AcceptCharsetHeaderValue::ANY)
			{
				$hasAny = true;
				$defaultValue = $q;
				continue;
			}

			if ($q > $preferredCharsetValue)
			{
				$preferredCharset = $charset;
				$preferredCharsetValue = $q;
			}

			$key = \strtolower($charset);
			$preferences[$key] = [
				'charset' => $charset,
				'q' => $q
			];
		}

		$negociated = [];
		foreach ($available as $a)
		{
			$ca = null;
			if ($a instanceof MediaTypeInterface)
				$ca = Container::keyValue($a->getParameters(), 'charset',
					$preferredCharset);
			else
				$ca = TypeConversion::toString($a);

			$ka = \strtolower($ca);

			$qa = $defaultValue;
			if (Container::keyExists($preferences, $ka))
				$qa = $preferences[$ka]['q'];
			if ($qa <= 0)
				continue;

			$negociated[] = [
				'value' => $a,
				'charset' => $ca,
				'q' => $qa
			];
		}

		Container::uasort($negociated,
			function ($a, $b) {
				$v = $b['q'] - $a['q'];
				if ($v)
					return $v;

				$a = $a['value'];
				$b = $b['value'];

				if ($a instanceof MediaTypeInterface)
				{
					if (!($b instanceof MediaTypeInterface))
						return -1;

					$va = Container::keyExists($a->getParameters(),
						'charset') ? 1 : 0;
					$vb = Container::keyExists($b->getParameters(),
						'charset') ? 1 : 0;

					$v = $vb - $va;
					if ($v)
						return $v;
				}
				elseif ($b instanceof MediaTypeInterface)
					return 1;
				return 0;
			});

		if ($flags & self::ALL_MATCH)
		{
			$list = [];
			foreach ($negociated as $entry)
			{
				$list[] = $this->appendCharset($entry['value'],
					$entry['charset']);
			}
			return $list;
		}

		$best = Container::firstValue($negociated);
		$charset = $best['charset'];
		$best = $best['value'];
		$best = $this->appendCharset($best, $charset);

		return $best;
	}

	/**
	 *
	 * @param \Traversable $accepted
	 * @param Traversable $availables
	 * @param string $headerField
	 *        	Accept header field name
	 * @throws ContentNegociationException
	 * @return mixed
	 */
	public function negociateLanguage($accepted, $available, $flags = 0)
	{
		if (!\is_array($available))
			$available = [
				$available
			];
		$preferences = [];

		$defaultValue = 1;
		foreach ($accepted as $k => $a)
		{
			$range = null;
			$q = 1;
			if ($a instanceof AcceptLanguageHeaderValue)
			{
				$q = $a->getQualityValue();
				$range = $a->getLanguageRange();
				$a = \strval($a);
			}
			elseif ($a instanceof LanguageRange)
				$range = $a;
			elseif (\is_string($k) && \is_numeric($a))
			{
				$q = $a;
				$a = $k;
			}

			if ($range === null)
				$range = LanguageRange::createFromString($a,
					LanguageRange::TYPE_BASIC);

			if (!\is_string($a))
				$a = TypeConversion::toString($range);

			if ($a == LanguageRange::ANY)
			{
				$hasAny = true;
				$defaultValue = $q;
				continue;
			}

			$preferences[\strval($range)] = [
				'range' => $range,
				'q' => $q
			];
		}

		Container::uasort($preferences,
			function ($a, $b) {
				$qa = $a['q'];
				$qb = $b['q'];

				$v = (($qb - $qa) > 0) ? 1 : -1;
				if ($v)
					return $v;
				$sa = \strtolower(\strval($a['range']));
				$sb = \strtolower(\strval($b['range']));
				$la = \strlen($sa);
				$lb = \strlen($sb);
				$l = min($la, $lb);
				$sa = \substr($sa, 0, $l);
				$sb = \substr($sb, 0, $l);
				if ($sa == $sb)
					return $lb - $la;
				return -1;
			});

		foreach ($preferences as $p)
		{
			$range = $p['range'];
			$filter = new LanguageRangeFilter($range);
			foreach ($available as $a)
			{
				$m = $filter->match($a);

				if ($m)
					return $a;
			}
		}

		/**
		 * On matching failure, the recommended behavior is to
		 * ignore the header.
		 *
		 * @see https://datatracker.ietf.org/doc/html/rfc7231#section-5.3.5
		 */

		if ($flags & self::ALL_MATCH)
			return $available;

		return Container::firstValue($available);
	}

	/**
	 * Compute the quality value of the given media type against a list of accepted media ranges.
	 *
	 * @param MediaTypeInterface $contentType
	 * @param \Traversable $acceptedMediaRanges
	 *        	List of AcceptHeaderValue or MediaRange with quality value parameter.
	 * @return number Quality value in the range [0.001, 1] or -1 if media type is not acceptable
	 */
	public function getContentTypeQualityValue(
		MediaTypeInterface $contentType, $acceptedMediaRanges)
	{
		$conformanceScore = -1;
		$qualityValue = -1;
		$subTypeText = \strval($contentType->getSubType());

		foreach ($acceptedMediaRanges as $accepted)
		{
			$mediaRange = null;
			if ($accepted instanceof MediaTypeInterface)
				$mediaRange = $accepted;
			elseif ($accepted instanceof AcceptHeaderValue)
				$mediaRange = $accepted->getMediaRange();
			elseif (TypeDescription::hasStringRepresentation($accepted))
			{
				$text = TypeConversion::toString($mediaRange);
				$mediaRange = MediaRange::createFromString($text, true);
			}

			if (!($mediaRange instanceof MediaTypeInterface))
				throw new \InvalidArgumentException(
					'Unable to get Media range from ' .
					TypeDescription::getName($accepted));

			$mainTypeScore = 0;
			$subTypeScore = 0;
			$parameterScore = 0;

			if ($mediaRange->getType() != MediaRange::ANY)
			{
				if ($mediaRange->getType() != $contentType->getType())
					continue;

				$mainTypeScore = 1;
			}

			$subTypeString = \strval($mediaRange->getSubType());
			if ($subTypeString != MediaRange::ANY)
			{
				if ($subTypeString != $subTypeText)
					continue;

				$subTypeScore = 1;
			}

			$count = $mediaRange->getParameters()->count() +
				$contentType->getParameters()->count();

			if ($count)
			{
				foreach ($mediaRange->getParameters() as $name => $value)
				{
					if ($contentType->getParameters()->offsetExists(
						$name))
						if ($contentType->getParameters()[$name] ==
							$value)
							$parameterScore++;
				}

				$parameterScore /= ($count);
			}
			else
				$parameterScore = 1;

			$score = 100 * $mainTypeScore + 10 * $subTypeScore +
				$parameterScore;

			if ($score > $conformanceScore)
			{
				$qualityValue = 1;
				if ($accepted instanceof QualityValueInterface)
					$qualityValue = $accepted->getQualityValue();
				elseif ($mediaRange->getParameters()->offsetExists('q'))
					$qualityValue = TypeConversion::toFloat(
						$mediaRange->getParameters()->offsetGet('q'));

				$conformanceScore = $score;
			}
		}

		return $qualityValue;
	}

	/**
	 *
	 * @param mixed $input
	 * @return MediaTypeInterface
	 */
	protected function normalizeMediaType($input)
	{
		if ($input instanceof MediaTypeInterface)
			return $input;
		if ($input instanceof ContentTypeHeaderValue)
			return $input->getMediaType();
		if (TypeDescription::hasStringRepresentation($input))
		{
			$mediaType = MediaType::createFromString($input, true);
			return $mediaType;
		}

		throw new \InvalidArgumentException('Invalid input');
	}

	protected function appendCharset($entry, $charset)
	{
		if (!($entry instanceof MediaTypeInterface &&
			!$entry->getParameters()->has('charset')))
			return $entry;

		/** @var MediaTypeInterface $entry */
		$entry = clone $entry;
		$p = $entry->getParameters();
		Container::setValue($p, 'charset', $charset);

		return $entry;
	}
}
