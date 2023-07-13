<?php

namespace Drupal\Dependencies\Tests;

use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\Dependencies\Drush\Commands\DrupalDependenciesDrushCommands
 */
class DrupalDependenciesTest extends TestCase
{
    use DrushTestTrait;

    /**
     * @covers ::why
     */
    public function testWhyModuleCommand(): void
    {
        $this->drush('list');
        $this->assertStringContainsString('why:module (wm)', $this->getOutput());
        $this->assertStringContainsString('List all objects (modules, configurations)', $this->getOutput());
        $this->assertStringContainsString('depending on a given module', $this->getOutput());

        // Trying to check an uninstalled module.
        $this->drush('why:module', ['node'], ['type' => 'module'], null, null, 1);
        $this->assertStringContainsString('Invalid node module', $this->getErrorOutput());

        // Check also uninstalled modules.
        $this->drush('wm', ['node'], ['type' => 'module', 'no-only-installed' => null]);
        $expected = <<<EXPECTED
            node
            ├─book
            ├─forum
            ├─history
            │ └─forum
            ├─statistics
            ├─taxonomy
            │ └─forum
            └─tracker
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());

        // Install node module.
        $this->drush('pm:install', ['node']);

        // No installed dependencies.
        $this->drush('why:module', ['node'], ['type' => 'module']);
        $this->assertSame('[notice] No other module depends on node', $this->getErrorOutput());

        $this->drush('pm:install', ['forum']);
        $this->drush('wm', ['node'], ['type' => 'module']);
        $expected = <<<EXPECTED
            node
            ├─forum
            ├─history
            │ └─forum
            └─taxonomy
              └─forum
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown(): void
    {
        $this->drush('entity:delete', ['taxonomy_term']);
        $this->drush('pmu', ['node,forum,taxonomy,history']);
        parent::tearDown();
    }
}
