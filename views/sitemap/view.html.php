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
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.view');
jimport('joomla.base.tree');
jimport('joomla.utilities.simplexml');

/**
 * This is the main JM Sitemap class. Its designed to get the menu listing,
 * loop-through and generate an XML output of the menu using existing
 * Joomla functionality.
 *
 */
class SitemapViewSitemap extends JView {
	/**
	 * Display the default.php file in the sitemap/tmpl directory
	 *
	 * @param string $tpl
	 */
	function display($tpl = null){
		// get menu items
		$menu_data = $this->getAllMenuData();
		$this->set('menu_data', $menu_data);

		parent::display($tpl);
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
			// set the title of the menu as Header 1
			$menu_str .= "<h1>".$menu_item['title']."</h1>";
			// get the XML listing for a given menu type
			$menu_str .= $this->render('jmSitemapXMLCallback',$menu_item['menutype']);
		}
		return $menu_str;
	}

	/**
	 * Get the list of published system menus
	 *
	 * @return array
	 */
	function getMenuList(){
		$db =& JFactory::getDBO();
		
		$query = 'SELECT m.menutype, m.title, jm.ordering FROM #__menu_types AS m, #__jmsitemap_menus as jm where jm.id = m.id and jm.published=1 order by jm.ordering asc';
		
		//$query = 'SELECT m.menutype, m.title' .
				//' FROM #__menu_types AS m';
				//' ORDER BY m.menutype';
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
		$menu = new JSitemapTree();
		$items = &JSite::getMenu();

		// Get Menu Items
		$rows = $items->getItems('menutype', $menu_name);

		// Build Menu Tree root down (orphan proof - child might have lower id than parent)
		$user =& JFactory::getUser();
		$ids = array();
		$ids[0] = true;
		
		// pop the first item until the array is empty
		while ( @!is_null($row = array_shift($rows))){
			if ($row->published == "1")
				if (array_key_exists($row->parent, $ids)) {
					$menu->addNode($row);
					// record loaded parents
					$ids[$row->id] = true;
				} else {
					// no parent yet so push item to back of list
					$db = JFactory::getDBO();
					$query = 'SELECT published,parent FROM #__menu WHERE menutype = \''.$menu_type.'\' AND id = ';
					$db->setQuery($query.$row->parent);
					$result =& $db->loadAssoc();
					$publishResult = 1;
					while ($result['parent'] != "0") {
						if ($result['published'] == "1") {
						 $db->setQuery($query.$result['parent']);
						 $result =& $db->loadAssoc();
						 var_dump($result);
						} else {
						 $publishResult = 0;
						 break;
						}
					}
					if ($publishResult == 1) array_push($rows, $row);
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

		// Get document
		$xml = JFactory::getXMLParser('Simple');
		$xml->loadString($xmls[$menu_name]);
		$doc = &$xml->document;

		$menu	= &JSite::getMenu();
		$active	= $menu->getActive();
		$start	= 0;
		$end	= 99;
		$sChild	= true;
		$path	= array();

		// Get subtree
		if ($start){
			$found = false;
			$root = true;
			$path = $active->tree;
			for ($i=0,$n=count($path);$i<$n;$i++){
				foreach ($doc->children() as $child){
					if ($child->attributes('id') == $path[$i]) {
						$doc = &$child->ul[0];
						$root = false;
						break;
					}
				}
				if (( $i?$i:1 ) == $start) {
					$found = true;
					break;
				}
			}
			if ((!is_a($doc, 'JSimpleXMLElement')) || (!$found) || ($root)) {
				$doc = false;
			}
		}

		if ($doc && is_callable($decorator)) {
			$doc->map($decorator, array('end'=>$end, 'children'=>$sChild));
		}
		return $doc;
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
		$xml = SitemapViewSitemap::getXML($menu_name, $callback);
		if ($xml) {
			// add attributes
			$xml->addAttribute('class', $menu_name.'_class');	// for CSS
			$xml->addAttribute('id', $menu_name.'_menu');	    // for JS if req.

			// show white space?
			$show_whitespace = true;
			return JFilterOutput::ampReplace($xml->toString((bool)$show_whitespace));
		}
	}

	/**
	 * This generates the XML list of menu items
	 *
	 * @param JSitemapMenuNode $node
	 * @param Array $args
	 */
	function jmSitemapXMLCallback(&$node, $args){
		$user	= &JFactory::getUser();
		$menu	= &JSite::getMenu();
		$active	= $menu->getActive();
		$path	= isset($active) ? array_reverse($active->tree) : null;

		if (($args['end']) && ($node->attributes('level') >= $args['end'])) {
			$children = &$node->children();
			foreach ($node->children() as $child)
			{
				if ($child->name() == 'ul') {
					$node->removeChild($child);
				}
			}
		}

		if ($node->name() == 'ul') {
			foreach ($node->children() as $child)
			{
				if ($child->attributes('access') > $user->get('aid', 0)) {
					$node->removeChild($child);
				}
			}
		}

		if (($node->name() == 'li') && isset($node->ul)) {
			$node->addAttribute('class', 'parent');
		}

		if (isset($path) && in_array($node->attributes('id'), $path)){
			if ($node->attributes('class')) {
				$node->addAttribute('class', $node->attributes('class').' active');
			} else {
				$node->addAttribute('class', 'active');
			}
		} else {
			if (isset($args['children']) && !$args['children'])
			{
				$children = $node->children();
				foreach ($node->children() as $child)
				{
					if ($child->name() == 'ul') {
						$node->removeChild($child);
					}
				}
			}
		}

		if (($node->name() == 'li') && ($id = $node->attributes('id'))) {
			if ($node->attributes('class')) {
				$node->addAttribute('class', $node->attributes('class').' item'.$id);
			} else {
				$node->addAttribute('class', 'item'.$id);
			}
		}

		if (isset($path) && $node->attributes('id') == $path[0]) {
			$node->addAttribute('id', 'current');
		} else {
			$node->removeAttribute('id');
		}
		$node->removeAttribute('level');
		$node->removeAttribute('access');
	}
}



/**
 * JM Sitemap Tree Class.
 *
 * @author		Louis Landry, modified by Peter Davies
 * @package		JM Sitemap
 * @since		1.5
 */
class JSitemapTree extends JTree {
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
		$this->_root		= new JSitemapMenuNode(0, 'ROOT');
		$this->_nodeHash[0]	=& $this->_root;
		$this->_current		=& $this->_root;
	}

	function addNode($item){
		// Get menu item data
		$data = $this->_getItemData($item);

		// Create the node and add it
		$node = new JSitemapMenuNode($item->id, $item->name, $item->access, $data);

		if (isset($item->mid)) {
			$nid = $item->mid;
		} else {
			$nid = $item->id;
		}
		$this->_nodeHash[$nid] =& $node;
		$this->_current =& $this->_nodeHash[$item->parent];

		if ($this->_current) {
			$this->addChild($node, true);
		} else {
			// sanity check
			JError::raiseError( 500, 'Orphan Error. Could not find parent for Item '.$item->id );
		}
	}

	function toXML(){
		// Initialize variables
		$this->_current =& $this->_root;

		// Recurse through children if they exist
		while ($this->_current->hasChildren()){
			$this->_buffer .= '<ul>';
			foreach ($this->_current->getChildren() as $child)
			{
				$this->_current = & $child;
				$this->_getLevelXML(0);
			}
			$this->_buffer .= '</ul>';
		}
		if($this->_buffer == '') { $this->_buffer = '<ul />'; }
		return $this->_buffer;
	}

	function _getLevelXML($depth){
		$depth++;

		// Start the item
		$this->_buffer .= '<li access="'.$this->_current->access.'" level="'.$depth.'" id="'.$this->_current->id.'">';

		// Append item data
		$this->_buffer .= $this->_current->link;

		// Recurse through item's children if they exist
		while ($this->_current->hasChildren()){
			$this->_buffer .= '<ul>';
			foreach ($this->_current->getChildren() as $child)
			{
				$this->_current = & $child;
				$this->_getLevelXML($depth);
			}
			$this->_buffer .= '</ul>';
		}

		// Finish the item
		$this->_buffer .= '</li>';
	}

	function _getItemData($item){
		$data = null;

		// Menu Link is a special type that is a link to another item
		if ($item->type == 'menulink'){
			$menu = &JSite::getMenu();
			if ($tmp = clone($menu->getItem($item->query['Itemid']))) {
				$tmp->name	 = '<span><![CDATA['.$item->name.']]></span>';
				$tmp->mid	 = $item->id;
				$tmp->parent = $item->parent;
			} else {
				return false;
			}
		} else {
			$tmp = clone($item);
			$tmp->name = '<span><![CDATA['.$item->name.']]></span>';
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

			switch ($tmp->browserNav){
				default:
				case 0:
					// _top
					$data = '<a href="'.$tmp->url.'">'.$image.$tmp->name.'</a>';
					break;
				case 1:
					// _blank
					$data = '<a href="'.$tmp->url.'" target="_blank">'.$image.$tmp->name.'</a>';
					break;
				case 2:
					// window.open
					$attribs = 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes';

					// hrm...this is a bit dickey
					$link = str_replace('index.php', 'index2.php', $tmp->url);
					$data = '<a href="'.$link.'" onclick="window.open(this.href,\'targetWindow\',\''.$attribs.'\');return false;">'.$image.$tmp->name.'</a>';
					break;
			}
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
class JSitemapMenuNode extends JNode{
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