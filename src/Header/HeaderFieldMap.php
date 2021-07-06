<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Container\ArrayAccessContainerInterfaceTrait;
use NoreSources\Container\CaseInsensitiveKeyMapTrait;
use NoreSources\Type\ArrayRepresentation;
use Psr\Container\ContainerInterface;

class HeaderFieldMap implements \ArrayAccess, ArrayRepresentation,
	ContainerInterface, \Countable, \IteratorAggregate
{
	use CaseInsensitiveKeyMapTrait;
	use ArrayAccessContainerInterfaceTrait;
}
