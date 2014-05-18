<?php
/**
 * Twig::Runtime
 * ~~~~~~~~~~~~~
 *
 * The twig runtime environment.
 *
 * :copyright: 2008 by Armin Ronacher.
 * :license: BSD.
 */


$twig_filters = array(
    // formatting filters
    'date' =>              'twig_date_format_filter',
    'strftime' =>          'twig_strftime_format_filter',
    'strtotime' =>         'strtotime',
    'numberformat' =>      'number_format',
    'moneyformat' =>       'money_format',
    'filesizeformat' =>    'twig_filesize_format_filter',
    'format' =>            'sprintf',
    'relative' =>          'relative_time',

    // numbers
    'even' =>              'twig_is_even_filter',
    'odd' =>               'twig_is_odd_filter',

    // escaping and encoding
    'escape' =>           'twig_escape_filter',
    'e' =>                'twig_escape_filter',
    'urlencode' =>        'twig_urlencode_filter',
    'quotes' =>           'twig_quotes_filter',
    'slashes' =>          'addslashes',

    // string filters
    'title' =>            'twig_title_string_filter',
    'capitalize' =>       'twig_capitalize_string_filter',
    'upper' =>            'strtoupper',
    'lower' =>            'strtolower',
    'strip' =>            'trim',
    'rstrip' =>           'rtrim',
    'lstrip' =>           'ltrim',
    'translate' =>        'twig_translate_string_filter',
    'translate_plural' => 'twig_translate_plural_string_filter',
    'normalize' =>        'normalize',
    'truncate' =>         'twig_truncate_filter',
    'excerpt' =>          'twig_excerpt_filter',
    'replace' =>          'twig_replace_filter',
    'match' =>            'twig_match_filter',
    'contains' =>         'substr_count',
    'linebreaks' =>       'nl2br',
    'camelize' =>         'camelize',
    'strip_tags' =>       'strip_tags',
    'pluralize' =>        'twig_pluralize_string_filter',
    'depluralize' =>      'twig_depluralize_string_filter',
    'sanitize' =>         'sanitize',
    'repeat' =>           'str_repeat',

    // array helpers
    'join' =>             'twig_join_filter',
    'split' =>            'twig_split_filter',
    'first' =>            'twig_first_filter',
    'offset' =>           'twig_offset_filter',
    'last' =>             'twig_last_filter',
    'reverse' =>          'array_reverse',
    'length' =>           'twig_length_filter',
    'count' =>            'count',
    'sort' =>             'twig_sort_filter',

    // iteration and runtime
    'default' =>          'twig_default_filter',
    'keys' =>             'array_keys',
    'items' =>            'twig_get_array_items_filter',

    // debugging
    'inspect' =>          'twig_inspect_filter',

    'uploaded' =>         'uploaded',
    'fallback' =>         'oneof',
    'selected' =>         'twig_selected_filter',
    'checked' =>          'twig_checked_filter',
    'option_selected' =>  'twig_option_selected_filter'
);


class Twig_LoopContextIterator implements Iterator
{
    public $context;
    public $seq;
    public $idx;
    public $length;
    public $parent;

    public function __construct(&$context, $seq, $parent)
    {
        $this->context = $context;
        $this->seq = $seq;
        $this->idx = 0;
        $this->length = count($seq);
        $this->parent = $parent;
    }

    public function rewind() {}

    public function key() {}

    public function valid()
    {
        return $this->idx < $this->length;
    }

    public function next()
    {
        $this->idx++;
    }

    public function current()
    {
        return $this;
    }
}

function unretarded_array_unshift(&$arr, &$val) {
    $arr = array_merge(array(&$val), $arr);
}

/**
 * This is called like an ordinary filter just with the name of the filter
 * as first argument.  Currently we just raise an exception here but it
 * would make sense in the future to allow dynamic filter lookup for plugins
 * or something like that.
 */
function twig_missing_filter($name)
{
    $args = func_get_args();
    array_shift($args);

    $text = $args[0];
    array_shift($args);

    array_unshift($args, $name);
    unretarded_array_unshift($args, $text);

    $trigger = Trigger::current();

    if ($trigger->exists($name))
        return call_user_func_array(array($trigger, "filter"), $args);

    return $text;
}

