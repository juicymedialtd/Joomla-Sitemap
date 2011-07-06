<?php 
defined ('_JEXEC') or die ( 'Restricted access');
class TOOLBAR_jmsitemap{
	
	function _DEFAULT(){
		JToolbarHelper::title (JText::_( 'JM Sitemap'),'generic.png');
		JToolbarHelper::cancel();
	}
}
?>