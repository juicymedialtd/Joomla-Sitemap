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

jimport('joomla.application.component.controller');

/**
 * This is the Joomla controller for loading the default
 * sitemap view.
 *
 */
class SitemapController extends JController{

	/**
	 * Method to show the search view
	 *
	 * @access	public
	 * @since	1.5
	 */
	function display()
	{
		// Set a default view if none exists
		if (!JRequest::getCmd('view')) {
			JRequest::setVar('view', 'sitemap' );
		}

		switch (JRequest::getCmd('view')) {
			case 'sitemap':   JRequest::setVar( 'view', 'sitemap' ); break;
			case 'googlemap': JRequest::setVar( 'view', 'googlemap' ); break;
			default: JRequest::setVar( 'view', 'sitemap' ); break;
		}

		parent::display();
	}
}
?>
