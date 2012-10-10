<?php
namespace Flatten;

use \Cache;
use \Event;

class Flatten
{
  /**
   * The current URL
   * @var string
   */
  private static $hash = null;

  /**
   * The current language
   * @var string
   */
  private static $lang = null;

  /**
   * Hook Flatten to Laravel's events
   *
   * @return boolean Whether Flatten started caching or not
   */
  public static function hook()
  {
    // Check if we're in an allowed environment
    $environments = (array) Config::get('environments');
    if(in_array(\Request::env(), $environments)) return false;

    // Set cache language
    preg_match_all("#^([a-z]{2})/.+#i", \URI::current(), $language);
    static::$lang = array_get($language, '1.0', Config::get('application.language'));

    // Get pages to cache
    $only    = Config::get('only');
    $ignored = Config::get('ignore');
    $cache   = false;

    // Pages to cache
    if($only and static::matches($only)) $cache = true;

    // Pages to ignore
    if($ignored and !static::matches($ignored)) $cache = true;

    // If normal case
    if(!$ignored and $only) $cache = true;

    if ($cache) {
      static::load();
      static::save();

      return true;
    }

    return false;
  }

  /**
   * Empties the pages in cache
   *
   * @return boolean Whether the operation succeeded or not
   */
  public static function flush($pattern = null)
  {
    $folder = path('storage').'cache'.DS.Config::get('folder');

    // Delete only certain files
    if($pattern) {
      $pattern = str_replace('/', '_', $pattern);
      $files = glob($folder.DS.'*'.$pattern.'*');
      foreach($files as $file) \File::delete($file);

      return sizeof($files);
    }

    return \File::cleandir($folder);
  }

  /**
   * Flush a specific action
   *
   * @param  string  $action     An action
   * @param  array   $parameters Parameters to pass it
   * @return boolean             Success or not
   */
  public static function flush_action($action, $parameters = array())
  {
    // Get matching URL
    $url = action($action, $parameters);
    $url = static::urlToPattern($url);

    // Flush any cache found with that pattern
    return static::flush($url);
  }

  /**
   * Flush a specific route
   *
   * @param  string  $route      A route
   * @param  array   $parameters Parameters to pass it
   * @return boolean             Success or not
   */
  public static function flush_route($route, $parameters = array())
  {
    // Get matching URL
    $url = route($route, $parameters);
    $url = static::urlToPattern($url);

    // Flush any cache found with that pattern
    return static::flush($url);
  }

  ////////////////////////////////////////////////////////////////////
  ///////////////////////////// CORE METHODS /////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Load a page from the cache
   */
  private static function load()
  {
    Event::listen(Config::get('hook'), function() {

      // Get page from cache if any
      $cache = Cache::get(static::hash());

      // Render page
      if ($cache) static::render($cache);
    });
  }

  /**
   * Save the current page in the cache
   */
  private static function save()
  {
    Event::listen('laravel.done', function() {

      // Get content from buffer
      $content = ob_get_clean();

      // Cache it
      Cache::forever(static::hash(), $content);

      // Render page
      static::render($content);
    });
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////////// HELPERS ////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Transforms an URL into a Regex pattern
   *
   * @param  string $url An url to transform
   * @return string      A transformed URL
   */
  private static function urlToPattern($url)
  {
    // Remove the base from the URL
    $url = str_replace(\URL::base().'/', null, $url);

    // Remove language-specific pattern if any
    $url = preg_replace('#[a-z]{2}/(.+)#', '$1', $url);

    return $url;
  }

  /**
   * Get the current page's hash
   *
   * @return string A page hash
   */
  private static function hash($localize = true)
  {
    if(!static::$hash) {

      // Get folder and current page
      $folder = Config::get('folder');
      $page = \URI::current();

      // Localize the cache or not
      if($localize) {
        if(!starts_with($page, static::$lang)) $page = static::$lang.'/'.$page;
      }

      // Slugify and prepend folder
      $page = str_replace('/', '_', $page);
      if($folder) $page = $folder . DS . $page;

      // Cache the current page to avoid repetition
      // static::$hash = $page;
      return $page;
    }

    return static::$hash;
  }

  /**
   * Whether the current page matches against an array of pages
   *
   * @param  array $ignored   An array of pages regex
   * @return boolean          Matches or not
   */
  private static function matches($pages)
  {
    if(!$pages) return false;

    $page = static::$hash;
    if(!$page) $page = \URI::current();
    $pages = implode('|', $pages);

    return preg_match('#' .$pages. '#', $page);
  }

  /**
   * Render a content
   *
   * @param  string $content A content to render
   */
  private static function render($content)
  {
    // Set correct header
    header('Content-Type: text/html; charset=utf-8');

    // Display content
    exit($content);
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////////// TESTERS ////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Set the current URL for testing purposes
   *
   * @param string $hash The current hash to use
   */
  public static function setHash($hash)
  {
    static::$hash = $hash;
  }
}