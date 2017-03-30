<?php

namespace Yarak\tests\functional;

use Phalcon\DI;
use Yarak\Yarak;

class MakeSeederTest extends \Codeception\Test\Unit
{
    /**
     * Setup the class.
     */
    public function setUp()
    {
        parent::setUp();

        $this->tester->setUp();
    }

    /**
     * @test
     */
    public function it_makes_a_seeder()
    {
        $config = $this->tester->getConfig();

        Yarak::call('make:seeder', [
            'name' => 'PostsTableSeeder'
        ], DI::getDefault());

        $path = $config->getSeedDirectory('PostsTableSeeder.php');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertContains('class PostsTableSeeder', $contents);
    }
}
