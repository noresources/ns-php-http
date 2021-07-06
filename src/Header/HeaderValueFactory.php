<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
namespace NoreSources\Http\Header;

use NoreSources\Container\Container;
use NoreSources\Http\RFC7230;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

class HeaderValueFactory
{

	/**
	 * Get the expected Header value class names for the given header name
	 *
	 * @param string $headerFieldName
	 *        	Header field name
	 * @return string[] AlternativeValueList and HeaderValue class names
	 */
	public static function getHeaderValueClassnames($headerFieldName)
	{
		$camelCaseHeeaderName = Container::implodeValues(
			explode('-', \strtolower($headerFieldName)), '',
			function ($part) {
				return strtoupper(\substr($part, 0, 1)) .
				\strtolower(\substr($part, 1));
			});

		return [
			(__NAMESPACE__ . '\\' . $camelCaseHeeaderName .
			'AlternativeValueList'),
			(__NAMESPACE__ . '\\' . $camelCaseHeeaderName . 'HeaderValue')
		];
	}

	/**
	 *
	 * @param string $headerFieldName
	 * @param MessageInterface $message
	 * @param boolean $multiple
	 * @return AlternativeValueListInterface|HeaderValueInterface
	 */
	public static function fromMessage(MessageInterface $message,
		$headerFieldName, $multiple = false)
	{
		if (!$message->hasHeader($headerFieldName))
			return ($multiple) ? array() : null;

		$headerValues = $message->getHeader($headerFieldName);

		if ($multiple)
		{
			$alternatives = [];
			foreach ($headerValues as $headerValue)
			{
				$alternatives[] = self::fromKeyValue($headerFieldName,
					$headerValue);
			}

			return $alternatives;
		}

		$headerValue = Container::firstValue($headerValues);
		return self::fromKeyValue($headerFieldName, $headerValue);
	}

	/**
	 *
	 * @deprecated Use fromMessage()
	 */
	public static function fromRequest(RequestInterface $request,
		$headerFieldName, $multiple = false)
	{
		return self::fromMessage($request, $headerFieldName);
	}

	/**
	 *
	 * @param string $headerLine
	 * @param boolean $returnKeyValue
	 *        	If true, return an array containing the header name and header value
	 * @throws InvalidHeaderException::
	 * @return HeaderValueInterface|array The header value or an array [name, value] if
	 *         $returnKeyValue is true
	 */
	public static function fromHeaderLine($headerLine,
		$returnKeyValue = false)
	{
		$pattern = '(' . RFC7230::TOKEN_PATTERN . '):' .
			RFC7230::OWS_PATTERN . '(.*)';
		$m = [];
		if (!\preg_match(chr(1) . $pattern . chr(1) . 'i', $headerLine,
			$m))
			throw new InvalidHeaderException($pattern,
				InvalidHeaderException::INVALID_HEADER_LINE);

		try
		{
			$value = self::fromKeyValue($m[1], $m[2]);
		}
		catch (\Exception $e)
		{
			throw new InvalidHeaderException($e->getMessage(),
				InvalidHeaderException::INVALID_HEADER_VALUE);
		}

		if ($returnKeyValue)
			return [
				$m[1],
				$value
			];
		return $value;
	}

	/**
	 *
	 * @param string $headerFieldName
	 * @param string $headerValue
	 * @return AlternativeValueListInterface|HeaderValueInterface
	 */
	public static function fromKeyValue($headerFieldName, $headerValue)
	{
		$headerValue = \ltrim($headerValue);

		list ($alternativeValueListClassname, $headerValueClassName) = self::getHeaderValueClassnames(
			$headerFieldName);

		if (!(\class_exists($headerValueClassName) &&
			($reflection = new \ReflectionClass($headerValueClassName)) &&
			$reflection->implementsInterface(
				HeaderValueInterface::class)))
		{
			$headerValueClassName = TextHeaderValue::class;
		}

		// Alternative list values

		if (\class_exists($alternativeValueListClassname) &&
			($listReflection = new \ReflectionClass(
				$alternativeValueListClassname)) &&
			$listReflection->implementsInterface(
				AlternativeValueListInterface::class))
		{
			if ($listReflection->hasMethod('fromString'))
				return \call_user_func(
					[
						$alternativeValueListClassname,
						'fromString'
					], $headerValue);

			$valueDelimiter = ',';
			if ($listReflection->hasConstant('VALUE_DELIMITER'))
				$valueDelimiter = $listReflection->getConstant(
					'VALUE_DELIMITER');

			if ($listReflection->hasConstant('HEADERVALUE_CLASSNAME'))
				$headerValueClassName = $listReflection->getConstant(
					'HEADERVALUE_CLASSNAME');

			$valueReflection = new \ReflectionClass(
				$headerValueClassName);

			if (!$valueReflection->hasMethod('parseFieldValueString'))
				throw new \Exception(
					'No deserialization method available for header');

			$alternatives = [];
			$delimiter = false;

			do
			{
				list ($instance, $consumed) = \call_user_func(
					[
						$headerValueClassName,
						'parseFieldValueString'
					], $headerValue);

				$alternatives[] = $instance;
				$headerValue = \ltrim(\substr($headerValue, $consumed));

				$delimiter = \strpos($headerValue, $valueDelimiter);
				if ($delimiter !== false)
					$headerValue = \ltrim(
						\substr($headerValue, $delimiter + 1));
			}
			while ($delimiter !== false);

			return $listReflection->newInstanceArgs([
				$alternatives
			]);
		}

		// Single value

		if (\class_exists($headerValueClassName) &&
			($reflection = new \ReflectionClass($headerValueClassName)) &&
			$reflection->implementsInterface(
				HeaderValueInterface::class))
		{
			if ($reflection->hasMethod('fromString'))
				return \call_user_func(
					[
						$headerValueClassName,
						'fromString'
					], $headerValue);

			if ($reflection->hasMethod('parseFieldValueString'))
			{
				list ($instance, $consumed) = \call_user_func(
					[
						$headerValueClassName,
						'parseFieldValueString'
					], $headerValue);
				return $instance;
			}

			return $reflection->newInstance($headerValue);
		}

		return new TextHeaderValue($headerValue);
	}
}
