<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

/**
 * Quality value.
 *
 * RFC7230 Section 5.3.5
 *
 * @see https://tools.ietf.org/html/rfc7231#section-5.3.5
 *
 */
interface QualityValueInterface
{

	/**
	 *
	 * @return float A quality value in the range [0.001, 1]
	 */
	function getQualityValue();

	/**
	 * Set the quality value
	 *
	 * @param float $qualityValue
	 *        	Quality value in the range [0.001, 1]
	 */
	function setQualityValue($qualityValue);
}