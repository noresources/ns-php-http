<?php
namespace NoreSources\Http\Traits;

use NoreSources\Type\TypeConversion;

/**
 * Default StatusExceptionInterface implementation using Exception class error code
 */
trait StatusExceptionTrait
{

	public function getStatusCode()
	{
		$e = $this;
		if ($e instanceof \ErrorException)
			return $e->getCode();
		return TypeConversion::toInteger($e);
	}
}
