<?php
declare(strict_types=1);
namespace TinifyAI\Exception;

class InsufficientCreditsException extends \RuntimeException
{
	public function __construct( string $message, public readonly ?string $creditsResetAt = null ) {
		parent::__construct($message);
	}
}
