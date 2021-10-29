Pico MultiLanguage <!-- omit in toc -->
==================

[Pico][1] plugin for multi-language support.

- [Usage](#usage)
- [Getting Started](#getting-started)
  - [Install](#install)
    - [Integration with other plugins](#integration-with-other-plugins)
  - [Configuration settings](#configuration-settings)
  - [Content translation](#content-translation)
  - [Template variables defined for Twig](#template-variables-defined-for-twig)
  - [Theme translation](#theme-translation)
  - [Changing the site title automatically](#changing-the-site-title-automatically)
  - [Changing the page date formatted automatically](#changing-the-page-date-formatted-automatically)
- [Changelog](#changelog)
  - [1.0](#10)
- [License](#license)


# Usage

A plugin to support content and theme in different languages.

You can define the languages available for your site and translate your content pages and your theme into those languages.

---

**NOTES:**

* You will need to use **the same language codes** wherever you need them.
* An **IETF language tag** like `en` is suggested.
* In all our examples we use the languages `en` and `fr`.

---


# Getting Started

## Install

Add the plugin in the `plugins` directory of your Pico installation (e.g. `/var/www/html/pico/plugins/`)
* using [Composer](https://getcomposer.org/) `composer require iridescent-dev/pico-multilanguage-plugin`
* or manually by uploading the `MultiLanguage.php` file to your `plugins` directory

Pico MultiLanguage plugin requires PHP >=7.0.

### Integration with other plugins

The MultiLanguage plugin filters the list of pages to keep only pages in the current language. If you are using a plugin which processes the list of pages, it may be necessary to add MultiLanguage to it as a dependency. <br/>
Take the example of [Pagination](https://github.com/rewdy/Pico-Pagination): filtering pages by language must be done before paging.

You must add or edit the following line in this plugin:
``` php
class Pagination extends AbstractPicoPlugin {
	protected $dependsOn = array("MultiLanguage");
  ...
```


## Configuration settings

You can change the default configuration by adding values to your `config` file. Here are the options available and what they do.

* `default_language` - The default language. - *Default value: en*.
* `languages` - The list of available languages and their display names. - *Default value: 'en' => 'English'*.
* `language_dir` - The path to the language directory. - *Default value: language/* (e.g. `/var/www/html/pico/language/`). <br/>
  See [Theme translation](#theme-translation).
* `site_titles` - The list of translated titles of your website. - *Default value: (unset)*. <br/>
  See [Changing the site title automatically](#changing-the-site-title-automatically).
* `date_formats` - The list of date formats by language. - *Default value: (unset)*. <br/>
  See [Changing the page date formatted automatically](#changing-the-page-date-formatted-automatically).
* `date_locales` - The list of date locales by language. - *Default value: (unset)*. <br/>
  See [Changing the page date formatted automatically](#changing-the-page-date-formatted-automatically).

For reference, these values are set in `config/config.yml` using the following format:
``` yml
##
# MultiLanguage support
#
default_language: en                # The default language.

languages:                          # The list of available languages and their display names.
    en: English
    fr: Français

language_dir: language/             # The path to the language directory.

site_titles:                        # The list of translated titles of your website.
    en: Website title               #   English title of your website
    fr: Titre du site web           #   French title of your website

date_formats:                       # The list of date formats by language.
                                    #     See https://php.net/manual/en/function.strftime.php for more info
    en: %B %d, %Y                   #   e.g. september 23, 2021
    fr: %d %B %Y                    #   e.g. 23 septembre 2021

date_locales:                       # The list of date locales by language.
                                    #     See https://php.net/manual/en/function.setlocale.php for more info
    en: en_US.UTF-8
    fr: fr_FR.UTF-8
```


## Content translation

The plugin introduces `Language` and `pid` page headers. Essentially, each language version gets a separate content file with a different `Language` but the same `pid`.

* `Language` is the language of the page. A page without a language header gets assigned the `default_language`.

* `pid` identifies the same page (content-wise) across different languages, making it possible to find a different language version of a certain page. Same content, different language -> same pid.

You can organize your `content` directory with one folder per language, then you can use the `language_base_url` variable instead of `base_url`. See [Template variables defined for Twig](#template-variables-defined-for-twig).

Each language folder can contain `index.md` and `404.md` files. These files must have the corresponding `Language` header and the same `pid`. All Markdown files can be placed in the folder corresponding to their language.

The structure should be as follows:
```
content
  └───en
  │   │   index.md
  │   │   404.md
  │   └   ...
  └───fr
      │   index.md
      │   404.md
      └   ...
```

Sample of `content/en/index.md`
``` html
---
Title: Welcome
Description: Pico is a stupidly simple, blazing fast, flat file CMS.
Language: en
pid: home
---
```

Sample of `content/fr/index.md`
``` html
---
Title: Bienvenue
Description: Pico est un CMS de fichier à plat stupidement simple et ultra rapide.
Language: fr
pid: home
---
```

Below we've shown some examples of locations and their corresponding URLs:
| URL              | Physical Location                                                                |
| ---------------- | -------------------------------------------------------------------------------- |
| **/**            | `content/index.md` if the file exists or `content/`**`[lang]`**`/index.md` __*__ |
| **/en**          | `content/en/index.md`                                                            |
| **/fr**          | `content/fr/index.md`                                                            |
| **/fr/sub/page** | `content/fr/sub/page.md`                                                         |
| **/badurl**      | `content/404.md` if the file exists or `content/`**`[lang]`**`/404.md` __*__     |
| **/en/badurl**   | `content/en/404.md`                                                              |

__*__ To replace **`[lang]`**, the plugin uses the browser language if it is one of the available `languages`, otherwise the `default_language` is used.


## Template variables defined for Twig

* `{{ languages }}`<br/>
      array of all available languages.
* `{{ page_languages }}`<br/>
      page data array of all different language versions of the current page, including the current page.<br/>
      For example, to access the page in english use `{{ page_languages['en'] }}`.
* `{{ current_language }}`<br/>
      the language of the current page.
* `{{ language_base_url }}`<br/>
      is equivalent to `{{ base_url }}/{{ current_language }}` and `{{ base_url }}?{{ current_language }}`, depending on enabled URL rewriting. See [https://picocms.org/docs/#url-rewriting](https://picocms.org/docs/#url-rewriting).<br/>
      In content files you can use the `%language_base_url%`.

All this can be used to build a language switcher:
``` twig
<ul>
 {% for language, display_name in languages %}
      {% if language == current_language %}
          <li>{{ display_name }}</li>
      {% else %}
          {% if page_languages[language] %}
              <li><a href="{{ page_languages[language].url }}">{{ display_name }}</a></li>
          {% else %}
              <li><a href="{{ language|link }}">{{ display_name }}</a></li>
          {% endif %}
      {% endif %}
 {% endfor %}
 </ul>
```

Here, we use `{{ language|link }}` because the homepage URL for each language matches the language code (e.g. `/en` or `/fr`). See [Content translation](#content-translation).


## Theme translation

This Twig filter can be used in your theme templates:
* `translate` - To get a translated and formatted string (e.g. `{{ 'my_key'|translate }}`).<br/>
      See [https://php.net/manual/en/function.sprintf.php](https://php.net/manual/en/function.sprintf.php).

You must add a `language` directory in your Pico installation, with one file per language. Each file must contain all the keys used by the `translate` Twig filter in your templates.

The structure should be as follows:
```
language
  │   en.php
  │   fr.php
```

Sample of `language/en.php`
``` php
<?php
return array(
    "my_key" => "my translated value",      // {{ 'my_key'|translate }}
    "posted_by" => "Posted by %s on %s",    // {{ 'posted_by'|translate(page.author, page.date_formatted) }}
);
```


## Changing the site title automatically

In addition to the regular `site_title` defined in `config.yml`, the MultiLanguage plugin can adjust the `{{ site_title }}` twig variable according to the language of the given page.

``` yml
##
# Basic
#
site_title: Pico                    # The title of your website
```

``` yml
##
# MultiLanguage support
#
site_titles:                        # The list of translated titles of your website.
    en: Website title               #   English title of your website
    fr: Titre du site web           #   French title of your website
```

This creates an array called `site_titles` (as opposed to the default `site_title`). `site_titles` uses the same language codes used within individual pages. If there is no entry given for a page's language, it will default to the original `site_title` variable set.


## Changing the page date formatted automatically

In addition to the regular `date_format` defined in `config.yml`, the MultiLanguage plugin can adjust the `{{ date_formatted }}` twig variable according to the language of the given page. You can define a date format and which locale (available on your server) you want to use for each language of the site.

``` yml
##
# Content
#
date_format: %D %T                  # Pico's default date format;
                                    #     See https://php.net/manual/en/function.strftime.php for more info
```

``` yml
##
# MultiLanguage support
#
date_formats:                       # The list of date formats by language.
                                    #     See https://php.net/manual/en/function.strftime.php for more info
    en: %B %d, %Y                   #   e.g. september 23, 2021
    fr: %d %B %Y                    #   e.g. 23 septembre 2021

date_locales:                       # The list of date locales by language.
                                    #     See https://php.net/manual/en/function.setlocale.php for more info
    en: en_US.UTF-8
    fr: fr_FR.UTF-8
```

This creates an array called `date_formats` (as opposed to the default `date_format`). `date_formats` uses the same language codes used within individual pages. If there is no entry given for a page's language, it will default to the original `date_format` variable set.

An other array called `date_locales` is created, which uses the same language codes used within individual pages. If there is no entry given for a page's language, the server locale will be used by default.


# Changelog
## 1.0
* Breaking changes
  * The language management has changed, you must indicate in your `config.yml` the available `languages`.
  * Template variables defined for Twig has changed:
    * `{{ languages }}` returns an array of all available `languages` defined in `config.yml`, instead of all strings defined in language headers.
    * `{{ page_languages }}` still returns the same, but the structure has changed. Now the array returns `key => value` pairs, where the key is the language of the page.

* New features
  * The concept of `language_base_url` which allows you to organize your `content` directory with one folder per language.
  * Theme translation.
  * Changing the page date formatted automatically.


# License
http://opensource.org/licenses/MIT

[1]: http://picocms.org/
