<?php
/**
 * Copyright © 2012 - 2020 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

/**
 * RFC 7231 Accept header value
 *
 * @see https://tools.ietf.org/html/rfc7231#section-5.3.2
 *
 */
class AcceptAlternativeValueList implements AlternativeValueListInterface
{

	use AlternativeValueListTrait;
}