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
 * A node, used for parsing YAML.
 *
 * @category Horde
 * @package  Horde_Yaml
 */
class Horde_Yaml_Node
{
    /**
     * @var string
     */
    public $parent;

    /**
     */
    public $id;

    /**
     * @var mixed
     */
    public $data;

    /**
     * @var integer
     */
    public $indent;

    /**
     * @var bool
     */
    public $children = false;

    /**
     * The constructor assigns the node a unique ID.
     * @return void
     */
    public function __construct($nodeId)
    {
        $this->id = $nodeId;
    }

}
