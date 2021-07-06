<?php

/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Http
 */
namespace NoreSources\Http\Header;

use NoreSources\Http\Header\Traits\AlternativeValueListTrait;

class AcceptEncodingAlternativeValueList implements
	AlternativeValueListInterface
{
	use AlternativeValueListTrait;
}
