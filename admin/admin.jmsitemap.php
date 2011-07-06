<?php
defined( '_JEXEC') or die (' Restricted access');
require_once( JApplicationHelper::getPath('admin_html'));
$cid 	= JRequest::getVar('cid', array(0), 'post', 'array');
JArrayHelper::toInteger($cid);


switch($task)
{
	case 'publish':
		publishToggle('pub', $cid);
		break;
		
	case 'unpublish':
		publishToggle('unpub', $cid);
		break;

	case 'saveorder':
		saveOrder($cid);
		break;
	
	case 'savepriority':
		savePriority($cid);
		break;
	
	case 'cancel':
		$msg = "Operation Cancelled";
		$mainframe->redirect( 'index.php', $msg );
		break;
		
	case 'googlesitemap':
		showGoogleXML($option);
		break;
		
	case 'settings':
		echo 'Under Development';
		break;
	
	default:
		showMenus($option);
		echo $task;
		break;
} 

function showGoogleXML($option)
{
	global $mainframe;
	
	checkMenu('google');
	
	$db =& JFactory::getDBO();
	$query = "SELECT google.id, m.name, google.priority from #__jmsitemap_google as google, #__menu as m where m.id = google.id and m.published=1";
	$db->setQuery($query);
	$rows = $db->loadObjectList();
	if($db->getErrorNum()){
		echo $db->stderr();
		return false;
	}
	
	HTML_jmsitemap::showGoogleXML($option, $rows);
}

function showMenus($option)
{
	@checkMenu();

	$db =& JFactory::getDBO();
	$query = "SELECT #__jmsitemap_menus.id, #__menu_types.title, #__jmsitemap_menus.ordering, #__jmsitemap_menus.published  from #__menu_types, #__jmsitemap_menus where #__menu_types.id = #__jmsitemap_menus.id order by #__jmsitemap_menus.ordering asc";
	$db->setQuery($query);
	$rows = $db->loadObjectList();
	if($db->getErrorNum()){
		echo $db->stderr();
		return false;
	}
	
	HTML_jmsitemap::showMenus( $option, $rows);
}

function checkMenu($option)
{
	$db =& JFactory::getDBO();
	//perform basic check to see if we need to update the menu list
	if($option == 'google')
	{
		$query = "select count(*) from #__jmsitemap_google";
	}
	else 
	{
		$query = "select count(*) from #__jmsitemap_menus";
	}
		
	
	$db->setQuery($query);
	$current_items = $db->loadResult();
	
	if($option == 'google')
	{
		$query = "select count(*) from #__menu where published=1";
	}
	else
	{
		$query = "select count(*) from #__menu_types";
	}

	$db->setQuery($query);
	$new_items = $db->loadResult();
	
if($new_items > $current_items)
{

	//get menu items currently stored in sitemap db table
	if($option == 'google')
	{
		$query = "SELECT m.id from #__menu as m, #__jmsitemap_google as google where m.id = google.id and m.published=1";
	}
	else
	{
		$query = "SELECT #__menu_types.id from #__menu_types, #__jmsitemap_menus where #__menu_types.id = #__jmsitemap_menus.id";
	}
	$db->setQuery($query);
	$menu_mods = $db->loadResultArray();
	//print_r($menu_mods);
	
	//check module table for any new items
	if($option == 'google')
	{
		$query = "SELECT id from #__menu where published=1";
	}
	else
	{
		$query = "SELECT id from #__menu_types";	
	}
	$db->setQuery($query);
	$updated_list = $db->loadResultArray();
	
	$t = count($updated_list);
	
	$j = 0;
	for($i=0; $i < $t; $i++)
	{
		if(!in_array($updated_list[$i], $menu_mods))
		{
			$new_menus[$j] = $updated_list[$i]; 
			$j++;
		}
		else 
		{
			continue;
		}
	}

	//finally insert the new items to the sitemap table
	$nt = count($new_menus);
	
	for($i=0; $i < $nt; $i++)
	{
		//get the highest order number currently in the sitemap table
		if($option == 'google')
		{
			$query = "select max(priority) from #__jmsitemap_google";
		}
		else 
		{
			$query = "select max(ordering) from #__jmsitemap_menus";
		}
		
		$db->setQuery($query);
		$maxordering = $db->loadResult();
		
		if(empty($maxordering))
		{
			$maxordering = 0;
		}
		//increment order number
		$newordernum = ++$maxordering;

		if($option == 'google')
		{
			$query = "insert into #__jmsitemap_google set id=$new_menus[$i], priority=0.5";
		}
		else
		{
			$query = "insert into #__jmsitemap_menus set id=$new_menus[$i], published=0, ordering=$newordernum";
		}
		$db->setQuery($query);
		$db->query();	
	}
}
	
}

function saveOrder(&$cid)
{
	global $mainframe;
	
	$db =& JFactory::getDBO();
	$total		= count( $cid );
	$order 		= JRequest::getVar( 'order', array(0), 'post', 'array' );
	JArrayHelper::toInteger($order, array(0));
	
	for($i = 0; $i < $total; $i++)
	{
		$query = "update #__jmsitemap_menus set ordering=$order[$i] where id=$cid[$i]";
		$db->setQuery($query);
		$db->query();
	}
	if($i == $total)
	{
			$msg 	= 'New ordering saved';
			$mainframe->redirect( 'index.php?option=com_jmsitemap', $msg );
	}
	
}

function savePriority(&$cid)
{
	global $mainframe;
	
	$db =& JFactory::getDBO();
	$total		= count( $cid );
	$priority		= JRequest::getVar( 'priority', array(0), 'post', 'array' );
	//JArrayHelper::toInteger($priority, array(0));

	
	for($i = 0; $i < $total; $i++)
	{
		if($priority[$i] < 0.0 || $priority[$i] > 1)
		{
			$msg = 'Priority value cannot exceed 1.0 or be less than 0.0';
			$mainframe->redirect('index.php?option=com_jmsitemap&task=googlesitemap', $msg, 'error');
			break;	
		}
		else
		{
			$query = "update #__jmsitemap_google set priority=$priority[$i] where id=$cid[$i]";
			$db->setQuery($query);
			$db->query();
		}
	}
	if($i == $total)
	{
			$msg 	= 'New priority ordering saved';
			$mainframe->redirect( 'index.php?option=com_jmsitemap&task=googlesitemap', $msg );
	}
	
}

function publishToggle($option, $cid)
{
		global $mainframe;	
	
		$db =& JFactory::getDBO();
		if($option == 'pub')
		{
			$query = "update #__jmsitemap_menus set published=1 where id=$cid[0]";
		}
		else if($option == 'unpub')
		{
			$query = "update #__jmsitemap_menus set published=0 where id=$cid[0]";
		}
		$db->setQuery($query);
		
		echo $query;
		if($db->query())
		{
			$mainframe->redirect( 'index.php?option=com_jmsitemap' );
		}
}

?>