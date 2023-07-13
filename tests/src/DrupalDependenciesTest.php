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
     * @covers ::dependentsOfModule
     */
    public function testModuleDependentOfModule(): void
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

        // Cleanup
        $this->drush('entity:delete', ['taxonomy_term']);
        $this->drush('pmu', ['node,forum,taxonomy,history']);
    }

    /**
     * @covers ::validateDependentsOfModule
     */
    public function testOptionsMismatch(): void
    {
        $this->drush('why:module', ['node'], [], null, null, 1);
        $this->assertStringContainsString("The --type option is mandatory", $this->getErrorOutput());

        $this->drush('why:module', ['node'], ['type' => 'wrong'], null, null, 1);
        $this->assertStringContainsString("The --type option can take only 'module' or 'config' as value", $this->getErrorOutput());

        $this->drush('why:module', ['node'], ['type' => 'config', 'no-only-installed' => null], null, null, 1);
        $this->assertStringContainsString("Cannot use --type=config together with --no-only-installed", $this->getErrorOutput());
    }

    /**
     * @covers ::dependentsOfModule
     */
    public function testConfigDependentOfModule(): void
    {
        // Trying to check an uninstalled module.
        $this->drush('why:module', ['node'], ['type' => 'config'], null, null, 1);
        $this->assertStringContainsString('Invalid node module', $this->getErrorOutput());

        // Install node module.
        $this->drush('pm:install', ['node']);

        // No installed dependencies.
        $this->drush('why:module', ['node'], ['type' => 'config']);
        $expected = <<<EXPECTED
            node
            ├─field.storage.node.body
            ├─system.action.node_delete_action
            ├─system.action.node_make_sticky_action
            ├─system.action.node_make_unsticky_action
            ├─system.action.node_promote_action
            ├─system.action.node_publish_action
            ├─system.action.node_save_action
            ├─system.action.node_unpromote_action
            ├─system.action.node_unpublish_action
            ├─core.entity_view_mode.node.full
            ├─core.entity_view_mode.node.rss
            ├─core.entity_view_mode.node.search_index
            ├─core.entity_view_mode.node.search_result
            └─core.entity_view_mode.node.teaser
            EXPECTED;
        $this->assertStringContainsString($expected, $this->getOutput());

        $this->drush('pm:install', ['forum']);
        $this->drush('wm', ['node'], ['type' => 'config']);

        $expected = <<<EXPECTED
            node
            ├─field.storage.node.body
            │ └─field.field.node.forum.body
            │   ├─core.entity_view_display.node.forum.default
            │   ├─core.entity_view_display.node.forum.teaser
            │   └─core.entity_form_display.node.forum.default
            ├─field.storage.node.comment_forum
            │ └─field.field.node.forum.comment_forum
            │   ├─core.entity_view_display.node.forum.default
            │   ├─core.entity_view_display.node.forum.teaser
            │   └─core.entity_form_display.node.forum.default
            ├─field.storage.node.taxonomy_forums
            │ └─field.field.node.forum.taxonomy_forums
            │   ├─core.entity_view_display.node.forum.default
            │   ├─core.entity_view_display.node.forum.teaser
            │   └─core.entity_form_display.node.forum.default
            ├─system.action.node_delete_action
            ├─system.action.node_make_sticky_action
            ├─system.action.node_make_unsticky_action
            ├─system.action.node_promote_action
            ├─system.action.node_publish_action
            ├─system.action.node_save_action
            ├─system.action.node_unpromote_action
            ├─system.action.node_unpublish_action
            ├─core.entity_view_mode.node.full
            ├─core.entity_view_mode.node.rss
            ├─core.entity_view_mode.node.search_index
            ├─core.entity_view_mode.node.search_result
            └─core.entity_view_mode.node.teaser
              └─core.entity_view_display.node.forum.teaser
            EXPECTED;
        $this->assertStringContainsString($expected, $this->getOutput());

        // Cleanup.
        $this->drush('entity:delete', ['taxonomy_term']);
        $this->drush('pmu', ['node,forum,taxonomy,history']);
    }
}
