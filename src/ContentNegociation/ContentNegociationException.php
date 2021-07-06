<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\ContentNegociation;

use NoreSources\Http\Status;
use NoreSources\Http\StatusExceptionInterface;
use NoreSources\Http\Traits\StatusExceptionTrait;

class ContentNegociationException extends \Exception implements
	StatusExceptionInterface
{

	use StatusExceptionTrait;

	public function __construct($negociationType)
	{
		$this->negociationType = $negociationType;
		parent::__construct($negociationType . ' negociation error',
			Status::NOT_ACCEPTABLE);
	}

	public function getNegociationType()
	{
		return $this->negociationType;
	}

	private $negociationType;
}
