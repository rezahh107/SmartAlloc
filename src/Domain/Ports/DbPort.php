<?php

namespace SmartAlloc\Domain\Ports;

interface DbPort
{
    public function fetchOne(string $sql, array $params = array()): mixed;
    public function fetchAll(string $sql, array $params = array()): array;
    public function execute(string $sql, array $params = array()): int;
}
