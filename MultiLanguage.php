<?php

/**
 * Pico MultiLanguage plugin - multi-language support for Pico CMS (https://picocms.org/)
 *
 * @author  Til Boerner <tilmanboerner@gmx.net>, Richard Martin-Nielsen <richard.martin@utoronto.ca>, Iridescent <veronique@iridescent.dev>
 * @link    https://github.com/tilboerner/pico_languages
 * @link    https://github.com/RichardMN/pico_multilanguage
 * @link    https://github.com/iridescent-dev/pico-multilanguage-plugin
 * @license http://opensource.org/licenses/MIT The MIT License
 * @copyright 2014 Til Boerner
 * @version 1.0
 */
class MultiLanguage extends AbstractPicoPlugin
{
    /**
     * This plugin is enabled by default
     *
     * @see AbstractPicoPlugin::$enabled
     * @var boolean
     */
    protected $enabled = true;

    // The default language
    private $default_language = 'en';

    // The list of available languages and their display names
    private $languages = array('en' => 'English');

    // The list of available language codes
    private $available_languages = array();

    // The path to your language directory
    private $language_dir = 'language/';

    // The list of translated titles of your website
    private $site_titles = array();

    // The list of date formats by languages
    private $date_formats = array();

    // The list of date locales by languages
    private $date_locales = array();

    // Array of page data grouped by language
    private $pages_by_language = array();

    // Array of page data grouped by id
    private $pages_by_id = array();

    // The current language
    private $current_language;

    /**
     * Triggered after Pico has read its configuration
     *
     * @see Pico::getConfig()
     * @see Pico::getBaseUrl()
     * @see Pico::isUrlRewritingEnabled()
     *
     * @param array &$config array of config variables
     */
    public function onConfigLoaded(array &$config)
    {
        if (isset($config['default_language'])) {
            $this->default_language = $config['default_language'];
        }

        // check languages
        if (isset($config['languages'])) {
            $this->languages = $config['languages'];
        }
        if (!is_array($this->languages)) {
            throw new RuntimeException('Invalid languages, "' . $this->languages . '" must be an array');
        }
        $this->available_languages = array_keys($this->languages);

        // check language dir
        if (isset($config['language_dir'])) {
            $this->language_dir = $config['language_dir'];
        }
        if (!is_dir($this->language_dir)) {
            throw new RuntimeException('Invalid language directory "' . $this->language_dir . '"');
        }
        $this->language_dir = $this->getPico()->getAbsolutePath($this->language_dir);

        // check site titles
        if (isset($config['site_titles'])) {
            $this->site_titles = $config['site_titles'];
        }
        if (!is_array($this->site_titles)) {
            throw new RuntimeException('Invalid site titles, "' . $this->site_titles . '" must be an array');
        }

        // check date formats
        if (isset($config['date_formats'])) {
            $this->date_formats = $config['date_formats'];
        }
        if (!is_array($this->date_formats)) {
            throw new RuntimeException('Invalid date formats, "' . $this->date_formats . '" must be an array');
        }

        // check date locales
        if (isset($config['date_locales'])) {
            $this->date_locales = $config['date_locales'];
        }
        if (!is_array($this->date_locales)) {
            throw new RuntimeException('Invalid date locales, "' . $this->date_locales . '" must be an array');
        }
    }

    /**
     * Triggered after Pico has evaluated the request URL
     *
     * @see Pico::getRequestUrl()
     *
     * @param string &$url part of the URL describing the requested contents
     */
    public function onRequestUrl(&$url)
    {
        $content_dir = $this->getPico()->getConfig('content_dir');
        $content_ext = $this->getPico()->getConfig('content_ext');

        // Checks if an index page exists at the root of the
        // content_dir when the url is the base_url
        if ($url == '' && !is_file($content_dir . '/index' . $content_ext)) {
            // Redirects to the index page of the language
            $language = $this->getBrowserLanguage();
            if (is_file($content_dir . $language . '/index' . $content_ext)) {
                $url = $language;
            }
        }
    }

