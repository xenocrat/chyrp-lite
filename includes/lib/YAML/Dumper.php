<?php
/**
 * Horde YAML package
 *
 * This package is heavily inspired by the Spyc PHP YAML
 * implementation (http://spyc.sourceforge.net/), and portions are
 * copyright 2005-2006 Chris Wanstrath.
 *
 * @author   Chris Wanstrath (chris@ozmm.org)
 * @author   Chuck Hagenbuch (chuck@horde.org)
 * @author   Mike Naberezny (mike@maintainable.com)
 * @license  http://opensource.org/licenses/bsd-license.php BSD
 * @category Horde
 * @package  Horde_Yaml
 */

/**
 * Dump PHP data structures to YAML.
 *
 * @category Horde
 * @package  Horde_Yaml
 */
class Horde_Yaml_Dumper
{
    protected $_options = array();

    /**
     * Dump PHP array to YAML
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into valid YAML.
     *
     * Options:
     *    `indent`:
     *       number of spaces to indent children (default 2)
     *    `wordwrap`:
     *       wordwrap column number (default 40)
     *
     * @param  array|Traversable  $array     PHP array or traversable object
     * @param  integer            $options   Options for dumping
     * @return string                        YAML representation of $value
     */
    public function dump($value, $options = array())
    {
        // validate & merge default options
        if (!is_array($options)) {
            throw new InvalidArgumentException('Options must be an array');
        }

        $defaults = array('indent'   => 2,
                          'wordwrap' => 0);
        $this->_options = array_merge($defaults, $options);

        if (! is_int($this->_options['indent'])) {
            throw new InvalidArgumentException('Indent must be an integer');
        }

        if (! is_int($this->_options['wordwrap'])) {
            throw new InvalidArgumentException('Wordwrap column must be an integer');
        }

        // new YAML document
        $dump = "---\n";

        // iterate through array and yamlize it
        foreach ($value as $key => $val) {
            $dump .= $this->_yamlize($key, $val, 0, ($value === array_values($value)));
        }
        return $dump;
    }

    /**
     * Attempts to convert a key / value array item to YAML
     *
     * @param  string        $key     The name of the key
     * @param  string|array  $value   The value of the item
     * @param  integer       $indent  The indent of the current node
     * @param  boolean       $seq     Is the item part of a sequence?
     * @return string
     */
    protected function _yamlize($key, $value, $indent, $seq = false)
    {
        if ($value instanceof Serializable) {
            // Dump serializable objects as !php/object::classname serialize_data
            $data = '!php/object::' . get_class($value) . ' ' . $value->serialize();
            $string = $this->_dumpNode($key, $data, $indent);
        } elseif (is_array($value) || $value instanceof Traversable) {
            // It has children.  Make it the right kind of item.
            $string = $this->_dumpNode($key, null, $indent);

            // Add the indent.
            $indent += $this->_options['indent'];

            // Yamlize the array.
            $string .= $this->_yamlizeArray($value, $indent);
        } elseif (!is_array($value)) {
            // No children.
            $string = $this->_dumpNode($key, $value, $indent, $seq);
        }

        return $string;
    }

    /**
     * Attempts to convert an array to YAML
     *
     * @param  array    $array The array you want to convert
     * @param  integer  $indent The indent of the current level
     * @return string
     */
    protected function _yamlizeArray($array, $indent)
    {
        if (!is_array($array)) {
            return false;
        }

        $seq = ($array === array_values($array));

        $string = '';
        foreach ($array as $key => $value) {
            $string .= $this->_yamlize($key, $value, $indent, $seq);
        }
        return $string;
    }

    /**
     * Returns YAML from a key and a value
     *
     * @param  string   $key     The name of the key
     * @param  string   $value   The value of the item
     * @param  integer  $indent  The indent of the current node
     * @param  boolean  $seq     Is the item part of a sequence?
     * @return string
     */
    protected function _dumpNode($key, $value, $indent, $seq = false)
    {
        // Do some folding here, for blocks.
        if (strpos($value, "\n") !== false
            || strpos($value, ': ') !== false
            || strpos($value, '- ') !== false) {
            $value = $this->_doLiteralBlock($value, $indent);
        } else {
            $value = $this->_fold($value, $indent);
        }

        if (is_bool($value)) {
            $value = ($value) ? 'true' : 'false';
        } elseif (is_float($value)) {
            if (is_nan($value)) {
                $value = '.NAN';
            } elseif ($value === INF) {
                $value = '.INF';
            } elseif ($value === -INF) {
                $value = '-.INF';
            }
        }

        $spaces = str_repeat(' ', $indent);

        if ($seq) {
            // It's a sequence.
            $string = $spaces . '- ' . $value . "\n";
        } else {
            // It's mapped.
            $string = $spaces . $key . ': ' . $value . "\n";
        }

        return $string;
    }

    /**
     * Creates a literal block for dumping
     *
     * @param  string   $value
     * @param  integer  $indent  The value of the indent.
     * @return string
     */
    protected function _doLiteralBlock($value, $indent)
    {
        $exploded = explode("\n", $value);
        $newValue = '|';
        $indent += $this->_options['indent'];
        $spaces = str_repeat(' ', $indent);
        foreach ($exploded as $line) {
            $newValue .= "\n" . $spaces . trim($line);
        }
        return $newValue;
    }

    /**
     * Folds a string of text, if necessary
     *
     * @param   $value   The string you wish to fold
     * @return  string
     */
    protected function _fold($value, $indent)
    {
        // Don't do anything if wordwrap is set to 0
        if (! $this->_options['wordwrap']) {
            return (is_string($value) and !is_numeric($value) and !empty($value)) ? '"'.str_replace("\"", "\\\"", $value).'"' : $value ;
        }

        if (strlen($value) > $this->_options['wordwrap']) {
            $indent += $this->_options['indent'];
            $indent = str_repeat(' ', $indent);
            $wrapped = wordwrap($value, $this->_options['wordwrap'], "\n$indent");
            $value = ">\n" . $indent . $wrapped;
        }

        return $value;
    }

}
