## About
Helper class meant to be used in WP plugins to inject template-parts & page-templates in the currenct active theme

## Usage
in the plugin directory create the following dirs:
* template-pages
* template-parts

in the main plugin file initialize the class:

```php
new WpPageTemplateLoader(__FILE__,$aOpts);
```

available `$aOpts` options:
* `filter_prefix`: define a custom prefix for filter & action hooks, defaults to `$sPluginRootFile`
* `theme_template_directory`: define a folder name inside the current theme to scan for template-parts, default to ``none``
* `plugin_template_pages_directory`: define the folder name inside the plugin where page-templates are stored, defaults to `template-pages`
* `plugin_template_parts_directory`: define the folder name inside the plugin where template-parts are stored, defaults to `template-parts`

## install using composer:
``composer require alexrah/wp-admin-custom-post-types``

published on packagist at: [https://packagist.org/packages/alexrah/wp-admin-custom-post-types](https://packagist.org/packages/alexrah/wp-admin-custom-post-types)

## Changelog

### version 1.0.0
* helper classes to inject template-parts & page-templates
