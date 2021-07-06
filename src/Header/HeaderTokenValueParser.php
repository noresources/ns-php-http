<?php
namespace NoreSources\Http\Header;

use NoreSources\Http\ParameterMap;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Http\QualityValueInterface;
use NoreSources\Http\RFC7230;

/**
 * Parse token value with optional quality value
 */
class HeaderTokenValueParser
{

	/**
	 *
	 * @param string $headerValueClassname
	 *        	HeaderValueInterface class name
	 * @param callable $tokenValidator
	 *        	Token validator
	 */
	public function __construct(
		$headerValueClassname = TextHeaderValue::class,
		$tokenValidator = null)
	{
		$this->headerValueClass = new \ReflectionClass(
			$headerValueClassname);
		$this->tokenValidator = $tokenValidator;
		$this->delimiter = ',';
	}

	public function parseText($value)
	{
		$valueLength = \strlen($value);
		$text = \ltrim($value);
		$textLength = \strlen($text);
		$consumed = $valueLength - $textLength;

		$token = $text;

		$semicolon = \strpos($text, ';');
		$delimiter = \strpos($text, $this->delimiter);

		if ($delimiter !== false)
		{
			if ($semicolon !== false && $semicolon > $delimiter)
				$semicolon = false;
		}

		if ($semicolon !== false)
		{
			if (!$this->headerValueClass->implementsInterface(
				QualityValueInterface::class))
				throw new \InvalidArgumentException(
					$this->headerValueClass->getName() .
					'does not accept parameters');

			$token = \trim(\substr($text, 0, $semicolon));
		}

		if (!empty($token))
		{
			if (!\preg_match(
				chr(1) . RFC7230::OWS_PATTERN . '(' .
				RFC7230::TOKEN_PATTERN . ')' . chr(1), $token, $match))
				throw new \InvalidArgumentException(
					$token . ' ns not a valid token');

			$consumed += \strlen($match[0]);
			$token = $match[1];
		}

		if (\is_callable($this->tokenValidator) &&
			!\call_user_func($this->tokenValidator, $token))
			throw new \InvalidArgumentException(
				$token . ' is not a valid value for ' .
				$this->headerValueClass->getName());
		$instance = $this->headerValueClass->newInstance($token);

		if ($semicolon !== false)
		{
			$consumed += 1;
			$text = \substr($value, $consumed);
			$q = new ParameterMap();
			$consumed += ParameterMapSerializer::unserializeParameters(
				$q, $text,
				function ($n, $v) {

					return (\strcasecmp($n, 'q') == 0) ? ParameterMapSerializer::ACCEPT : ParameterMapSerializer::ABORT;
				});

			if ($q->has('q'))
				$instance->setQualityValue(\floatval($q->get('q')));
		}

		return [
			$instance,
			$consumed
		];
	}

	/**
	 *
	 * @var \ReflectionClass
	 */
	private $headerValueClass;

	private $delimiter;

	/**
	 *
	 * @var callable
	 */
	private $tokenValidator;
}
