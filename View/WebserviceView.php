<?php
/**
 * Webservice View Class
 *
 * Renders the data as either json or xml
 *
 * PHP versions 4 and 5
 *
 * Copyright 2010, Jose Diaz-Gonzalez
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the below copyright notice.
 *
 * @copyright   Copyright 2010, Jose Diaz-Gonzalez
 * @package     webservice
 * @subpackage  webservice.views
 * @link        http://github.com/josegonzalez/webservice_plugin
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 **/
App::uses('View', 'View');
class WebserviceView extends View {

/**
 * XML document encoding
 *
 * @var string
 * @access private
 */
	public $xml_encoding = 'UTF-8';

/**
 * XML document version
 *
 * @var string
 * @access private
 */
	public $xml_version = '1.0';

/**
 * Determines whether native JSON extension is used for encoding.  Set by object constructor.
 *
 * @var boolean
 * @access public
 */
   public $json_useNative = true;

/**
 * Array of parameter data
 *
 * @var array Parameter data
 */
	public $params = array();

/**
 * Variables for the view
 *
 * @var array
 * @access public
 */
	public $viewVars = array();

/**
 * List of variables to collect from the associated controller
 *
 * @var array
 * @access protected
 */
	protected $__passedVars = array(
		'viewVars', 'params'
	);

/**
 * Constructor
 *
 * @param Controller $controller A controller object to pull View::__passedArgs from.
 * @param boolean $register Should the View instance be registered in the ClassRegistry
 * @return View
 */
	function __construct(&$controller) {
		if (isset($controller->Session))
			$this->viewVars['flashMessage'] = $controller->Session->read('Message');
		if (empty($this->request->params['useJsonNative']))
			$this->request->params['useJsonNative'] = false;
		parent::__construct($controller);
	}

	protected function _setHeader($header) {
		header($header);
    }

	public function render() {
		Configure::write('debug', 0);
		$textarea = false;
		if (isset($this->viewVars['debugToolbarPanels']))
			unset($this->viewVars['debugToolbarPanels']);
		if (isset($this->viewVars['debugToolbarJavascript']))
			unset($this->viewVars['debugToolbarJavascript']);
		if (isset($this->viewVars['webserviceTextarea'])) {
			$textarea = true;
			unset($this->viewVars['webserviceTextarea']);
		}
		if (!empty($this->validationErrors)) {
            $this->viewVars['validationErrors'] = $this->validationErrors;
        }
		switch ($this->request->params['ext']) {
			case 'json':
				$this->_setHeader("Pragma: no-cache");
				$this->_setHeader("Cache-Control: no-store, no-cache, max-age=0, must-revalidate");
				$this->_setHeader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
				$this->_setHeader("Last-Modified: " . gmdate('D, d M Y H:i:s') . ' GMT');
				if (!$textarea)
					$this->_setHeader('Content-type: application/json');
				return ($textarea ? '<textarea>' : '') . json_encode($this->viewVars) . ($textarea ? '</textarea>' : '');
				break;
			case 'xml':
				if (!$textarea)
					$this->_setHeader('Content-type: application/xml');
				return ($textarea ? '<textarea>' : '') . $this->toXml($this->viewVars) . ($textarea ? '</textarea>' : '');
		}
	}

/**
 * Dummy method
 *
 * @deprecated deprecated in Webservice view
 */
	public function renderLayout() {}


/**
 * The main function for converting to an XML document.
 * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
 *
 * @param array $data
 * @param string $rootNodeName - what you want the root node to be - defaultsto data.
 * @param SimpleXMLElement $xml - should only be used recursively
 * @return string XML
 */
	public function toXML($data, $rootNodeName = 'ResultSet', &$xml = null) {
		// turn off compatibility mode as simple xml throws a wobbly if you don't.
		if (ini_get('zend.ze1_compatibility_mode') == 1) ini_set('zend.ze1_compatibility_mode', 0);
		if (is_null($xml)) $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><{$rootNodeName} />");

		// loop through the data passed in.
		foreach ($data as $key => $value) {
			// no numeric keys in our xml please!
			$numeric = false;
			if (is_numeric($key)) {
				$numeric = 1;
				$key = $rootNodeName;
			}

			// delete any char not allowed in XML element names
			$key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);

			// if there is another array found recrusively call this function
			if (is_array($value)) {
				$node = $this->isAssoc($value) || $numeric ? $xml->addChild($key) : $xml;

				// recrusive call.
				if ($numeric) $key = 'anon';
				$this->toXml($value, $key, $node);
			} else {
				// add single node.
				$value = htmlentities($value);
    			$xml->addChild($key, $value);
			}
		}

		//return $xml->asXML();
		// if you want the XML to be formatted, use the below instead to return the XML

		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($xml->asXML());
		$doc->formatOutput = true;
		return $doc->saveXML();
	}


/**
 * Convert an XML document to a multi dimensional array
 * Pass in an XML document (or SimpleXMLElement object) and this recursively loops through and builds a representative array
 *
 * @param string $xml - XML document - can optionally be a SimpleXMLElement object
 * @return array ARRAY
 */
	public function toArray($xml) {
		if (is_string($xml)) $xml = new SimpleXMLElement($xml);

		$children = $xml->children();
		if (!$children) return (string) $xml;

		$arr = array();
		foreach ($children as $key => $node) {
			$node = $this->toArray($node);

			// support for 'anon' non-associative arrays
			if ($key == 'anon') $key = count($arr);

			// if the node is already set, put it into an array
			if (isset($arr[$key])) {
				if (!is_array($arr[$key]) || $arr[$key][0] == null) $arr[$key] = array($arr[$key]);
				$arr[$key][] = $node;
			} else {
				$arr[$key] = $node;
			}
		}
		return $arr;
	}

/**
 * Determine if a variable is an associative array
 *
 * @param mixed $variable variable to checked for associativity
 * @return boolean try if variable is an associative array, false if otherwise
 */
	public function isAssoc($variable) {
		return (is_array($variable) && 0 !== count(array_diff_key($variable, array_keys(array_keys($variable)))));
	}

}
