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

class IXR_Error
{
    var $code;
    var $message;

    function IXR_Error($code, $message)
    {
        $this->code = $code;
        $this->message = htmlspecialchars($message);
    }

    function getXml()
    {
        $xml = <<<EOD
<methodResponse>
  <fault>
    <value>
      <struct>
        <member>
          <name>faultCode</name>
          <value><int>{$this->code}</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>{$this->message}</string></value>
        </member>
      </struct>
    </value>
  </fault>
</methodResponse>

EOD;
        return $xml;
    }
}
