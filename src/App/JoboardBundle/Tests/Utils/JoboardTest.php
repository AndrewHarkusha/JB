<?php

namespace App\JoboardBundle\Tests\Utils;

use App\JoboardBundle\Utils\Joboard;

class JoboardTest extends \PHPUnit_Framework_TestCase
{
    public function testSlugify()
    {
        $this->assertEquals('company', Joboard::slugify('Company'));
        $this->assertEquals('ooo-company', Joboard::slugify('ooo company'));
        $this->assertEquals('company', Joboard::slugify(' company'));
        $this->assertEquals('company', Joboard::slugify('company '));
    }
}