function twig_get_attribute($obj, $item, $function = true)
{
    if (is_array($obj) && isset($obj[$item]))
        return $obj[$item];
    if (!is_object($obj))
        return NULL;
    if ($function and method_exists($obj, $item))
        return call_user_func(array($obj, $item));
    if (property_exists($obj, $item)) {
        $tmp = get_object_vars($obj);
        return $tmp[$item];
    }
    $method = 'get' . ucfirst($item);
    if ($function and method_exists($obj, $method))
        return call_user_func(array($obj, $method));
    if (is_object($obj)) {
        @$obj->$item; # Funky way of allowing __get to activate before returning the value.
        return @$obj->$item;
    }
    return NULL;
}

function twig_paginate(&$context, $as, $over, $per_page)
{
    $name = (in_array("page", Paginator::$names)) ? $as."_page" : "page" ;

    if (count($over) == 2 and $over[0] instanceof Model and is_string($over[1]))
        $context[$as] = $context["::parent"][$as] = new Paginator($over[0]->__getPlaceholders($over[1]), $per_page, $name);
    else
        $context[$as] = $context["::parent"][$as] = new Paginator($over, $per_page, $name);
}

function twig_iterate(&$context, $seq)
{
    $parent = isset($context['loop']) ? $context['loop'] : null;
    $seq = twig_make_array($seq);
    $context['loop'] = array('parent' => $parent, 'iterated' => false);
    return new Twig_LoopContextIterator($context, $seq, $parent);
}

function twig_set_loop_context(&$context, $iterator, $target)
{
    $context[$target] = $iterator->seq[$iterator->idx];
    $context['loop'] = twig_make_loop_context($iterator);
}

function twig_set_loop_context_multitarget(&$context, $iterator, $targets)
{
    $values = $iterator->seq[$iterator->idx];
    if (!is_array($values))
        $values = array($values);
    $idx = 0;
    foreach ($values as $value) {
        if (!isset($targets[$idx]))
            break;
        $context[$targets[$idx++]] = $value;
    }
    $context['loop'] = twig_make_loop_context($iterator);
}

function twig_make_loop_context($iterator)
{
    return array(
        'parent' =>     $iterator->parent,
        'length' =>     $iterator->length,
        'index0' =>     $iterator->idx,
        'index' =>      $iterator->idx + 1,
        'revindex0' =>  $iterator->length - $iterator->idx - 1,
        'revindex '=>   $iterator->length - $iterator->idx,
        'first' =>      $iterator->idx == 0,
        'last' =>       $iterator->idx + 1 == $iterator->length,
        'iterated' =>   true
    );
}

function twig_make_array($object)
{
    if (is_array($object))
        return array_values($object);
    elseif (is_object($object)) {
        $result = array();
        foreach ($object as $value)
            $result[] = $value;
        return $result;
    }
    return array();
}

function twig_date_format_filter($timestamp, $format='F j, Y, G:i')
{
    return when($format, $timestamp);
}

function twig_strftime_format_filter($timestamp, $format='%x %X')
{
    return when($format, $timestamp, true);
}

function twig_urlencode_filter($url, $raw=false)
{
    if ($raw)
        return rawurlencode($url);
    return urlencode($url);
}

function twig_join_filter($value, $glue='')
{
    return implode($glue, (array) $value);
}

function twig_default_filter($value, $default='')
{
    return is_null($value) ? $default : $value;
}

function twig_get_array_items_filter($array)
{
    $result = array();
    foreach ($array as $key => $value)
        $result[] = array($key, $value);
    return $result;
}

function twig_filesize_format_filter($value)
{
    $value = max(0, (int)$value);
    $places = strlen($value);
    if ($places <= 9 && $places >= 7) {
        $value = number_format($value / 1048576, 1);
        return "$value MB";
    }
    if ($places >= 10) {
        $value = number_format($value / 1073741824, 1);
        return "$value GB";
    }
    $value = number_format($value / 1024, 1);
    return "$value KB";
}

function twig_is_even_filter($value)
{
    return $value % 2 == 0;
}

function twig_is_odd_filter($value)
{
    return $value % 2 == 1;
}

function twig_replace_filter($str, $search, $replace, $regex = false)
{
    if ($regex)
        return preg_replace($search, $replace, $str);
    else
        return str_replace($search, $replace, $str);
}

function twig_match_filter($str, $match)
{
    return preg_match($match, $str);
}


