[![ci](https://github.com/claudiu-cristea/drupal-dependencies/actions/workflows/ci.yml/badge.svg)](https://github.com/claudiu-cristea/drupal-dependencies/actions/workflows/ci.yml)

## Description

Provides Drush commands showing the tree of dependencies between Drupal objects,
such as modules or configuration entities. Useful to understand the dependency
chain in a Drupal installation.

## Use cases

### Get all installed modules requiring a given module

```bash
./vendor/bin/drush why:module --dependent-type=module
node
├─forum
├─history
│ └─forum
└─taxonomy
└─forum
```

###  Get all modules requiring a given module (installed o not)

```bash
./vendor/bin/drush why:module node --dependent-type=module -no-only-installed
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

### Get all config entities requiring a given module

```bash
./vendor/bin/drush why:module --dependent-type=config
node
├─core.entity_view_mode.node.full
├─core.entity_view_mode.node.rss
├─core.entity_view_mode.node.search_index
├─core.entity_view_mode.node.search_result
├─core.entity_view_mode.node.teaser
│ └─core.entity_view_display.node.forum.teaser
├─field.storage.node.body
│ └─field.field.node.forum.body
│   ├─core.entity_form_display.node.forum.default
│   ├─core.entity_view_display.node.forum.default
│   └─core.entity_view_display.node.forum.teaser
├─field.storage.node.comment_forum
│ └─field.field.node.forum.comment_forum
│   ├─core.entity_form_display.node.forum.default
│   ├─core.entity_view_display.node.forum.default
│   └─core.entity_view_display.node.forum.teaser
├─field.storage.node.taxonomy_forums
│ └─field.field.node.forum.taxonomy_forums
│   ├─core.entity_form_display.node.forum.default
│   ├─core.entity_view_display.node.forum.default
│   └─core.entity_view_display.node.forum.teaser
├─system.action.node_delete_action
├─system.action.node_make_sticky_action
├─system.action.node_make_unsticky_action
├─system.action.node_promote_action
├─system.action.node_publish_action
├─system.action.node_save_action
├─system.action.node_unpromote_action
└─system.action.node_unpublish_action
```

## Author

Claudiu Cristea | https://www.drupal.org/u/claudiucristea
