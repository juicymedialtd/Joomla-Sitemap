<?php
/**
 * @author 		Juicy Media Ltd <joomla@juicymedia.co.uk>
 * @version		2
 * @package		JM Sitemap
 * @copyright	Copyright (C) 2005 - 2007 Open Source Matters. All rights reserved.
 * @license		GNU/GPL
 *
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 *
 * + Initial construction Peter Davies, 10 June 2008
 * + Sitemap integration and extract Michael Oldroyd, 11 June 2008
 * + Multiple Menu and External Link Support Michael Oldroyd, 13 June 2008
 * + Article 'lastmod' Date extraction and 'changefreq' conversion, 16 June 2008
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('joomla.application.component.view');
jimport('joomla.base.tree');
jimport('joomla.utilities.simplexml');

define('CURL_READY',0);
define('CURL_BLOCKED',1);
define('CURL_UNAVAILABLE',2);


/**
 * This is the main JM Sitemap class. Its designed to get the menu listing,
 * loop-through and generate an XML output of the menu using existing
 * Joomla functionality.
 *
 */
class SitemapViewGooglemap extends JView {
	/**
	 * Ouput XML sitemap
	 *
	 * @param string $tpl
	 */
	function display($tpl = null){
		// tell the browser
		header('Content-type: application/xml');
		$menu_data = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

		// get menu items
		$menu_data .= $this->getAllMenuData();
		$this->set('menu_data', $menu_data);

		// send to the file
		$this->saveData($menu_data);

		// submit the sitemap to search engines after writing it
		//print_r($this->submitSitemap());

		// send to the screen
		echo $menu_data;

		// we need to exit here so that
		// the Joomla template does not load
		exit();
	}

	/**
	 * Save XML data
	 *
	 * @param string $data
	 */
	function saveData($data){
		$path = JPATH_SITE.DS;
		JPath::check( $path );
		$out = fopen($path."sitemap.xml", "w");
		fwrite($out, $data);
		fclose($out);
	}

	/**
	 * Submits the xml sitemap location
	 *
	 * @return array The results of the submission
	 */
	function submitSitemap() {
		$sitemap = new SubmitSitemap();
		return $sitemap->curlTest();
	}

	/**
	 * Get all data for each menu
	 *
	 * @return string
	 */
	function getAllMenuData(){
		// get the base menu
		$items = &JSite::getMenu();

		// get the list and loop through the menus
		$menus = $this->getMenuList();
		$menu_str = "";
		foreach ($menus as $menu_item) {
			// get the XML listing for a given menu type
			$menu_str .= $this->render('jmGooglemapXMLCallback',$menu_item['menutype']);
		}
		return $this->processToXML($menu_str);
	}

	/**
	 * Get the list of published system menus
	 *
	 * @return array
	 */
	function getMenuList(){
		$db =& JFactory::getDBO();			
		$query = 'SELECT m.menutype, m.title, jm.ordering FROM #__menu_types AS m, #__jmsitemap_menus as jm where jm.id = m.id and jm.published=1 order by jm.ordering asc';
		$db->setQuery( $query );

		// load all rows as associative list
		return $db->loadAssocList();
	}

	/**
	 * This will construct an XML listing of the menu
	 *
	 * @param string $menu_name
	 * @return string
	 */
	function buildXML($menu_name = "mainmenu"){
		$menu = new JGooglemapTree();
		$items = &JSite::getMenu();

		// Get Menu Items
		/*replace line with query execution getMenu is not enough*/$rows = $items->getItems('menutype', $menu_name);
		//print_r($rows);

		// Build Menu Tree root down (orphan proof - child might have lower id than parent)
		$user =& JFactory::getUser();
		$ids = array();
		$ids[0] = true;

		// pop the first item until the array is empty
		while ( @!is_null($row = array_shift($rows))){
			if (array_key_exists($row->parent, $ids)) {
				$menu->addNode($row);
				// record loaded parents
				$ids[$row->id] = true;
			} else {
				// no parent yet so push item to back of list
				array_push($rows, $row);
			}
		}
		return $menu->toXML();
	}

	/**
	 * This function gets the XML structure and sub items
	 *
	 * @param string $menu_name
	 * @param string $decorator
	 * @return string
	 */
	function &getXML($menu_name, $decorator){
		static $xmls;

		$string = $this->buildXML($menu_name);
		$xmls[$menu_name] = $string;
		$doc = $xmls[$menu_name];

		// Get document
		$xml = JFactory::getXMLParser('Simple');
		$xml->loadString($xmls[$menu_name]);
		$doc = &$xml->document;

		return $doc;
	}