// add multibyte extensions if possible
if (function_exists('mb_get_info')) {
    function twig_upper_filter($string)
    {
        $template = twig_get_current_template();
        if (!is_null($template->charset))
            return mb_strtoupper($string, $template->charset);
        return strtoupper($string);
    }

    function twig_lower_filter($string)
    {
        $template = twig_get_current_template();
        if (!is_null($template->charset))
            return mb_strtolower($string, $template->charset);
        return strtolower($string);
    }

    function twig_title_string_filter($string)
    {
        $template = twig_get_current_template();
        if (is_null($template->charset))
            return ucwords(strtolower($string));
        return mb_convert_case($string, MB_CASE_TITLE, $template->charset);
    }

    function twig_capitalize_string_filter($string)
    {
        $template = twig_get_current_template();
        if (is_null($template->charset))
            return ucfirst(strtolower($string));
        return mb_strtoupper(mb_substr($string, 0, 1, $template->charset)) .
               mb_strtolower(mb_substr($string, 1, null, $template->charset));
    }

    // override the builtins
    $twig_filters['upper'] = 'twig_upper_filter';
    $twig_filters['lower'] = 'twig_lower_filter';
}

// and byte fallback
else {
    function twig_title_string_filter($string)
    {
        return ucwords(strtolower($string));
    }

    function twig_capitalize_string_filter($string)
    {
        return ucfirst(strtolower($string));
    }
}

function twig_translate_string_filter($string, $domain = "theme") {
    $domain = ($domain == "theme" and ADMIN) ? "chyrp" : $domain ;
    return __($string, $domain);
}

function twig_translate_plural_string_filter($single, $plural, $number, $domain = "theme") {
    $domain = ($domain == "theme" and ADMIN) ? "chyrp" : $domain ;
    return _p($single, $plural, $number, $domain);
}

function twig_inspect_filter($thing) {
    if (ini_get("xdebug.var_display_max_depth") == -1)
        return var_dump($thing);
    else
        return '<pre class="chyrp_inspect"><code>' .
               fix(var_export($thing, true)) .
               '</code></pre>';
}

function twig_split_filter($string, $cut = " ") {
    return explode($cut, $string);
}

function twig_first_filter($array) {
    foreach ($array as $key => &$val)
        return $val; # Return the first one.

    return false;
}

function twig_last_filter($array) {
    return $array[count($array) - 1];
}

function twig_offset_filter($array, $offset = 0) {
    return $array[$offset];
}

function twig_selected_filter($foo) {
    $try = func_get_args();
    array_shift($try);

    $just_class = (end($try) === true);
    if ($just_class)
        array_pop($try);

    if (is_array($try[0])) {
        foreach ($try as $index => $it)
            if ($index)
                $try[0][] = $it;

        $try = $try[0];
    }

    if (in_array($foo, $try))
        return ($just_class) ? " selected" : ' class="selected"' ;
}

function twig_checked_filter($foo) {
    if ($foo)
        return ' checked="checked"';
}

function twig_option_selected_filter($foo) {
    $try = func_get_args();
    array_shift($try);

    if (in_array($foo, $try))
        return ' selected="selected"';
}

function twig_pluralize_string_filter($string, $number = null) {
    if ($number and $number == 1)
        return $string;
    else
        return pluralize($string);
}

function twig_depluralize_string_filter($string) {
    return depluralize($string);
}

function twig_quotes_filter($string) {
    return str_replace(array('"', "'"), array('\"', "\\'"), $string);
}

function twig_length_filter($thing) {
    if (is_string($thing))
        return strlen($thing);
    else
        return count($thing);
}

function twig_escape_filter($string, $quotes = true, $decode = true) {
    if (!is_string($string)) # Certain post attributes might be parsed from YAML to an array,
        return $string;      # in which case the module provides a value. However, the attr
                             # is still passed to the "fallback" and "fix" filters when editing.

    $safe = fix($string, $quotes);
    return $decode ? preg_replace("/&amp;(#?[A-Za-z0-9]+);/", "&\\1;", $safe) : $safe ;
}

function twig_truncate_filter($text, $length = 100, $ending = "...", $exact = false, $html = true) {
    return truncate($text, $length, $ending, $exact, $html);
}

function twig_excerpt_filter($text, $length = 200, $ending = "...", $exact = false, $html = true) {
    $paragraphs = preg_split("/(\r?\n\r?\n|\r\r)/", $text);
    if (count($paragraphs) > 1)
        return $paragraphs[0];
    else
        return truncate($text, $length, $ending, $exact, $html);
}

function twig_sort_filter($array) {
    asort($array);
    return $array;
}
