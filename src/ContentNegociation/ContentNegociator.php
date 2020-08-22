<?php
namespace NoreSources\Http\ContentNegociation;

use NoreSources\Container;
use NoreSources\SingletonTrait;
use NoreSources\TypeConversion;
use NoreSources\TypeDescription;
use NoreSources\Http\QualityValueInterface;
use NoreSources\Http\Header\AcceptAlternativeValueList;
use NoreSources\Http\Header\AcceptHeaderValue;
use NoreSources\Http\Header\AlternativeValueListInterface;
use NoreSources\Http\Header\ContentTypeHeaderValue;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\MediaType\MediaRange;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeInterface;
use Psr\Http\Message\RequestInterface;

class ContentNegociator
{
	use SingletonTrait;

	const CONTENT_TYPE = HeaderField::ACCEPT;

	const LANGUAGE = HeaderField::ACCEPT_LANGUAGE;

	const CHARSET = HeaderField::ACCEPT_CHARSET;

	const ENCODING = HeaderField::ACCEPT_ENCODING;

	public function negociate(RequestInterface $request,
		$availables = array())
	{
		$negociated = [];

		$accept = HeaderValueFactory::fromMessage($request,
			HeaderField::ACCEPT);

		if (($available = Container::keyValue($availables,
			self::CONTENT_TYPE)) && Container::count($available))
		{
			$available = Container::map($available,
				function ($index, $mediaType) {
					if ($mediaType instanceof ContentTypeHeaderValue)
						$mediaType = $mediaType->getMediaType();

					if (!($mediaType instanceof MediaTypeInterface))
					{
						$text = TypeConversion::toString($mediaType);
						$mediaType = new MediaType('');
						$mediaType->unserialize($text);
					}
					return $mediaType;
				});

			if ($accept instanceof AcceptAlternativeValueList)
				$negociated[self::CONTENT_TYPE] = $this->negociateContentType(
					$accept, $available);
			else
				$negociated[self::CONTENT_TYPE] = Container::firstValue(
					$available);
		}
		elseif ($accept instanceof AcceptAlternativeValueList)
		{
			/**
			 *
			 * @var AcceptHeaderValue $mediaRange
			 */
			$accept = Container::firstValue($accept);
			$negociated[self::CONTENT_TYPE] = $accept->getMediaRange();
		}

		/**
		 *
		 * @todo Less trivial implementation
		 */

		foreach ([
			self::LANGUAGE,
			self::CHARSET,
			self::ENCODING
		] as $type)
		{
			$list = HeaderValueFactory::fromMessage($request, $type);

			if (($available = Container::keyValue($availables, $type)))
			{
				if ($list instanceof AlternativeValueListInterface)
				{
					foreach ($list as $l)
					{
						foreach ($available as $a)
						{
							if (\strcasecmp(
								TypeConversion::toString($l),
								TypeConversion::toString($a)) == 0)
							{
								$negociated[$type] = $a;
							}
						}
					}
				}

				$negociated[$type] = Container::firstValue($available);
			}
			elseif ($list instanceof AlternativeValueListInterface)
			{
				$negociated[$type] = TypeConversion::toString(
					Container::firstValue($list));
			}
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

		uksort($filtered,
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
}
