[![ci](https://github.com/claudiu-cristea/drupal-dependencies/actions/workflows/ci.yml/badge.svg)](https://github.com/claudiu-cristea/drupal-dependencies/actions/workflows/ci.yml)

## Description

Provides Drush commands showing the tree of dependencies between Drupal objects,
such as modules or configuration entities. Useful to understand the dependency
chain in a Drupal installation.

## Commands

* `why:module`: List all modules that depend on a given module

## Usage example

### Include only installed modules

```bash
./vendor/bin/drush why:module node
```

will output

```
node
├─forum
├─history
│ └─forum
└─taxonomy
  └─forum
```

### Include uninstalled modules

```bash
./vendor/bin/drush why:module node --no-only-installed
```

will output

```
node
├─book
├─forum
├─history
│ └─forum
├─statistics
├─taxonomy
│ └─forum
└─tracker
```
