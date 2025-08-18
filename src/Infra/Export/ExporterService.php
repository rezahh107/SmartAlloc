<?php
namespace SmartAlloc\Infra\Export;

use InvalidArgumentException;
use PDO;
use PDOStatement;

class ExporterService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        // Use in-memory SQLite for demonstration/testing purposes
        $this->db = $db ?? new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Export data for a given id, ensuring the id is numeric and using
     * prepared statements to avoid SQL injection.
     *
     * @param string $id
     * @return array<int, array<string, mixed>>
     */
    public function exportData(string $id): array
    {
        if (!ctype_digit($id)) {
            throw new InvalidArgumentException('Invalid id');
        }

        /** @var PDOStatement $stmt */
        $stmt = $this->db->prepare('SELECT * FROM exports WHERE id = :id');
        $stmt->execute([':id' => (int) $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
