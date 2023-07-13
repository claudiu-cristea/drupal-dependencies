<?php

declare(strict_types=1);

namespace Drupal\Dependencies\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\Dependency;
use Drupal\Core\Extension\Extension;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drush commands revealing Drupal dependencies.
 */
class DrupalDependenciesDrushCommands extends DrushCommands
{
    private const CIRCULAR_REFERENCE = 'circular_reference';
    private array $dependents = [];
    private array $tree = [];
    private array $relation = [];
    private array $canvas = [];
    private array $dependencies = [
      'module-module' => [],
      'config-module' => [],
      'config-config' => [],
    ];

    #[CLI\Command(name: 'why:module', aliases: ['wm'])]
    #[CLI\Help(description: 'List all objects (modules, configurations) depending on a given module')]
    #[CLI\Argument(name: 'module', description: 'The module to check dependencies for')]
    #[CLI\Option(
        name: 'type',
        description: 'Type of dependents: module, config',
        suggestedValues: ['module', 'config']
    )]
    #[CLI\Option(name: 'only-installed', description: 'Only check for installed modules')]
    #[CLI\Usage(
        name: 'drush why:module node --type=module',
        description: 'Show all installed modules depending on node module'
    )]
    #[CLI\Usage(
        name: 'drush why:module node --type=module --no-only-installed',
        description: 'Show all modules, including uninstalled, depending on node module'
    )]
    #[CLI\Usage(
        name: 'drush why:module node --type=config',
        description: 'Show all configuration entities depending on node module'
    )]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function dependentsOfModule(string $module, array $options = [
        'type' => InputOption::VALUE_REQUIRED,
        'only-installed' => true,
    ]): ?string
    {
        if ($options['type'] === 'module') {
            $this->buildDependents($this->dependencies['module-module']);
        } else {
            $this->scanConfigs();
            $this->buildDependents($this->dependencies['config-module']);
            $this->buildDependents($this->dependencies['config-config']);
        }

        if (!isset($this->dependents[$module])) {
            $this->logger()->notice(dt('No other module depends on @module', [
                '@module' => $module,
            ]));
            return null;
        }
        $this->canvas[] = $module;
        $this->buildTree($module);


        return implode("\n", $this->canvas);
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'why:module')]
    public function validateDependentsOfModule(CommandData $commandData): void
    {
        $type = $commandData->input()->getOption('type');
        if (empty($type)) {
            throw new \InvalidArgumentException("The --type option is mandatory");
        }
        if (!in_array($type, ['module', 'config'], true)) {
            throw new \InvalidArgumentException("The --type option can take only 'module' or 'config' as value");
        }

        $notOnlyInstalled = $commandData->input()->getOption('no-only-installed');
        if ($notOnlyInstalled && $type === 'config') {
            throw new \InvalidArgumentException("Cannot use --type=config together with --no-only-installed");
        }

        $installedModules = \Drupal::getContainer()->getParameter('container.modules');
        $module = $commandData->input()->getArgument('module');
        if ($type === 'module') {
            $this->dependencies['module-module'] = array_map(function (Extension $extension): array {
                return array_map(function (string $dependencyString) {
                    return Dependency::createFromString($dependencyString)->getName();
                }, $extension->info['dependencies']);
            }, \Drupal::service('extension.list.module')->getList());

            if (!$notOnlyInstalled) {
                $this->dependencies['module-module'] = array_intersect_key($this->dependencies['module-module'], $installedModules);
            }
            if (!isset($this->dependencies['module-module'][$module])) {
                throw new \InvalidArgumentException(dt('Invalid @module module', [
                    '@module' => $module,
                ]));
            }
        }
        elseif (!isset($installedModules[$module])) {
            throw new \InvalidArgumentException(dt('Invalid @module module', [
                '@module' => $module,
            ]));
        }
    }

    /**
     * @param string $dependency
     * @param array $path
     * @param string $indent
     */
    protected function buildTree(string $dependency, array $path = [], string $indent = ''): void
    {
        $path[] = $dependency;
        $dependents = $this->dependents[$dependency];
        foreach (array_keys($dependents) as $delta => $dependent) {
            $lastFromThisLevel = $delta + 1 < count($dependents);
            $char = $lastFromThisLevel ? '├' : '└';
            $stroke = $indent . "{$char}─" . $dependent;
            if (!NestedArray::keyExists($this->tree, $path)) {
                NestedArray::setValue($this->tree, $path, []);
            }

            $circularReference = isset($this->relation[$dependency]) && $this->relation[$dependency] === $dependent;
            if ($circularReference) {
                // This relation has been already defined on other path. We mark
                // it as circular reference.
                NestedArray::setValue($this->tree, [...$path, ...[$dependent]], self::CIRCULAR_REFERENCE);
                $stroke .= ' (' . dt('CIRCULAR') . ')';
            }

            // Draw a new line to the canvas.
            $this->canvas[] = $stroke;

            if ($circularReference) {
                continue;
            }

            // Save this relation to avoid infinite circular references.
            $this->relation[$dependency] = $dependent;
            if (isset($this->dependents[$dependent])) {
                $char = $lastFromThisLevel ? '│' : ' ';
                $this->buildTree($dependent, $path, $indent . "$char ");
            } else {
                NestedArray::setValue($this->tree, [...$path, ...[$dependent]], []);
            }
        }
    }

    /**
     * @param array $list
     */
    protected function buildDependents(array $list): void
    {
        foreach ($list as $dependent => $dependencies) {
            foreach ($dependencies as $dependency) {
                $this->dependents[$dependency][$dependent] = $dependent;
            }
        }
    }

    protected function scanConfigs(): void
    {
        $entityTypeManager = \Drupal::entityTypeManager();
        $configTypeIds = array_keys(
            array_filter($entityTypeManager->getDefinitions(), function (EntityTypeInterface $entityType): bool {
                return $entityType->entityClassImplements(ConfigEntityInterface::class);
            })
        );
        foreach ($configTypeIds as $configTypeId) {
            /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $config */
            foreach ($entityTypeManager->getStorage($configTypeId)->loadMultiple() as $config) {
                $dependencies = $config->getDependencies();
                $name = $config->getConfigDependencyName();
                if (!empty($dependencies['module'])) {
                    $this->dependencies['config-module'][$name] = $dependencies['module'];
                }
                if (!empty($dependencies['config'])) {
                    $this->dependencies['config-config'][$name] = $dependencies['config'];
                }
            }
        }
    }
}