    /**
     * Triggered after Pico has read the contents of the 404 file
     *
     * @see DummyPlugin::on404ContentLoading()
     * @see Pico::getRawContent()
     * @see Pico::is404Content()
     *
     * @param string &$rawContent raw file contents
     */
    public function on404ContentLoaded(&$rawContent)
    {
        $content_dir = $this->getPico()->getConfig('content_dir');
        $content_dir_length = strlen($content_dir);
        $content_ext = $this->getPico()->getConfig('content_ext');

        $file = $this->getPico()->getRequestFile();
        $file_length = strlen($file);
        $file_path = explode('/', substr($file, $content_dir_length, $file_length));

        // Checks if a 404 page exists at the root of the
        // content_dir when the file path does not use a language
        if (!in_array($file_path[0], $this->available_languages) && !is_file($content_dir . '/404' . $content_ext)) {
            // Load the contents of the 404 file for the language
            $language = $this->getBrowserLanguage();
            if (is_file($content_dir . $language . '/404' . $content_ext)) {
                $rawContent = $this->getPico()->loadFileContent($content_dir . $language . '/404' . $content_ext);
            }
        }
    }

    /**
     * Triggered when Pico reads its known meta header fields
     *
     * @see Pico::getMetaHeaders()
     *
     * @param string[] &$headers list of known meta header fields; the array
     *     key specifies the YAML key to search for, the array value is later
     *     used to access the found value
     */
    public function onMetaHeaders(array &$headers)
    {
        $headers['language'] = 'Language';
        $headers['pid'] = 'pid';
    }

    /**
     * Triggered after Pico has parsed the meta header
     *
     * @see DummyPlugin::onMetaParsing()
     * @see Pico::getFileMeta()
     *
     * @param string[] &$meta parsed meta data
     */
    public function onMetaParsed(array &$meta)
    {
        // Checks if the language of the page is set and that it is available for the site
        if (!$meta['language'] || !in_array($meta['language'], $this->available_languages)) {
            $meta['language'] = $this->default_language;
        }
        $this->current_language = $meta['language'];
    }

    /**
     * Triggered after Pico has prepared the raw file contents for parsing
     *
     * @see DummyPlugin::onContentParsing()
     * @see Pico::parseFileContent()
     * @see DummyPlugin::onContentParsed()
     *
     * @param string &$markdown Markdown contents of the requested page
     */
    public function onContentPrepared(&$markdown)
    {
        $variables = array();

        // replace %language_base_url%
        $variables['%language_base_url%'] = rtrim($this->getLanguageBaseUrl(), '/');

        $markdown = str_replace(array_keys($variables), $variables, $markdown);
    }

    /**
     * Triggered when Pico reads a single page from the list of all known pages
     *
     * The `$pageData` parameter consists of the following values:
     *
     * | Array key      | Type   | Description                              |
     * | -------------- | ------ | ---------------------------------------- |
     * | id             | string | relative path to the content file        |
     * | url            | string | URL to the page                          |
     * | title          | string | title of the page (YAML header)          |
     * | description    | string | description of the page (YAML header)    |
     * | author         | string | author of the page (YAML header)         |
     * | time           | string | timestamp derived from the Date header   |
     * | date           | string | date of the page (YAML header)           |
     * | date_formatted | string | formatted date of the page               |
     * | raw_content    | string | raw, not yet parsed contents of the page |
     * | meta           | string | parsed meta data of the page             |
     *
     * @see DummyPlugin::onSinglePageLoading()
     * @see DummyPlugin::onSinglePageContent()
     *
     * @param array &$pageData data of the loaded page
     */
    public function onSinglePageLoaded(array &$pageData)
    {
        $language = $pageData['meta']['language'] ?? $this->default_language;
        $page_id = $pageData['meta']['pid'];

        // set page.language, page.is_current_language and page.id
        $pageData['language'] = $language;
        $pageData['is_current_language'] = $language === $this->current_language;
        $pageData['pid'] = $page_id;

        // edit page.date_formatted
        $pageData['date_formatted'] = $this->localizeAndFormatDate($pageData['date']);

        // add page to languages[$lang]
        if (!isset($this->pages_by_language[$language])) {
            $this->pages_by_language[$language] = array();
        }
        $this->pages_by_language[$language][] = $pageData;

        // add pages with Id to page_languages[$pid][$lang]
        if ($page_id) {
            if (!isset($this->pages_by_id[$page_id])) {
                $this->pages_by_id[$page_id] = array();
            }
            $this->pages_by_id[$page_id][$language] = $pageData;
        }
    }

