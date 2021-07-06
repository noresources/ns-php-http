<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Type\StringRepresentation;

interface AlternativeValueListInterface extends \Countable,
	\IteratorAggregate, StringRepresentation

{

	/**
	 *
	 * @param integer $index
	 *        	Alternative index
	 * @return HeaderValueInterface
	 */
	function getAlternative($index);
}