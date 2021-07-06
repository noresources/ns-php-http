<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\ContentNegociation;

use NoreSources\SingletonTrait;
use NoreSources\Container\Container;
use NoreSources\Http\QualityValueInterface;
use NoreSources\Http\Coding\ContentCoding;
use NoreSources\Http\Header\AcceptEncodingHeaderValue;
use NoreSources\Http\Header\AcceptHeaderValue;
use NoreSources\Http\Header\AlternativeValueListInterface;
use NoreSources\Http\Header\ContentTypeHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\MediaType\MediaRange;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;
use Psr\Http\Message\RequestInterface;
use Traversable;

class ContentNegociator
{
	use SingletonTrait;

	/**
	 *
	 * @param RequestInterface $request
	 * @param
	 *        	array<string, mixed> $availables
	 * @return array<string, mixed>
	 */
	public function negociate(RequestInterface $request,
		$availables = array())
	{
		$negociated = [];

		$map = [
			HeaderField::ACCEPT => [
				'field' => HeaderField::CONTENT_TYPE,
				'negociator' => [
					$this,
					'negociateContentType'
				],
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
			$normalizer = Container::keyValue($properties, 'normalizer',
				[
					TypeDescription::class,
					'toString'
				]);
			$list = HeaderValueFactory::fromMessage($request,
				$requestHeaderField);
			$available = Container::keyValue($availables,
				$responseHeaderField,
				Container::keyValue($availables, $requestHeaderField,
					null));

			if ($available === null)
				continue;

			if (!Container::isTraversable($available))
				$available = [
					$available
				];

			$available = Container::map($available,
				function ($k, $v) use ($normalizer) {
					return \call_user_func($normalizer, $v);
				});

			if ($list instanceof AlternativeValueListInterface)
			{
				$negociated[$responseHeaderField] = \call_user_func(
					$negociator, $list, $available, $requestHeaderField);
			}
			else
				$negociated[$responseHeaderField] = Container::firstValue(
					$available);
		}

		return $negociated;
	}

	/**
	 *
	 * @param \Traversable $accepted
	 *        	List of AcceptHeaderValue or MediaRange with quality value parameter.
	 * @param MediaTypeInterface[] $available
	 *        	List of available media type
	 * @return MediaTypeInterface
	 */
	public function negociateContentType($accepted, $available)
	{
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
			throw new ContentNegociationException(self::CONTENT_TYPE);

		Container::uksort($filtered,
			function ($a, $b) use ($qvalues) {
				$a = $qvalues[$a];
				$b = $qvalues[$b];
				if ($a == $b)
					return 0;
				return ($a > $b) ? -1 : 1;
			});

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
	public function negociateEncoding($accepted, $available)
	{
		$hasAnyCoding = false;
		$explicitelyAccepted = Container::map($accepted,
			function ($k, $a) use (&$hasAnyCoding) {
				$coding = null;
				if ($a instanceof AcceptEncodingHeaderValue)
					$coding = $a->getCoding();
				elseif (\is_string($k) && \is_numeric($a))
					$coding = $k;
				elseif (\is_string($a))
					$coding = TypeConversion::toString($a);
				if ($coding == AcceptEncodingHeaderValue::ANY)
				{
					$hasAnyCoding = true;
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
			function ($k, $v) use ($hasAnyCoding, $explicitelyAccepted) {
				if ($v < 0.001)
					return false;
				if ($hasAnyCoding)
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

		asort($filtered);
		return Container::firstKey(\array_reverse($filtered));
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
	public function negociateLanguage($accepted, $availables,
		$headerField)
	{
		foreach ($accepted as $a)
		{
			foreach ($availables as $available)
			{
				if (\strcasecmp(TypeConversion::toString($a),
					TypeConversion::toString($available)) == 0)
				{
					return $available;
				}
			}
		}

		throw new ContentNegociationException($headerField);
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
				$mediaRange = new MediaRange(MediaRange::ANY);
				$mediaRange->unserialize($text);
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
			$mediaType = new MediaType('');
			$mediaType->unserialize(TypeConversion::toString($input));
			return $mediaType;
		}

		throw new \InvalidArgumentException('Invalid input');
	}
}
