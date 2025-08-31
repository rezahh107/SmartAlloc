<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Rules;

class RuleConfigError extends \RuntimeException {}
class RuleTimeout extends \RuntimeException {}
class InvalidInput extends \InvalidArgumentException {}
class ExternalDependencyError extends \RuntimeException {}
