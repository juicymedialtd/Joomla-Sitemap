<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once( JApplicationHelper::getPath( 'toolbar_html' ) );

switch ( $task )
{
	default:
		TOOLBAR_jmsitemap::_DEFAULT();
		break;
}
?>