	/**
	 * Processes the XML string to remove placeholder parent elements
	 *
	 * @param unknown_type $xmlString
	 */
	function processToXML($xmlString) {

		//Strip out the placeholder root elements
		$rules = array(
			'/<root>\\n/',
			'/<\/root>/'
		);
		$xmlString = preg_replace($rules,'',$xmlString);

		// validate against a bunch of schemas
		$xml = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'."\n";
        $xml .= ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9"'."\n";
        //$xml .= ' url="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"'."\n";
        $xml .= ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $xml .= $xmlString;

        //Add closing element
        $xml .= '</urlset>';

        return $xml;
	}

	/**
	 * Create the UL for the menu adding ID's & class
	 *
	 * @param string $callback
	 * @param string $menu_name
	 * @return string
	 */
	function render($callback, $menu_name="mainmenu"){
		// include the menu class
		$xml = SitemapViewGooglemap::getXML($menu_name, $callback);
		if ($xml) {
			// show white space?
			$show_whitespace = true;
			return JFilterOutput::ampReplace($xml->toString((bool)$show_whitespace));
		}
	}

	/**
	 * This generates the XML list of menu items
	 *
	 * @param JGooglemapMenuNode $node
	 * @param Array $args
	 */
	function jmGooglemapXMLCallback(&$node, $args){
		$user	= &JFactory::getUser();
		$menu	= &JSite::getMenu();
		$active	= $menu->getActive();
		$path	= isset($active) ? array_reverse($active->tree) : null;
	}
}

/**
 * Handles submission of the sitemap in a RESTful manner,
 * using curl
 *
 * @todo Add list of search engine URLs
 * @todo Check curl can communicate with external servers
 * @todo Allow custom configuration of curl using administrator front-end
 */
class SubmitSitemap {
	/**
	 * The vars to pass to the search engine
	 *
	 * @var string
	 */
	var $_submitURI;

	/**
	 * Whether the current envionment has curl
	 * support
	 *
	 * @var bool
	 */
	var $_curlAvailable;

	/**
	 * The list of available search engines to submit
	 *
	 * @var array
	 */
	var $_availableEngines;

	/**
	 * Initialises the submit URI, checks for curl, and defines
	 * the list of search engines to submit to
	 *
	 * @todo Get list of available search engines from a database
	 * table (Allowing users to add and remove engines they want to
	 * submit to)
	 */
	function __construct() {
		$this->curlTest;

		if ($this->_curlAvailable === CURL_READY) {
			$this->_submitURI = urlencode("/ping?sitemap=http://"
								.$_SERVER['SERVER_NAME'].
								"/sitemap.xml");

			$this->_availableEngines = array (
				array('loc'=>'google.com'),
				array('loc'=>'live.com')
			);
		}
	}

