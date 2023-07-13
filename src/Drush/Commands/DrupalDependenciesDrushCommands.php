<?php

declare(strict_types=1);

namespace Drupal\Dependencies\Drush\Commands;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\Dependency;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

/**
 * Drush commands revealing Drupal dependencies.
 */
class DrupalDependenciesDrushCommands extends DrushCommands
{
    private const CIRCULAR_REFERENCE = 'circular_reference';
    private array $dependencies = [];
    private array $tree = [];
    private array $relation = [];
    private array $canvas = [];

    #[CLI\Command(name: 'why:module', aliases: ['wm'])]
    #[CLI\Help(description: 'List all modules that depend on a given module')]
    #[CLI\Argument(name: 'module', description: 'The module to check dependencies for')]
    #[CLI\Option(name: 'only-installed', description: 'Only check for installed modules')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function why(string $module, array $options = [
        'only-installed' => true,
    ]): ?string
    {
        $list = \Drupal::service('extension.list.module')->getList();
        if ($options['only-installed']) {
            $installed = \Drupal::getContainer()->getParameter('container.modules');
            $list = array_intersect_key($list, $installed);
        }
        if (!isset($list[$module])) {
            throw new \InvalidArgumentException(dt('Invalid @module module', [
                '@module' => $module,
            ]));
        }

        $this->buildDependencies($list);

        if (!isset($this->dependencies[$module])) {
            $this->logger()->notice(dt('No other module depends on @module', [
                '@module' => $module,
            ]));
            return null;
        }

        $this->canvas[] = $module;
        $this->buildTree($module);

        return implode("\n", $this->canvas);
    }

    /**
     * @param string $dependency
     * @param array $path
     * @param string $indent
     */
    protected function buildTree(string $dependency, array $path = [], string $indent = ''): void
    {
        $path[] = $dependency;
        $dependants = $this->dependencies[$dependency];
        foreach (array_keys($dependants) as $delta => $module) {
            $lastFromThisLevel = $delta + 1 < count($dependants);
            $char = $lastFromThisLevel ? '├' : '└';
            $stroke = $indent . "{$char}─" . $module;
            if (!NestedArray::keyExists($this->tree, $path)) {
                NestedArray::setValue($this->tree, $path, []);
            }

            $circularReference = isset($this->relation[$dependency]) && $this->relation[$dependency] === $module;
            if ($circularReference) {
                // This relation has been already defined on other path. We mark it as
                // circular reference.
                NestedArray::setValue($this->tree, [...$path, ...[$module]], self::CIRCULAR_REFERENCE);
                $stroke .= ' [' . dt('circular reference') . ']';
            }

            // Draw a new line to the canvas.
            $this->canvas[] = $stroke;

            if ($circularReference) {
                continue;
            }

            // Save this relation to avoid infinite circular references.
            $this->relation[$dependency] = $module;
            if (isset($this->dependencies[$module])) {
                $char = $lastFromThisLevel ? '│' : ' ';
                $this->buildTree($module, $path, $indent . "$char ");
            } else {
                NestedArray::setValue($this->tree, [...$path, ...[$module]], []);
            }
        }
    }

    /**
     * @param array $list
     */
    protected function buildDependencies(array $list): void
    {
        foreach ($list as $module => $data) {
            foreach ($data->info['dependencies'] as $dependencyString) {
                $dependency = Dependency::createFromString($dependencyString)->getName();
                $this->dependencies[$dependency][$module] = $module;
            }
        }
    }
}
