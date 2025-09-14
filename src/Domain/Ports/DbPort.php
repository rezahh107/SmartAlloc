<?php

namespace SmartAlloc\Domain\Ports;

interface DbPort
{
    /**
     * Prepare a SQL statement.
     *
     * @param string $query
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): string;

    /**
     * Retrieve a single variable.
     *
     * @param string $query
     * @return mixed
     */
    public function getVar(string $query);

    /**
     * Retrieve multiple rows.
     *
     * @param string $query
     * @return array
     */
    public function getResults(string $query): array;

    /**
     * Run a generic query.
     *
     * @param string $query
     * @return mixed
     */
    public function query(string $query);
}
