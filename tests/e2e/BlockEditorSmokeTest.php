<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class BlockEditorSmokeTest extends BaseTestCase
{
    public function test_block_json_api_v3_or_skip(): void
    {
        $root = dirname(__DIR__, 2);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $blockJsons = [];
        foreach ($it as $f) {
            if ($f->isFile() && $f->getFilename() === 'block.json' && strpos($f->getPathname(), '/tests/') === false && strpos($f->getPathname(), '/vendor/') === false) {
                $blockJsons[] = $f->getPathname();
            }
        }
        if (empty($blockJsons)) {
            $this->markTestSkipped('No custom block — API v3 compliance not applicable');
        }
        $ok = false;
        foreach ($blockJsons as $p) {
            $data = json_decode((string) file_get_contents($p), true);
            if (isset($data['apiVersion']) && (int) $data['apiVersion'] === 3 && isset($data['name']) && isset($data['title']) && (isset($data['editorScript']) || isset($data['editorScriptModule'])) && (isset($data['style']) || isset($data['editorStyle']))) {
                $ok = true;
            }
        }
        $this->assertTrue($ok, 'Found block.json but apiVersion 3 missing');
    }
}
