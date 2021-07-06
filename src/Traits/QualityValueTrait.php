<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Traits;

/**
 * Reference implementation of the QualityValueInterface
 */
trait QualityValueTrait
{

	public function getQualityValue()
	{
		return $this->qualityValue;
	}

	public function setQualityValue($qualityValue)
	{
		$this->qualityValue = min(1, max(0, $qualityValue));
	}

	public function getQualityValueParameterString()
	{
		return \sprintf("q=%.2f", $this->qualityValue);
	}

	/**
	 *
	 * @var float Quality value in the range [0.001, 1]
	 */
	private $qualityValue;
}