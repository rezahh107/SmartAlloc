<?php
namespace SmartAlloc\Domain\Allocation;

use InvalidArgumentException;

class StudentAllocator
{
    /**
     * Reads JSON from php://input, validates it, and returns decoded data.
     *
     * @return array<string, mixed>
     */
    public function allocate(): array
    {
        $input = file_get_contents('php://input');

        if (!self::isValidJson($input)) {
            throw new InvalidArgumentException('Invalid JSON input');
        }

        /** @var array<string, mixed> $studentData */
        $studentData = json_decode($input, true, flags: JSON_THROW_ON_ERROR);

        return $studentData;
    }

    private static function isValidJson(string $input): bool
    {
        if (function_exists('json_validate')) {
            return json_validate($input);
        }

        json_decode($input);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
