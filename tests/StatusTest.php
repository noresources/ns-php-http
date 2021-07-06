<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http;

final class StatusTest extends \PHPUnit\Framework\TestCase
{

	final function testGetText()
	{
		$this->assertEquals('Not Found',
			Status::getStatusText(Status::NOT_FOUND),
			'Valid code returns status text');
		$this->assertEquals('', Status::getStatusText(-1),
			'Invalid code returns empty string');
	}
}


