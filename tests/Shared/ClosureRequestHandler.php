<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Test;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClosureRequestHandler implements RequestHandlerInterface
{

	public function __construct($closure)
	{
		$this->closure = $closure;
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		return \call_user_func($this->closure, $request);
	}

	private $closure;
}
