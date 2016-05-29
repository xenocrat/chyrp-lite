<?php
/**
 * IXR - The Incutio XML-RPC Library
 *
 * @package   IXR
 *
 * @copyright Incutio Ltd 2010 (http://www.incutio.com)
 * @version   1.7.4 7th September 2010
 * @author    Simon Willison
 * @link      http://scripts.incutio.com/xmlrpc/
 * @license   BSD License http://www.opensource.org/licenses/bsd-license.php
 */

class IXR_Base64
{
    var $data;

    function __construct($data)
    {
        $this->data = $data;
    }

    function getXml()
    {
        return '<base64>'.base64_encode($this->data).'</base64>';
    }
}
