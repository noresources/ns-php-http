<?php
namespace NoreSources\Http;

interface StatusExceptionInterface
{

	/**
	 *
	 * @return integer HTTP status code
	 */
	function getStatusCode();
}
