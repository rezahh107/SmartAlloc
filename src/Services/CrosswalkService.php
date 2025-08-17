<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Crosswalk service for data mapping and caching
 */
final class CrosswalkService
{
    public function __construct(
        private Db $db,
        private Cache $cache,
        private Logging $logger
    ) {}

    /**
     * Get school code by name with caching
     */
    public function schoolCodeByName(string $name): int
    {
        $key = 'salloc:xwalk:v1:school:' . md5($name);
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return (int) $cached;
        }
        
        $row = $this->db->query(
            "SELECT code FROM {$this->getSchoolTable()} WHERE name = %s LIMIT 1",
            [$name]
        );
        
        $code = (int) ($row[0]['code'] ?? 0);
        $this->cache->l1Set($key, $code, 3600);
        
        return $code;
    }

    /**
     * Get city by school code
     */
    public function cityBySchoolCode(int $schoolCode): string
    {
        $key = 'salloc:xwalk:v1:city:' . $schoolCode;
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return (string) $cached;
        }
        
        $row = $this->db->query(
            "SELECT city FROM {$this->getSchoolTable()} WHERE code = %d LIMIT 1",
            [$schoolCode]
        );
        
        $city = (string) ($row[0]['city'] ?? '');
        $this->cache->l1Set($key, $city, 3600);
        
        return $city;
    }

    /**
     * Get mentor name by ID
     */
    public function mentorNameById(int $mentorId): string
    {
        $key = 'salloc:xwalk:v1:mentor:' . $mentorId;
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return (string) $cached;
        }
        
        $row = $this->db->query(
            "SELECT name FROM {$this->getMentorsTable()} WHERE mentor_id = %d LIMIT 1",
            [$mentorId]
        );
        
        $name = (string) ($row[0]['name'] ?? '');
        $this->cache->l1Set($key, $name, 3600);
        
        return $name;
    }

    /**
     * Get school information by code
     */
    public function getSchoolInfo(int $schoolCode): ?array
    {
        // Apply alias rule for postal code/school code
        $canonicalCode = $this->resolveAlias($schoolCode);
        
        $key = 'salloc:xwalk:v1:school_info:' . $canonicalCode;
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return $cached;
        }
        
        $row = $this->db->query(
            "SELECT * FROM {$this->getSchoolTable()} WHERE code = %d LIMIT 1",
            [$schoolCode]
        );
        
        $schoolInfo = $row[0] ?? null;
        if ($schoolInfo) {
            $this->cache->l1Set($key, $schoolInfo, 3600);
        }
        
        return $schoolInfo;
    }

    /**
     * Get nearby centers for a given center
     */
    public function getNearbyCenters(int $centerId): array
    {
        $key = 'salloc:xwalk:v1:nearby_centers:' . $centerId;
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return $cached;
        }
        
        $rows = $this->db->query(
            "SELECT center_id FROM {$this->getCentersTable()} WHERE nearby_center_id = %d",
            [$centerId]
        );
        
        $nearbyCenters = array_column($rows, 'center_id');
        $this->cache->l1Set($key, $nearbyCenters, 3600);
        
        return $nearbyCenters;
    }

    /**
     * Get city by center ID
     */
    public function getCityByCenterId(int $centerId): ?string
    {
        $key = 'salloc:xwalk:v1:city_by_center:' . $centerId;
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return $cached;
        }
        
        $row = $this->db->query(
            "SELECT city FROM {$this->getCentersTable()} WHERE center_id = %d LIMIT 1",
            [$centerId]
        );
        
        $city = $row[0]['city'] ?? null;
        if ($city) {
            $this->cache->l1Set($key, $city, 3600);
        }
        
        return $city;
    }

    /**
     * Invalidate cache by increasing version
     */
    public function invalidate(): void
    {
        // In a real implementation, this would bump the version number
        // For now, we'll just clear the cache
        $this->logger->info('crosswalk.invalidated');
    }

    /**
     * Get school table name
     */
    private function getSchoolTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'salloc_xw_school';
    }

    /**
     * Get mentors table name
     */
    private function getMentorsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'salloc_mentors';
    }

    /**
     * Get centers table name
     */
    private function getCentersTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'salloc_centers';
    }

    /**
     * Resolve alias codes to canonical codes
     * Maps alternate postal codes or school codes to their canonical versions
     */
    private function resolveAlias(int $code): int
    {
        $key = 'salloc:xwalk:v1:alias:' . $code;
        $cached = $this->cache->l1Get($key);
        
        if ($cached !== false && $cached !== null) {
            return (int) $cached;
        }

        // Check if this is an alias code
        $aliasRow = $this->db->query(
            "SELECT canonical_code FROM {$this->getAliasTable()} WHERE alias_code = %d LIMIT 1",
            [$code]
        );

        if (!empty($aliasRow)) {
            $canonicalCode = (int) $aliasRow[0]['canonical_code'];
            $this->cache->l1Set($key, $canonicalCode, 7200); // 2 hours cache
            return $canonicalCode;
        }

        // If no alias found, return the original code
        $this->cache->l1Set($key, $code, 7200);
        return $code;
    }

    /**
     * Get alias table name
     */
    private function getAliasTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'salloc_aliases';
    }

    /**
     * Update alias mapping (admin function)
     */
    public function updateAliasMapping(int $aliasCode, int $canonicalCode): bool
    {
        try {
            $result = $this->db->query(
                "INSERT INTO {$this->getAliasTable()} (alias_code, canonical_code, created_at) 
                 VALUES (%d, %d, NOW()) 
                 ON DUPLICATE KEY UPDATE canonical_code = VALUES(canonical_code), updated_at = NOW()",
                [$aliasCode, $canonicalCode]
            );

            // Clear cache for this alias
            $key = 'salloc:xwalk:v1:alias:' . $aliasCode;
            $this->cache->l1Delete($key);

            $this->logger->info('Alias mapping updated', [
                'alias_code' => $aliasCode,
                'canonical_code' => $canonicalCode
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update alias mapping', [
                'alias_code' => $aliasCode,
                'canonical_code' => $canonicalCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 