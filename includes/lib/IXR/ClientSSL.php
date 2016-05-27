<?php
/**
 * Client for communicating with a XML-RPC Server over HTTPS.
 *
 * @package IXR
 *
 * @author    Jason Stirk <jstirk@gmm.com.au>
 * @link      http://blog.griffin.homelinux.org/projects/xmlrpc/
 * @version   0.2.0 26May2005 08:34 +0800
 * @copyright (c) 2004-2005 Jason Stirk
 * @license   BSD License http://www.opensource.org/licenses/bsd-license.php
 */

class IXR_ClientSSL extends IXR_Client
{
    /**
     * Filename of the SSL Client Certificate
     * @access private
     * @since 0.1.0
     * @var string
     */
    var $_certFile;

    /**
     * Filename of the SSL CA Certificate
     * @access private
     * @since 0.1.0
     * @var string
     */
    var $_caFile;

    /**
     * Filename of the SSL Client Private Key
     * @access private
     * @since 0.1.0
     * @var string
     */
    var $_keyFile;

    /**
     * Passphrase to unlock the private key
     * @access private
     * @since 0.1.0
     * @var string
     */
    var $_passphrase;

    /**
     * Constructor
     * @param string $server URL of the Server to connect to
     * @since 0.1.0
     */
    function IXR_ClientSSL($server, $path = false, $port = 443, $timeout = false)
    {
        parent::IXR_Client($server, $path, $port, $timeout);
        $this->useragent = 'The Incutio XML-RPC PHP Library for SSL';

        // Set class fields
        $this->_certFile=false;
        $this->_caFile=false;
        $this->_keyFile=false;
        $this->_passphrase='';
    }

    /**
     * Set the client side certificates to communicate with the server.
     *
     * @since 0.1.0
     * @param string $certificateFile Filename of the client side certificate to use
     * @param string $keyFile Filename of the client side certificate's private key
     * @param string $keyPhrase Passphrase to unlock the private key
     */
    function setCertificate($certificateFile, $keyFile, $keyPhrase='')
    {
        // Check the files all exist
        if (is_file($certificateFile)) {
            $this->_certFile = $certificateFile;
        } else {
            die('Could not open certificate: ' . $certificateFile);
        }

        if (is_file($keyFile)) {
            $this->_keyFile = $keyFile;
        } else {
            die('Could not open private key: ' . $keyFile);
        }

        $this->_passphrase=(string)$keyPhrase;
    }

    function setCACertificate($caFile)
    {
        if (is_file($caFile)) {
            $this->_caFile = $caFile;
        } else {
            die('Could not open CA certificate: ' . $caFile);
        }
    }

    /**
     * Sets the connection timeout (in seconds)
     * @param int $newTimeOut Timeout in seconds
     * @returns void
     * @since 0.1.2
     */
    function setTimeOut($newTimeOut)
    {
        $this->timeout = (int)$newTimeOut;
    }

    /**
     * Returns the connection timeout (in seconds)
     * @returns int
     * @since 0.1.2
     */
    function getTimeOut()
    {
        return $this->timeout;
    }

