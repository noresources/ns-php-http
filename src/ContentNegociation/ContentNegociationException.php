<?php
namespace NoreSources\Http\ContentNegociation;

use Laminas\HttpHandlerRunner\Exception\EmitterException;

class ContentNegociationException extends EmitterException
{

	public function __construct($negociationType)
	{
		$this->negociationType = $negociationType;
		parent::\__construct(
			$negociationType . ' negociation error');
	}

	public function getNegociationType()
	{
		return $this->negociationType;
	}

	private $negociationType;
}