    /**
     * Triggered after Pico has sorted the pages array
     *
     * Please refer to {@see Pico::readPages()} for information about the
     * structure of Pico's pages array and the structure of a single page's
     * data.
     *
     * @see DummyPlugin::onPagesLoading()
     * @see DummyPlugin::onPagesDiscovered()
     * @see Pico::getPages()
     *
     * @param array[] &$pages sorted list of all known pages
     */
    public function onPagesLoaded(array &$pages)
    {
        // only keep pages with same language as current
        $pages = array_filter($pages, function ($page) {
            return $page['is_current_language'];
        });
    }

    /**
     * Triggered before Pico renders the page
     *
     * @see DummyPlugin::onPageRendered()
     *
     * @param string &$templateName  file name of the template
     * @param array  &$twigVariables template variables
     */
    public function onPageRendering(&$templateName, array &$twigVariables)
    {
        $twigVariables['languages'] = $this->languages;

        $page_id = $twigVariables['meta']['pid'];
        $twigVariables['page_languages'] = $this->pages_by_id[$page_id] ?? array();

        if (isset($this->site_titles[$this->current_language]) && !empty($this->site_titles[$this->current_language])) {
            $twigVariables['site_title'] = $this->site_titles[$this->current_language];
        }

        $twigVariables['current_language'] = $this->current_language;
        $twigVariables['language_base_url'] = rtrim($this->getLanguageBaseUrl(), '/');
    }

    /**
     * Returns the translated and formatted string for the key passed as a parameter.
     * Search in the file corresponding to the current language in the language directory.
     * (e.g. `language/en.php`)
     *
     * @see https://php.net/manual/en/function.sprintf.php
     * @param  string $key
     * @param  array  $args
     * @return string translated string
     */
    public function translate($key)
    {
        $lang_array = include $this->language_dir . $this->current_language . '.php';

        if (!isset($lang_array[$key]) || empty($lang_array[$key])) {
            return $key;
        }

        $translated_value = $lang_array[$key];
        $args = func_get_args();
        if (count($args) > 1) {
            // Replace $key by $translated_value in $args array
            $args[0] = $translated_value;
            // Format the string
            $translated_value = call_user_func_array("sprintf", $args);
        }

        return $translated_value;
    }

    /**
     * Registers Twig filters.
     */
    public function onTwigRegistration()
    {
        $this->getPico()->getTwig()->addFilter(new \Twig\TwigFilter('translate', [$this, 'translate']));
    }

    /**
     * Returns the browser language if available in the languages array, or the default language.
     *
     * @return string
     */
    private function getBrowserLanguage()
    {
        $browser_language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        return in_array($browser_language, $this->available_languages) ? $browser_language : $this->default_language;
    }

    /**
     * Returns the Base URL for the current language.
     * Is equivalent to `{{ base_url }}/{{ current_language }}` and
     * `{{ base_url }}?{{ current_language }}`, depending on enabled URL rewriting.
     *
     * @return string the language_base_url
     */
    private function getLanguageBaseUrl()
    {
        return $this->getPico()->getBaseUrl()
        . (!$this->getPico()->isUrlRewritingEnabled() ? '?' : '')
        . $this->current_language;
    }

    /**
     * Returns the localized and formatted date.
     *
     * @see https://php.net/manual/en/function.strftime.php
     * @param  string $date
     * @return string date formatted and localized
     */
    private function localizeAndFormatDate($date)
    {
        setlocale(LC_TIME, ''); // reset the locale
        $locale = $this->date_locales[$this->current_language] ?? $this->current_language;
        setlocale(LC_TIME, $locale); // set the current locale, stay on the default value if it does not exist

        $date_format = $this->date_formats[$this->current_language] ?? $this->getPico()->getConfig('date_format');
        $formatted_date = utf8_encode(strftime($date_format, strtotime($date)));

        setlocale(LC_TIME, ''); // reset the locale

        return $formatted_date;
    }
}
