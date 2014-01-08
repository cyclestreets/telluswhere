<?php

# Class to implement a website asking visitors to say where public infrastructure changes are needed and to report on existing infrastructure
class telluswhere
{
	# Settings
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName'		=> 'Tell us where',
			'style'					=> 'default',
			'administratorEmail'	=> (isSet ($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : NULL),
			'feedbackRecipient'		=> NULL,
			'contactsPageHtml'		=> false,
			'aboutPageHtml'			=> NULL,
			'termsPageHtml'			=> NULL,
			'defaultLatitude'		=> NULL,
			'defaultLongitude'		=> NULL,
			'defaultZoom'			=> NULL,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	# Register actions
	private function actions ()
	{
		# Specify available actions
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '/',
			),
			'current' => array (
				'description' => false,
				'url' => '/current/',
			),
			'suggest' => array (
				'description' => false,
				'url' => '/suggest/',
			),
			'about' => array (
				'description' => false,
				'url' => '/about/',
			),
			'terms' => array (
				'description' => false,
				'url' => '/terms/',
			),
			'contacts' => array (
				'description' => false,
				'url' => '/contacts/',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	# Class properties
	private $template = array ();	// Associative array of fragments to be replaced
	private $replacedPlaceholders = array ();	// Associative array of placeholder comments which have been replaced
	
	# Cycle parking type presets
	private $parkingTypes = array (
		'stand'				=> 'Sheffield stand',
		'shelter'			=> 'Secure bike shelter',
		'stacker'			=> 'Double decker stand',
		'streetfurniture'	=> 'Integrated Street furniture',
		'insecure'			=> 'Wall hoop',
		'informal'			=> 'Informal (e.g. railings)',
	);
	
	# Land type presets
	private $landTypes = array (
		'highway'			=> 'Public highway',
		'school'			=> 'School',
		'private'			=> 'Private land',
		'unknown'			=> 'Not sure',
	);
	
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Set the include path to include libraries
		set_include_path (get_include_path () . PATH_SEPARATOR . $_SERVER['DOCUMENT_ROOT'] . '/libraries/');
		
		# Load required libraries
		require_once ('application.php');
		
		# Define the location of the stub launching file
		$this->baseUrl = application::getBaseUrl ();
		
		# Function to merge the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults (), __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Determine the style directory in use
		if (!$this->styleDirectory = $this->getStyleDirectory ($this->settings['style'])) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error.</p>";
			echo $this->html;
			return false;
		}
		
		# If a file is requested, serve the file directly, then end
		if (isSet ($_GET['file'])) {
			$this->serveFile ($_GET['file']);
			return;
		}
		
		# End if no action specified
		if (!isSet ($_GET['action']) || !strlen ($_GET['action'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Ensure the action is valid
		$this->action = $_GET['action'];
		$this->actions = $this->actions ();
		if (!isSet ($this->actions[$this->action])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Load the template
		$templateHtml = $this->getTemplateHtml ($this->action);
		
		# Perform the action, which will write into the page template array
		$this->{$this->action} ();
		
		# Render the page
		$html = $this->doTemplateSubstitution ($templateHtml, $this->template);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to determine the style directory in use
	private function getStyleDirectory ($style)
	{
		# Check the existence of the directory
		$location = '/style/' . $style;
		$directory = $_SERVER['DOCUMENT_ROOT'] . $location;
		if (!is_dir ($directory)) {return false;}
		
		# Return the location, not slash-terminated
		return $location;
	}
	
	
	# 404 page
	private function page404 ()
	{
		# Send the header
		application::sendHeader (404);
		
		# Show generic text if custom 404 not available
		$page = '/404.html';
		$path = $this->styleDirectory . $page;
		$file = $_SERVER['DOCUMENT_ROOT'] . $path;
		if (!is_readable ($file)) {
			$html  = "\n<h1>Page not found</h1>";
			$html .= "\n<p>Sorry, that page was not found. Please check the URL or use the menu to navigate elsewhere.</p>";
			return $html;
		}
		
		# Get the HTML
		$html = $this->convertDesignerHtmlToTemplate ($page);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to load the template
	private function getTemplateHtml ($action)
	{
		# Determine the location of the template
		$templateLocation = $this->actions[$action]['url'] . (substr ($this->actions[$action]['url'], -1) == '/' ? 'index.html' : '');	// Convert /path/ to /path/index.html
		
		# Obtain the template
		$html = $this->convertDesignerHtmlToTemplate ($templateLocation);
		
		# Return the template HTML
		return $html;
	}
	
	
	# Function to convert the designer's raw HTML to a templatised HTML page; this is a preprocessor which enables a template to be dropped in from a designer without making changes first
	private function convertDesignerHtmlToTemplate ($page)
	{
		# Determine the location
		$path = $this->styleDirectory . $page;
		$file = $_SERVER['DOCUMENT_ROOT'] . $path;
		
		# Load the file
		$html = file_get_contents ($file);
		
		# Chop trailing directory index; e.g. "/path/index.html" becomes "/path/"
		$html = $this->htmlCleanChopDirectoryIndex ($html);
		
		# Make HTML paths absolute; e.g. "css/" becomes "/css/"
		$html = $this->htmlCleanPathsAbsolute ($html, $path);
		
		# Replace templated sections with placeholders
		$html = $this->commentsToPlaceholders ($html, $this->replacedPlaceholders);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to chop directory indexes from links
	private function htmlCleanChopDirectoryIndex ($html)
	{
		# Define directory index filename(s)
		$supported = array ('index.html', );		// More can be added if found necessary, e.g. index.php
		$supportedDirectoryIndexesRegexp = implode ('|', $supported);
		
		# HTML href links
		$html = preg_replace ('@(\s+)(href)="(' . $supportedDirectoryIndexesRegexp . ')"@', '$1$2="./"', $html);			// Special case: href="index.html" becomes href="./"
		$html = preg_replace ('@(\s+)(href)="([^"]*)/(' . $supportedDirectoryIndexesRegexp . ')"@', '$1$2="$3/"', $html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to rewrite HTML paths to be absolute
	private function htmlCleanPathsAbsolute ($html, $path)
	{
		# Extract all URL references or return HTML as-is)
		#!# No support for single-quoted (var='text') attributes quotes yet
		#!# Will currently catch href="path"/src="path" appearing within the HTML as plain text
		if (!preg_match_all ('@\s+(href|src)="([^"]+)"(\s|>)@', $html, $pathsOriginal, PREG_SET_ORDER)) {return $html;}
		
		# Determine the path prefix that needs to be inserted
		$path = dirname ($path . '.bogus') . '/';	// .bogus ensures that dirname doesn't convert "/foo/bar" (which should not be supplied anyway) to /foo
		$delimiter = '@';
		$prefix = preg_replace ($delimiter . '^' . addcslashes ($this->styleDirectory, $delimiter) . $delimiter, '', $path);	// i.e. replace /style/default/ with /, leaving e.g. /contacts/
		
		# Work through each path to determine the replacement path for each match
		$paths = array ();
		foreach ($pathsOriginal as $i => $match) {
			$paths[$i] = $match[2];		// By default, start with unamended original
			
			# Full URLs should be left unchanged
			if (preg_match ('|^https?://.+$|', $paths[$i])) {continue;}
			
			# Pure anchors should be left changed
			if (preg_match ('|^#.*$|', $paths[$i])) {continue;}
			
			# Current-directory only URLs ( ./ or ./something ) should be substituted with the absolute equivalent
			if ($paths[$i] == '.') {$paths[$i] = './';}	// Normalise
			if (preg_match ('|^\./(.*)$|', $paths[$i], $matches)) {
				$paths[$i] = $prefix . $matches[1];
				continue;
			}
			
			# Directory-traversal URLs - chop prefix for each, i.e. ../contacts/ => /prefix/../contacts/ => /contacts/
			if ($paths[$i] == '..') {$paths[$i] = '../';}	// Normalise
			if (preg_match ('|^\.\./(.*)$|', $paths[$i], $matches)) {
				$newPrefix = $prefix;
				while (preg_match ('|^\.\./(.*)$|', $paths[$i], $matches)) {
					if (strlen ($newPrefix)) {	// Never traverse higher than / - if HTML of ../../ should have been ../ then treat it as such
						$newPrefix = str_replace ('\\', '/', dirname ($newPrefix));	// Chop last component
					}
					$paths[$i] = $newPrefix . $matches[1];
				}
				continue;
			}
			
			# Prefix remainder, which are "from here" paths, e.g. "path/to" becomes "/prefix/path/to"
			$paths[$i] = $prefix . $paths[$i];
		}
		
		# Construct the find/replace entry; $match[1] is href/src; $match[2] is the original path
		$replacements = array ();
		foreach ($pathsOriginal as $i => $match) {
			$find    = sprintf (' %s="%s"', $match[1], $match[2]);
			$replace = sprintf (' %s="%s"', $match[1], $paths[$i]);
			$replacements[$find] = $replace;
		}
		
		# Perform replacements
		$html = strtr ($html, $replacements);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to insert placeholders, by replacing comments in the HTML where the placeholders go; the aim here is that a designer can supply code with both sample HTML and the placeholders in
	# This looks for "<!-- {$placeholdername} --> then lines of HTML here, then <!-- {/$placeholdername} -->"
	private function commentsToPlaceholders ($html, &$replacedPlaceholders = array ())
	{
		# Cache matched placeholder comments; note \1 is a backreference to ensure the opening and closing tags match, and the s modifier enables multiple-line matches
		$regexp = '|' . '<!--\s+\{\$([^}]+)\}\s+-->(.+)<!--\s+/\{\$\1\}\s+-->' . '|s';
		if (preg_match_all ($regexp, $html, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$replacedPlaceholders[$match[1]] = $match[2];		// placeholdername => html
			}
		}
		
		# Do the replacement of placeholder comments with actual placeholders
		$html = preg_replace ($regexp, '{\$\1}', $html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to render the page
	private function doTemplateSubstitution ($templateHtml, $replacements)
	{
		# Convert to Smarty-format placeholders
		$substitutions = array ();
		foreach ($replacements as $find => $replace) {
			$find = '{$' . $find . '}';
			$substitutions[$find] = $replace;
		}
		
		# Perform substitutions
		$html = strtr ($templateHtml, $substitutions);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to take an extracted part of the template and convert to ultimateForm form template format
	private function placeholderHtmlToFormTemplate ($placeholderName)
	{
		# Obtain the form template which was extracted during the template pre-processing
		$htmlBlock = $this->replacedPlaceholders[$placeholderName];
		
		# Extract the HTML between placeholder-comments nested within the form template to leave a standard template for the form
		$template = $this->commentsToPlaceholders ($htmlBlock, $replacedPlaceholders);
		
		# Convert each replaced placeholder to ultimateForm format
		$replacements = array ();
		foreach ($replacedPlaceholders as $placeholder => $originalHtml) {
			$replacements[$placeholder] = '{' . $placeholder . '}';
			if ($placeholder == 'submit') {
				$replacements[$placeholder] = '{[[SUBMIT]]}';
			}
		}
		
		# Substitute the placeholders for the ultimateForm placeholders
		$templateUltimateFormFormat = $this->doTemplateSubstitution ($template, $replacements);
		
		# Strip <form> and </form> tags if present
		$templateUltimateFormFormat = preg_replace ('/<form ([^>]+)>/s', '', $templateUltimateFormFormat);
		$templateUltimateFormFormat = str_replace ('</form>', '', $templateUltimateFormFormat);
		
		# Return the HTML
		return $templateUltimateFormFormat;
	}
	
	
	# Function to serve a file as per a standard webserver
	private function serveFile ($location)
	{
		# Throw 404 if none
		if (!strlen ($location)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Prevent directory traversal attacks
		if (substr_count ($location, '../')) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Ensure page exists
		$file = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . $location;
		if (!is_file ($file) || !is_readable ($file)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Enable caching to improve browser performance; see: http://stackoverflow.com/a/1583753/180733
		$lastModifiedTime = filemtime ($file);
		$etag = md5_file ($file);
		header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s', $lastModifiedTime) . ' GMT');
		header ('Etag: ' . $etag);
	    if (isset ($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset ($_SERVER['HTTP_IF_NONE_MATCH'])) {
			if (strtotime ($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModifiedTime || trim ($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
				header ('HTTP/1.1 304 Not Modified');
				return;
			}
		}
		
		# Ensure the fileinfo extension is loaded
		if (!function_exists ('finfo_open')) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error.</p>";
			echo $this->html;
			return false;
		}
		
		# Set a header for the MIME type of the file
		$mimeType = $this->getMimeType ($file);
		header ('Content-Type: ' . $mimeType);
		
		# Set a header for the length of the file
		header ('Content-Length: ' . filesize ($file));
		
		# Serve the file
		readfile ($file);
	}
	
	
	# Function to get the MIME type; this is basically a wrapper to finfo_file because of a PHP bug; see: http://stackoverflow.com/a/17736797/180733
	private function getMimeType ($file)
	{
		# Workaround for bug with finfo_file(); see http://bugs.php.net/53035
		$extension = pathinfo ($file, PATHINFO_EXTENSION);
		switch ($extension) {
			case 'css':
				return 'text/css';
			case 'js':
				return 'application/javascript';
		}
		
		# Get the MIME type
		$finfo = finfo_open (FILEINFO_MIME_TYPE);
		$mimeType = finfo_file ($finfo, $file);
		
		# Return the MIME type
		return $mimeType;
	}
	
	
	/* Content pages */
	
	
	# Home page
	private function home ()
	{
		# Start the HTML
		$html = '';
		
		# Return the HTML
		return $html;
	}
	
	
	# Suggest a location page
	private function suggest ()
	{
		# Start the HTML
		$html = '';
		
		// #!# TODO
		
		
		# Return the HTML
		return $html;
	}
	
	
	# Page for auditing of current locations
	private function current ()
	{
		# Start the HTML
		$html = '';
		
		# Add the form
		$result = $this->currentLocationsForm ($formHtml);
		$this->template['form'] = $formHtml;
		
		# Add the map
		$this->template['map'] = $this->currentLocationsMap ();
		
		# Send the result to the CycleStreets photo API
		if ($result) {
			
			// TODO
			application::dumpData ($result);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Map of current locations
	private function currentLocationsMap ()
	{
		# Start the HTML
		$html = '';
		
		# By default, no marker is shown
		$setMarkerInitially = false;
		
		# Set default map location
		$mapLocation = array (
			'latitude'	=> $this->settings['defaultLatitude'],
			'longitude'	=> $this->settings['defaultLongitude'],
			'zoom'		=> $this->settings['defaultZoom'],
		);
		
		# If the form is posted, and a map location was set, extract the map location
		#!# This hack is only necessary until ultimateForm has built-in support for a native map widget, which means this whole method can then be replaced
		if (isSet ($_POST['form'])) {
			if (isSet ($_POST['form']['latitude']) && isSet ($_POST['form']['longitude']) && isSet ($_POST['form']['zoom']) && preg_match ('/^[0-9-.]+$/', $_POST['form']['latitude']) && preg_match ('/^[0-9-.]+$/', $_POST['form']['longitude']) && preg_match ('/^[0-9]{1,2}$/', $_POST['form']['zoom'])) {
				$mapLocation = array (
					'latitude'	=> $_POST['form']['latitude'],
					'longitude'	=> $_POST['form']['longitude'],
					'zoom'		=> $_POST['form']['zoom'],
				);
				$setMarkerInitially = true;
			}
		}
		
		# Create the map application HTML
#!# Map width and height needs to be set in CSS using #map rather than #cycle-map
		$html .= '
		<link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.1/leaflet.css" />
		<script src="http://cdn.leafletjs.com/leaflet-0.7.1/leaflet.js"></script>
		<style type="text/css">
			#map {height: 400px;}
			#helptext {margin: 0;}
			#helptext.display {background-color: yellow;}
			#helptext.hide {background-color: transparent;}
		</style>
		
		<p id="helptext">Zoom all the way in, then click on the map to set the marker.</p>
		<div id="map"></div>
		
		';
		
		# Create the map application Javascript
		$setMarkerInitiallyJs = ($setMarkerInitially ? 'true' : 'false');
		$html .= "
		<script type=\"text/javascript\">
			
			var \$j = jQuery.noConflict();
			\$j( document ).ready(function() {
				
				// Set map centre location
				var map = L.map('map').setView([{$mapLocation['latitude']}, {$mapLocation['longitude']}], {$mapLocation['zoom']});
				
				// Initialise a marker
				var marker;
				
				// Set required accuracy for marker setting
				var minZoomLevelToSet = 18;
				
				// Determine whether to set the marker initially
				setMarkerInitially = {$setMarkerInitiallyJs};
				if(setMarkerInitially){
					var latlng = L.latLng({$mapLocation['latitude']}, {$mapLocation['longitude']});
					setMarker(latlng);
					map.setView(latlng,minZoomLevelToSet);
				}
				
				// Set tile layer
				var tileUrl = 'http://{s}.tile.cyclestreets.net/mapnik/{z}/{x}/{y}.png';
				var tileAttribution = 'Map data &copy; <a href=\"http://www.openstreetmap.org/\">OpenStreetMap</a> contributors (<a href=\"http://www.openstreetmap.org/copyright\">ODbL</a>)';
				L.tileLayer(tileUrl, {
					attribution: tileAttribution,
					maxZoom: 18
				}).addTo(map);
				
				// Create marker and popup when clicking on the map
				function onMapClick(e) {
					
					// Show the help text
					\$j('#helptext').addClass('display');
					
					// Remove any marker present
					if(marker){
						map.removeLayer(marker);
					}
					
					// Zoom if too far out and end
					if(map.getZoom() < minZoomLevelToSet){
						setFormValues (null, null, null);	// Clear any saved values
						var currentZoomLevel = map.getZoom();
						var zoomBy = (((minZoomLevelToSet - currentZoomLevel) <= 2) ? 1 : 2);	// When very zoomed in, zoom in less far, to avoid disorientation
						var newZoomLevel = currentZoomLevel + zoomBy;
						// alert('Current zoom: ' + currentZoomLevel + '; zooming by: ' + zoomBy + ' to: ' + newZoomLevel);
						map.setZoomAround(e.latlng, newZoomLevel);
						return;
					}
					
					// Set the marker
					setMarker(e.latlng);
					
					// Remove the help text
					\$j('#helptext').removeClass('display').addClass('hide');
				}
				map.on('click', onMapClick);
				
				// Function to set the marker
				function setMarker(latlng) {
					// Set marker position
					marker = new L.Marker(latlng, {draggable:true});
					map.addLayer(marker);
					marker.bindPopup('Cycle parking is needed here').openPopup();
					
					// Register dragend processing function
					marker.on('dragend', markerDrag);
					
					// Transmit the value to the form
					setFormValues (latlng.lat, latlng.lng, map.getZoom());
				}
				
				// After dragging, transmit the value to the form
				function markerDrag(e){
					setFormValues (e.target._latlng.lat, e.target._latlng.lng, map.getZoom());
				}
				
				// Function to transmit the values to the form
				function setFormValues (lat, lng, zoom){
					\$j('#form_latitude').val(lat);
					\$j('#form_longitude').val(lng);
					\$j('#form_zoom').val(zoom);
				}
				
				// Show the help text also if the user zooms
				map.on('zoomstart', function() {
					\$j('#helptext').addClass('display');
				});
			});
			
		</script>
		";
		
		# Return the HTML
		return $html;
	}
	
	
	# Contact form
	private function currentLocationsForm (&$html = '')
	{
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> 'Many thanks for your submission.',
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . "\n{latitude}\n{longitude}\n{zoom}" . $this->placeholderHtmlToFormTemplate ('form'),
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> 'Submit',
			'submitButtonAccesskey'		=> false,
			'nullText'					=> false,
		));
		
		# Widgets
		$form->select (array (
			'name'			=> 'type',
			'title'			=> 'Type of parking',
			'required'		=> true,
			'values'		=> $this->parkingTypes,
		));
		$form->number (array (
			'name'			=> 'capacity',
			'title'			=> 'How many cycles can be parked?',
			'required'		=> true,
		));
		$form->select (array (
			'name'			=> 'landtype',
			'title'			=> 'Land type',
			'required'		=> true,
			'values'		=> $this->landTypes,
		));
		$form->textarea (array (
			'name'			=> 'message',
			'title'			=> 'Additional info / comments',
			'required'		=> true,
			'rows'			=> 2,
			'cols'			=> 20,
		));
		$form->input (array (
			'name'			=> 'name',
			'title'			=> 'Your name',
			'required'		=> true,
		));
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'Your e-mail address',
			'required'		=> true,
			#!# Needs to prefill e-mail address when logged in
		));
		$form->select (array (
			'name'			=> 'mailinglist',
			'title'			=> 'Would you like to be kept up-to-date via e-mail?',
			'required'		=> true,
			'values'		=> array ('Yes', 'No'),
		));
		$form->checkboxes (array (
			'name'			=> 'terms',
			'title'			=> 'Do you accept our terms & conditions?',
			'required'		=> true,
			'values'		=> array ('Yes'),
			'default'		=> 'Yes',
			'discard'		=> true,
		));
		
		# Location (hidden)
		#!# ultimateForm has multiple bugs for hidden fields when using templating; for now, standard input widgets are used and then hidden using CSS
		$html .= "\n" . '<style type="text/css">
			#form_latitude, #form_longitude, #form_zoom {display: none;}
		</style>
		';
		$form->input (array (
			'name'			=> 'latitude',
			'title'			=> 'Latitude (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
		));
		$form->input (array (
			'name'			=> 'longitude',
			'title'			=> 'Longitude (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
		));
		$form->input (array (
			'name'			=> 'zoom',
			'title'			=> 'Zoom level (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
		));
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if (!strlen ($unfinalisedData['latitude']) || !strlen ($unfinalisedData['longitude']) || !strlen ($unfinalisedData['zoom']) || !preg_match ('/^[0-9-.]+$/', $unfinalisedData['latitude']) || !preg_match ('/^[0-9-.]+$/', $unfinalisedData['longitude']) || !preg_match ('/^[0-9]{1,2}$/', $unfinalisedData['zoom'])) {
				$form->registerProblem ('location', 'The map location needs to be set.');
			}
		}
		
		# Process the form
		if (!$result = $form->process ($html)) {return false;}
		
		# Return the result
		return $result;
	}
	
	
	# About page
	private function about ()
	{
		# Start the HTML
		$html = '';
		
		# Text of page
		$this->template['text'] = $this->settings['aboutPageHtml'];
		
		# Return the HTML
		return $html;
	}
	
	
	# Terms and conditions page
	private function terms ()
	{
		# Start the HTML
		$html = '';
		
		# Text of page
		$this->template['text'] = $this->settings['termsPageHtml'];
		
		# Return the HTML
		return $html;
	}
	
	
	# Contacts page
	private function contacts ()
	{
		# Start the HTML
		$html = '';
		
		# Contact form text
		$this->template['text'] = $this->settings['contactsPageHtml'];
		
		# E-mail address
		$this->template['feedbackRecipient'] = application::encodeEmailAddress ($this->settings['feedbackRecipient']);
		
		# Contact form
		$this->template['form'] = $this->contactForm ();
		
		# Return the HTML
		return $html;
	}
	
	
	# Contact form
	private function contactForm ()
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> "Many thanks for your message - we'll be in touch shortly if applicable.",
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $this->placeholderHtmlToFormTemplate ('form'),
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> 'Send message',
			'submitButtonAccesskey'		=> false,
			'antispam'					=> true,
		));
		
		# Widgets
		$form->textarea (array (
			'name'		=> 'message',
			'title'		=> 'Message',
			'required'	=> true,
			'cols'		=> 55,
		));
		$form->input (array (
			'name'		=> 'name',
			'title'		=> 'Your name',
			'required'	=> true,
		));
		$form->email (array (
			'name'		=> 'email',
			'title'		=> 'E-mail',
			'required'	=> true,
			#!# Needs to prefill e-mail address when logged in
		));
		
		# Set the processing options
		$form->setOutputEmail ($this->settings['feedbackRecipient'], $this->settings['administratorEmail'], $this->settings['applicationName'] . ' contact form', NULL, $replyToField = 'email');
		$form->setOutputScreen ();
		
		# Process the form
		$result = $form->process ($html);
		
		# Return the HTML
		return $html;
	}
}

?>