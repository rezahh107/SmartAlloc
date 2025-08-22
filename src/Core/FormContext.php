<?php
declare(strict_types=1);

namespace SmartAlloc\Core;

final class FormContext {
    public function __construct(public readonly int $formId) {}
    public function suffix(): string { return '_f' . $this->formId; }
}