    /**
     * Set the query to send to the XML-RPC Server
     * @since 0.1.0
     */
    function query()
    {
        $args = func_get_args();
        $method = array_shift($args);
        $request = new IXR_Request($method, $args);
        $length = $request->getLength();
        $xml = $request->getXml();

        if ($this->debug) {
            echo '<pre>'.htmlspecialchars($xml)."\n</pre>\n\n";
        }

        //This is where we deviate from the normal query()
        //Rather than open a normal sock, we will actually use the cURL
        //extensions to make the calls, and handle the SSL stuff.

        //Since 04Aug2004 (0.1.3) - Need to include the port (duh...)
        //Since 06Oct2004 (0.1.4) - Need to include the colon!!!
        //        (I swear I've fixed this before... ESP in live... But anyhu...)
        $curl=curl_init('https://' . $this->server . ':' . $this->port . $this->path);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        //Since 23Jun2004 (0.1.2) - Made timeout a class field
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        if ($this->debug) {
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
        }

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_PORT, $this->port);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                                    "Content-Type: text/xml",
                                    "Content-length: {$length}"));

        // Process the SSL certificates, etc. to use
        if (!($this->_certFile === false)) {
            // We have a certificate file set, so add these to the cURL handler
            curl_setopt($curl, CURLOPT_SSLCERT, $this->_certFile);
            curl_setopt($curl, CURLOPT_SSLKEY, $this->_keyFile);

            if ($this->debug) {
                echo "SSL Cert at : " . $this->_certFile . "\n";
                echo "SSL Key at : " . $this->_keyFile . "\n";
            }

            // See if we need to give a passphrase
            if (!($this->_passphrase === '')) {
                curl_setopt($curl, CURLOPT_SSLCERTPASSWD, $this->_passphrase);
            }

            if ($this->_caFile === false) {
                // Don't verify their certificate, as we don't have a CA to verify against
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            } else {
                // Verify against a CA
                curl_setopt($curl, CURLOPT_CAINFO, $this->_caFile);
            }
        }

        // Call cURL to do it's stuff and return us the content
        $contents = curl_exec($curl);
        curl_close($curl);

        // Check for 200 Code in $contents
        if (!strstr($contents, '200 OK')) {
            //There was no "200 OK" returned - we failed
            $this->error = new IXR_Error(-32300, 'transport error - HTTP status code was not 200');
            return false;
        }

        if ($this->debug) {
            echo '<pre>'.htmlspecialchars($contents)."\n</pre>\n\n";
        }
        // Now parse what we've got back
        // Since 20Jun2004 (0.1.1) - We need to remove the headers first
        // Why I have only just found this, I will never know...
        // So, remove everything before the first <
        $contents = substr($contents,strpos($contents, '<'));

        $this->message = new IXR_Message($contents);
        if (!$this->message->parse()) {
            // XML error
            $this->error = new IXR_Error(-32700, 'parse error. not well formed');
            return false;
        }
        // Is the message a fault?
        if ($this->message->messageType == 'fault') {
            $this->error = new IXR_Error($this->message->faultCode, $this->message->faultString);
            return false;
        }

        // Message must be OK
        return true;
    }
}

/**
 * Extension of the {@link IXR_Server} class to easily wrap objects.
 *
 * Class is designed to extend the existing XML-RPC server to allow the
 * presentation of methods from a variety of different objects via an
 * XML-RPC server.
 * It is intended to assist in organization of your XML-RPC methods by allowing
 * you to "write once" in your existing model classes and present them.
 *
 * @author Jason Stirk <jstirk@gmm.com.au>
 * @version 1.0.1 19Apr2005 17:40 +0800
 * @copyright Copyright (c) 2005 Jason Stirk
 * @package IXR
 */
class IXR_ClassServer extends IXR_Server
{
    var $_objects;
    var $_delim;

    function IXR_ClassServer($delim = '.', $wait = false)
    {
        $this->IXR_Server(array(), false, $wait);
        $this->_delimiter = $delim;
        $this->_objects = array();
    }

    function addMethod($rpcName, $functionName)
    {
        $this->callbacks[$rpcName] = $functionName;
    }

    function registerObject($object, $methods, $prefix=null)
    {
        if (is_null($prefix))
        {
            $prefix = get_class($object);
        }
        $this->_objects[$prefix] = $object;

        // Add to our callbacks array
        foreach($methods as $method)
        {
            if (is_array($method))
            {
                $targetMethod = $method[0];
                $method = $method[1];
            }
            else
            {
                $targetMethod = $method;
            }
            $this->callbacks[$prefix . $this->_delimiter . $method]=array($prefix, $targetMethod);
        }
    }

    function call($methodname, $args)
    {
        if (!$this->hasMethod($methodname)) {
            return new IXR_Error(-32601, 'server error. requested method '.$methodname.' does not exist.');
        }
        $method = $this->callbacks[$methodname];

        // Perform the callback and send the response
        if (count($args) == 1) {
            // If only one paramater just send that instead of the whole array
            $args = $args[0];
        }

        // See if this method comes from one of our objects or maybe self
        if (is_array($method) || (substr($method, 0, 5) == 'this:')) {
            if (is_array($method)) {
                $object=$this->_objects[$method[0]];
                $method=$method[1];
            } else {
                $object=$this;
                $method = substr($method, 5);
            }

            // It's a class method - check it exists
            if (!method_exists($object, $method)) {
                return new IXR_Error(-32601, 'server error. requested class method "'.$method.'" does not exist.');
            }

            // Call the method
            $result = $object->$method($args);
        } else {
            // It's a function - does it exist?
            if (!function_exists($method)) {
                return new IXR_Error(-32601, 'server error. requested function "'.$method.'" does not exist.');
            }

            // Call the function
            $result = $method($args);
        }
        return $result;
    }
}
