<?php

namespace Tests\Unit;

use Tests\Support\UnitTester;

require_once __DIR__ . '/../../include/CommonUtils.class.php';

/**
 * Tests for the CommonUtils class.
 */
class CommonUtilsTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    /**
     * Get a list of the image base URLs.
     */
    public function testGetImagesBaseURL()
    {
        $expected = [
            'original' => 'https://cdn.thegamesdb.net/images/original/',
            'small' => 'https://cdn.thegamesdb.net/images/small/',
            'thumb' => 'https://cdn.thegamesdb.net/images/thumb/',
            'cropped_center_thumb' => 'https://cdn.thegamesdb.net/images/cropped_center_thumb/',
            'medium' => 'https://cdn.thegamesdb.net/images/medium/',
            'large' => 'https://cdn.thegamesdb.net/images/large/',
        ];
        $result = \CommonUtils::getImagesBaseURL();

        $this->assertEquals($expected, $result);
    }
}