	/**
	 * A Decent way to test that curl is available
	 * and correctly configured. Detects whether curl
	 * is installed then attempts to get a response
	 * from the google.com servers
	 *
	 * @todo What to do if curl doesn't work
	 */
	function curlTest() {
		//Detect if curl is installed
		$this->_curlAvailable = (function_exists('curl_init'))
			? CURL_BLOCKED : CURL_UNAVAILABLE;

		if ($this->_curlAvailable === CURL_BLOCKED) {
			//Set up the test connection
			$testConn = curl_init('http://www.google.com');

			//Transmission Options
			curl_setopt($testConn, CURLOPT_HEADER, 1);
			curl_setopt($testConn, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($testConn, CURLOPT_TIMEOUT, 60);

			//Execute the connection
			$result[data] = curl_exec($testConn);

			//Get the transmission information
			$result[info] = curl_getinfo($testConn);
			$result[error] = curl_error($testConn);
			//print_r($result);
			//Close the connection
			curl_close($testConn);

			//Check for connection success
			return $result;
			if (false) $this->_curlAvailable = CURL_READY;
			else {

			}
		}
	}

	/**
	 * Handles submitting the site to each of the search engines
	 * specified.
	 *
	 */
	function submit() {
		if ($this->_curlAvailable === CURL_READY) {
			$connections = array();
			for ($i=0;$i<count($this->_availableEngines);$i++) {
				$engine = $this->_availableEngines[$i];
				$loc = 'http://www.'.$engine[loc].$this->_submitURI;
				$connection[$i] = curl_init($loc);
				curl_setopt($connection[$i], CURLOPT_HEADER, 1);
				curl_setopt($connection[$i], CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($connection[$i], CURLOPT_TIMEOUT, 5);
			}

			for ($i=0;$i<count($connection);$i++) {
				$engine = & $this->_availableEngines[$i];
				$engine[data] = curl_exec($connection[$i]);
				$engine[result] = curl_getinfo($connection[$i]);
				$engine[error] = curl_error($connection[$i]);
				curl_close($connection[$i]);
			}

			return $this->_availableEngines;
		}
	}
}

/**
 * JM Googlemap Tree Class.
 *
 * @author		Louis Landry, modified by Peter Davies
 * @package		JM Sitemap
 * @since		1.5
 */
class JGooglemapTree extends JTree {
	/**
	 * Node/Id Hash for quickly handling node additions to the tree.
	 */
	var $_nodeHash = array();

	/**
	 * Menu parameters
	 */
	var $_params = null;

	/**
	 * Menu parameters
	 */
	var $_buffer = null;

	function __construct(){
		$this->_root		= new JGooglemapMenuNode(0, 'ROOT');
		$this->_nodeHash[0]	=& $this->_root;
		$this->_current		=& $this->_root;
	}

	function addNode($item){
		// Get menu item data
		$data = $this->_getItemData($item);

		// Create the node and add it
		$node = new JGooglemapMenuNode($item->id, $item->name, $item->access, $data);

		if (isset($item->mid)) {
			$nid = $item->mid;
		} else {
			$nid = $item->id;
		}
		$this->_nodeHash[$nid] =& $node;

		//Messy way to remove the nested menu items
		$this->_current =& $this->_nodeHash[0];

		if ($this->_current) {
			$this->addChild($node, true);
		} else {
			// sanity check
			JError::raiseError( 500, 'Orphan Error. Could not find parent for Item '.$item->id );
		}
	}
	/**
	 * Gets details relating to the articles which are
	 * linked through the menu, currently the creation
	 * and modification timestamps of the articles
	 *
	 * @param unknown_type $artid
	 * @return unknown
	 */
	function getArticleInfo($artid) {
		$db =& JFactory::getDBO();

		$comID = <<<QRY
SELECT
	UNIX_TIMESTAMP(C.created) AS 'created',
	UNIX_TIMESTAMP(C.modified) AS 'modified'
FROM
	#__content AS C
WHERE
	C.id = $artid;
QRY;
		$db->setQuery( $comID );

		$result = $db->loadAssocList();

		return $result;
	}

function getPriorityLevel($artid)
{
	$db =& JFactory::getDBO();

	$query = "Select priority from #__jmsitemap_google where id=$artid";
	$db->setQuery($query);
	$result =& $db->loadResultArray();

	//print_r($result);

	return $result;
}

	/**
	 * Checks the difference between the date of the creation/modification
	 * of an article, and returns an approximate string value for the
	 * sitemap change frequency directive.
	 *
	 * @param int $modDate The creation/modification timestamp
	 * @return string The change frequency title determined
	 */
	function checkChangeFreq ($modDate) {
		$changeFreqs = array(
			-1=>"never",0=>"always",3600=>"hourly",
    		86400=>"daily",604800=>"weekly",
    		2419200=>"monthly",29030400=>"yearly"
		);

		$date = time();

		$difference = $date - $modDate;
		if (!is_null($modDate)) {
			foreach ($changeFreqs as $stamp => $title) {
				if ($difference <= $stamp)
					$change = $title;
				else if ($title = 'yearly' AND $difference > $stamp)
					$change = $changeFreqs[-1];
			}
			if (!isset($change)) $change = $changeFreqs[0];
		} else {
			$change = $changeFreqs[0];
		}
		return $change;
	}

	function toXML(){
		// Initialize variables
		$this->_current =& $this->_root;

		$this->_buffer = "<root>\n";

		// Recurse through children if they exist
		while ($this->_current->hasChildren()){
			foreach ($this->_current->getChildren() as $child)
			{
				$this->_current = & $child;
				$this->_getLevelXML(0);
			}
		}
		if($this->_buffer == '') { $this->_buffer = "\t<url />\n"; }

		$this->_buffer .= "</root>";

		return $this->_buffer;
	}

	function _getLevelXML($depth){
		$depth++;

		// Start the item
		$this->_buffer .= "\t<url>\n";

		// Detect internal and external links using regex
		$result = preg_match('/^http:\/\//',$this->_current->link);

		//Set the link string, and accommodate external links
		$link = ($result > 0) ?
			$this->_current->link :
			"http://".$_SERVER['SERVER_NAME'].$this->_current->link;

		//Add the link
		$this->_buffer .= "\t<loc>".$link."</loc>\n";

		//Get information from articles
		$lastMod = $this->getArticleInfo($this->_current->id);

		//Set up the modification date
		$this->_buffer .= "\t<lastmod>";
		$this->_buffer .= (count($lastMod) > 0)
			?(
				($lastMod[0]['modified'] > 0)
					? date('Y-m-d',$lastMod[0]['modified'])
					: date('Y-m-d',$lastMod[0]['created'])
			 ) : date('Y-m-d');
		$this->_buffer .= "</lastmod>\n";

		//Set the change frequency according to current data
		$changeStr = ($lastMod[0]['modified'] > 0)
			? $this->checkChangeFreq($lastMod[0]['modified'])
			: $this->checkChangeFreq($lastMod[0]['created']);

		//Write to buffer
		$this->_buffer .= "\t<changefreq>".$changeStr."</changefreq>\n";

		//Stub engine priority, set default to 0.5
		$priority = $this->getPriorityLevel($this->_current->id);
		$priority = (empty($priority[0]))? 0.5 : $priority[0];

		$this->_buffer .= "\t<priority>".$priority."</priority>\n";

		// Recurse through item's children if they exist
		while ($this->_current->hasChildren()){

			$this->_buffer .= "\t<url>\n";
			foreach ($this->_current->getChildren() as $child)
			{
				$this->_current = & $child;
				$this->_getLevelXML($depth);
			}
			$this->_buffer .= "\t</url>\n";
		}

		// Finish the item
		$this->_buffer .= "\t</url>\n";

	}

	function _getItemData($item){
		$data = null;

		// Menu Link is a special type that is a link to another item
		if ($item->type == 'menulink'){
			$menu = &JSite::getMenu();
			if ($tmp = clone($menu->getItem($item->query['Itemid']))) {
				$tmp->name	 = '<![CDATA['.$item->name.']]>';
				$tmp->mid	 = $item->id;
				$tmp->parent = $item->parent;
			} else {
				return false;
			}
		} else {
			$tmp = clone($item);
			$tmp->name = '<![CDATA['.$item->name.']]>';
		}

		$iParams = new JParameter($tmp->params);
		if ($iParams->get('menu_image') && $iParams->get('menu_image') != -1) {
			$image = '<img src="images/stories/'.$iParams->get('menu_image').'" alt="" />';
		} else {
			$image = null;
		}
		switch ($tmp->type){
			case 'separator' :
				return '<span class="separator">'.$image.$tmp->name.'</span>';
				break;

			case 'url' :
				if ((strpos($tmp->link, 'index.php?') !== false) && (strpos($tmp->link, 'Itemid=') === false)) {
					$tmp->url = $tmp->link.'&amp;Itemid='.$tmp->id;
				} else {
					$tmp->url = $tmp->link;
				}
				break;

			default :
				$tmp->url = 'index.php?Itemid='.$tmp->id;
				break;
		}

		// Print a link if it exists
		if ($tmp->url != null){
			// Handle SSL links
			$iSecure = $iParams->def('secure', 0);
			if (strcasecmp(substr($tmp->url, 0, 4), 'http') && (strpos($tmp->link, 'index.php?') !== false)) {
				$tmp->url = JRoute::_($tmp->url, true, $iSecure);
			} else {
				$tmp->url = str_replace('&', '&amp;', $tmp->url);
			}

			$data = $tmp->url;

			//print_r($tmp);

		} else {
			$data = '<a>'.$image.$tmp->name.'</a>';
		}

		return $data;
	}
}

/**
 * Main Menu Tree Node Class.
 *
 * @author		Louis Landry, modified by Peter Davies
 * @package		JM Sitemap
 * @since		1.5
 */
class JGooglemapMenuNode extends JNode{
	/**
	 * Node Title
	 */
	var $title = null;

	/**
	 * Node Link
	 */
	var $link = null;

	/**
	 * CSS Class for node
	 */
	var $class = null;

	function __construct($id, $title, $access = null, $link = null, $class = null){
		$this->id		= $id;
		$this->title	= $title;
		$this->access	= $access;
		$this->link		= $link;
		$this->class	= $class;
	}
}
?>