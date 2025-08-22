<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

require_once __DIR__ . '/../../scripts/qa-report.php';

final class QaReportAggregationTest extends BaseTestCase
{
    public function test_aggregates_signals_and_validations(): void
    {
        $root = sys_get_temp_dir() . '/sa_qa_' . uniqid();
        $art = $root . '/artifacts';
        @mkdir($art . '/coverage', 0777, true);
        @mkdir($art . '/schema', 0777, true);
        @mkdir($art . '/security', 0777, true);
        @mkdir($art . '/compliance', 0777, true);
        @mkdir($art . '/exporter', 0777, true);
        @mkdir($art . '/validation', 0777, true);

        file_put_contents($art . '/coverage/coverage.json', json_encode(['totals'=>['lines'=>['pct'=>95]]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/schema/schema-validate.json', json_encode(['count'=>3], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/security/rest-permissions.json', json_encode(['summary'=>['routes'=>5,'mutating_warnings'=>1,'readonly_warnings'=>2]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/security/sql-prepare.json', json_encode(['counts'=>['violations'=>1,'allowlisted'=>2]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/security/secrets.json', json_encode(['counts'=>['violations'=>0,'allowlisted'=>0]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/security/headers.json', json_encode(['counts'=>['missing'=>1,'allowlisted'=>0]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/compliance/license-audit.json', json_encode(['counts'=>['unapproved'=>0]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/exporter/validate.json', json_encode(['counts'=>['errors'=>0,'warnings'=>1]], JSON_UNESCAPED_SLASHES));
        file_put_contents($art . '/validation/form150.json', json_encode(['violations'=>[
            'national_id_checksum'=>1,
            'mobile_prefix_09'=>2,
            'landline_eq_mobile'=>3,
            'duplicate_liaison_phone'=>4,
            'postal_code_fuzzy'=>['accept'=>5,'manual'=>6,'reject'=>7],
            'hikmat_tracking_sentinel'=>8,
        ]], JSON_UNESCAPED_SLASHES));

        $out = $root . '/out';
        $rep1 = qa_report($root, $out);
        $json1 = file_get_contents($out . '/qa-report.json');
        $rep2 = qa_report($root, $out);
        $json2 = file_get_contents($out . '/qa-report.json');

        $this->assertSame($rep1['summary']['exporter']['warnings'], 1);
        $this->assertSame(1, $rep1['summary']['validation']['national_id_checksum']);
        $this->assertMatchesRegularExpression('/T.*Z/', $rep1['timestamp_utc']);
        $this->assertSame($json1, $json2, 'Report must be deterministic');
    }
}
