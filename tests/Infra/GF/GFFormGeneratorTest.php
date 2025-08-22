<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Infra\GF;

use SmartAlloc\Infra\GF\GFFormGenerator;
use SmartAlloc\Tests\BaseTestCase;

final class GFFormGeneratorTest extends BaseTestCase
{
    public function test_build_json_contains_required_fields(): void
    {
        $json = GFFormGenerator::buildJson();
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $form = $data['forms'][0];
        $labels = array_column($form['fields'], 'adminLabel');
        $this->assertContains('mobile', $labels);
        $this->assertContains('national_id', $labels);
    }
}
