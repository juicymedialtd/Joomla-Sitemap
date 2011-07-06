Joomla Sitemap
=============

This is a very simple native sitemap component for Joomla 1.5.X (started on 1.5.4 and tested on 1.5.23).
The component is a BETA release (now at v2) and now has some extremely useful admin functionality.

Features
-------
* native Joomla 1.5
* creates visual sitemap of selected menus
* creates XML sitemap of selected menus

Component Usage
-------
To use this component:
* download this site
* simply install the supplied ZIP file using the Joomla admin area
* after successful installation, create a menu link to the component, selecting the default view.
* Google sitemap generation is presently done by creating a menu link as above and then on the frontend visiting the link.
* this will have now created a "sitemap.xml" file in the root of the Joomla directory (directory and file permissions dependant).
* if required you can now remove the link (or simply unpublish).

Common Issues
-------
* This does not work with Joomla 1.6 yet (if this is urgent for a commercial project contact Juicy Media)
* Having been designed for Joomla 1.5 it does not require legacy mode to be enabled.
* Large menus will consume significant resources during sitemap creation
* This uses cURL for submitting the sitemap to Google
* If no sitemap.xml is created it is always down to permissions and file ownership - try manually making a blank version of the file in the root folder as this might help

Future Enhancements
-------
The current development lists all menus that have been defined in the Joomla admin area, future enhancements include:

* update admin interface to be more complete
* show external links with icon
* optional visual interface of mappings
* full CSS control of the output

CHANGE LOG
-------
Here is a list of known changes, including those from various contributors. Specfically this list covers items that were implemented pre SVN and GIT implementation.

06/07/2011 - Added to GIT
08/06/2011 - Fixed enable/disable menus for google XML (thanks to Hugh Saunders)
09/02/2010 - Recent Joomla update caused infinite loop issue
21/06/2009 - General tidy-up and bug fixes
22/07/2008 - Fixed database prefix issue
07/08/2008 - Fixed date and priority issue
07/11/2008 - Removed all PHP short code tags