=== VIP Twig ===
Contributors: westonruter, xwp
Tags: twig, templates
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Proposal to make Twig conform for use on WordPress.com VIP and other production environments where runtime compilation of templates is not allowed.

== Description ==

This plugin is an attempt to force Twig to work in a production environment where
runtime compilation of Twig templates into PHP class files is not allowed. This
is true on WordPress.com VIP and should also be true on other production environments
where filesystem write access is disabled (see also `DISALLOW_FILE_MODS`).

**This plugin is a proposal and has not been approved by WordPress.com VIP.**

This plugin does not currently provide any WordPress-specific extensions to Twig,
such as those found in [Timber](http://upstatement.com/timber/); this plugin
could provide the Twig backend to Timber.

This plugin provides a global function `vip_twig_environment()` which can be used
to access the plugin's instance of `Twig_Environment` which is available once
`after_setup_theme` has fired. In other words, you can render a template via:

```php
echo vip_twig_environment()->render( 'views/index.html.twig' ); // xss ok
```

This plugin uses subclasses of `Twig_Environment` and `Twig_Loader_Filesystem`
which have overridden methods to prevent any attempt to compile Twig templates
on the fly when on WordPress.com VIP or if `DISALLOW_FILE_MODS` is true and
`WP_DEBUG` is not.

When on a local dev environment where runtime compilation
is allowed, the Twig templates will by default get written out to
a `{stylesheet_directory}/twig-cache`.

The cached PHP files compiled from Twig templates get corresponding names, as opposed
to cache files being named with an opaque SHA256 hash.
For instance `components/common/primary-navigation.html.twig` gets compiled to
`{cache_dir}/components/common/primary-navigation.html.twig.57ce91.php`.
In the same vein, this plugin overrides `Twig_Environment::getTemplateClass()`
to generate PHP class names correspond the Twig template name used,
e.g. `__TwigTemplate_components_common_primary_navigation_html_twig_57ce91`

When wanting to do a deployment to production, this plugin provides a WP-CLI
command `wp vip-twig compile` to walk over all Twig templates and compile them
into their corresponding PHP cache files at once so that they can be committed.
As such, a great `pre-commit` hook for a theme using this plugin would be:
`wp vip-twig compile && git add -A twig-cache`

By default the Twig loader will look for templates in the child theme's
directory (stylesheet directory), and the parent theme's directory
(template directory). You may override this behavior or add more Twig template
paths to search via the config filter, for instance within the context of a plugin:

```php
add_filter( 'vip_twig_config', function ( $config ) {
	array_unshift( $config['loader_template_paths'], plugin_dir_path( __FILE__ ) );
	return $config;
});
```

This plugin also enforces that Twig templates only be loaded from the stylesheet
directory, the template directory, the VIP shared plugins directory, or from an
organization's specific plugins folder. This prevents including templates from
other clients' codebases.

In the context of WordPress.com VIP, the intention is that the PHP files generated
from Twig templates would get committed to SVN and code reviewed like any other
code being submitted to VIP.

PHP 5.3+ is required.

**Development of this plugin is done [on GitHub](https://github.com/xwp/wp-vip-twig).
Pull requests welcome. Please see any [issues](https://github.com/xwp/wp-vip-twig/issues) reported.**

== Changelog ==

= 0.1 =
* Initial release
