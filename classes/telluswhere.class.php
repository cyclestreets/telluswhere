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
			'apiBase'				=> 'https://api.cyclestreets.net',
			'cssFileLocation'		=> NULL,
			'apiKey'				=> NULL,
			'administratorEmail'	=> (isSet ($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : NULL),
			'defaultLatitude'		=> NULL,
			'defaultLongitude'		=> NULL,
			'defaultZoom'			=> NULL,
			'username'				=> NULL,
			'password'				=> NULL,
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
			'suggest' => array (
				'description' => false,
				'url' => '/suggest/',
				'apiUrl' => '/v2/photos?category=cycleparking&metacategory=bad&limit=150&thumbnailsize=200&fields=id,name,hasPhoto,thumbnailUrl',
				'metacategory' => 'bad',
				'additionalMetadata' => 'landtype',
			),
			'current' => array (
				'description' => false,
				'url' => '/current/',
				'apiUrl' => '/v2/pois?type=cycleparking&limit=40',
				'metacategory' => 'other',
				'additionalMetadata' => 'landtype,type,capacity',
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
			'data' => array (
				'description' => false,
				'url' => '/data/',
				#!# Change to administrator when permissions system in place
				'authentication' => true,
			),
			'download' => array (
				'description' => false,
				'url' => false,
				#!# Change to administrator when permissions system in place
				'authentication' => true,
			),
			'admin' => array (
				'description' => false,
				'url' => '/admin/',
				#!# Change to administrator when permissions system in place
				'authentication' => true,
			),
			'login' => array (
				'description' => false,
				'url' => '/login/',
				'apiUrl' => '/v2/user.validate',
			),
			'logout' => array (
				'description' => false,
				'url' => '/logout/',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	# Class properties
	private $html = '';
	private $databaseConnection = NULL;
	private $forcedAction = false;
	private $template = array ();	// Associative array of fragments to be replaced
	private $replacedPlaceholders = array ();	// Associative array of placeholder comments which have been replaced
	private $tmpDirectory = '/tmp/';
	
	# Cycle parking type presets
	private $parkingTypes = array (
		'stand'				=> 'Sheffield stand',
		'shelter'			=> 'Secure bike shelter',
		'stacker'			=> 'Double decker stand',
		'streetfurniture'	=> 'Integrated Street furniture',
		'insecure'			=> 'Old-style wall hoop',
	);
	
	# Land type presets
	private $landTypes = array (
		'highway'			=> 'Public highway',
		'redroute'			=> 'Red route',
		'workplace'			=> 'Workplace',
		'private'			=> 'Private land',
		'station'			=> 'Station',
		'school'			=> 'School',
		'park'				=> 'Park',
		'riverpier'			=> 'Riverside pier',
		'unknown'			=> 'Not sure',
	);
	
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Set the include path to include libraries
		set_include_path ($_SERVER['DOCUMENT_ROOT'] . '/libraries/' . PATH_SEPARATOR . get_include_path ());
		set_include_path ($_SERVER['DOCUMENT_ROOT'] . '/_fckeditor/' . PATH_SEPARATOR . get_include_path ());
		
		# Load required libraries
		require_once ('application.php');
		
		# Define the location of the stub launching file
		$this->baseUrl = application::getBaseUrl ();
		
		# Function to merge the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$fixedSettings = application::assignArguments ($errors, $settings, $this->defaults (), __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Add additional settings from the database, ensuring the database is set up
		if (!$this->settings = $this->getSettings ($fixedSettings)) {
			$this->html .= "\n<p class=\"warning\">The website could not be set up due to a configuration error. Please check back shortly.</p>";
			echo $this->html;
			return false;
		}
		
		# Determine the tmp directory in use for file uploads and ensure it is writeable
		if (!$this->tmpDirectory = $this->getWritableDirectory ($this->tmpDirectory)) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error. Please check back shortly.</p>";
			echo $this->html;
			return false;
		}
		
		# Determine the style directory in use
		if (!$this->styleDirectory = $this->getStyleDirectory ($this->settings['style'])) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error. Please check back shortly.</p>";
			echo $this->html;
			return false;
		}
		
		# Register standard placeholder substitutions
		$this->template['date'] = date ('Y');
		
		# If a file is requested, serve the file directly, then end
		if (isSet ($_GET['file'])) {
			if ($this->serveFile ($_GET['file'])) {
				return;		// End all processing as the content has now been delivered
			}
		}
		
		# Get the user's details, if authenticated
		$this->user = $this->getUser ();
		
		# End if no action specified
		if (!isSet ($_GET['action']) || !strlen ($_GET['action'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Ensure the action is valid
		$this->action = $_GET['action'];
		if ($this->forcedAction) {$this->action = $this->forcedAction;}
		$this->actions = $this->actions ();
		if (!isSet ($this->actions[$this->action])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Require authentication if specified
		if (isSet ($this->actions[$this->action]['authentication'])) {
			if (!$this->user) {
				$this->action = 'login';
			}
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
	
	
	# Function to add additional settings from the database, ensuring the database is set up
	private function getSettings ($settings)
	{
		# Ensure the server has PDO SQLite support (usually enabled using "extension=php_pdo_sqlite.ext" in php.ini)
		if (!extension_loaded ('pdo_sqlite')) {return false;}
		
		# Ensure the database directory exists and is writable
		$databaseFolder = '/db/';
		if (!$databaseDirectory = $this->getWritableDirectory ($databaseFolder)) {return false;}
		
		# Connect to the database, or create it if it does not yet exist (for PDO SQLite, a connection will attempt to create the file if it does not exist)
		$databaseFile = $databaseDirectory . 'db.sqlite';
		$databaseExists = is_readable ($databaseFile);
		require_once ('database.php');
		$this->databaseConnection = new database ('main', NULL, NULL, $databaseFile, $vendor = 'sqlite');
		
		# Create the structure if required
		if (!$databaseExists) {
			$this->createDatabaseStructure ($databaseFile);
		}
		
		# Obtain the settings; if none, force showing the settings form for first-run and return the fixed settings unmodified
		if (!$databaseSettings = $this->databaseConnection->selectOne ('main', 'settings', array ('id' => 1))) {
			$this->forcedAction = 'admin';
			return $settings;
		}
		
		# Add in the database settings
		$settings = array_merge ($settings, $databaseSettings);
		
		# Return the settings
		return $settings;
	}
	
	
	# Function to bootstrap the database structure
	private function createDatabaseStructure ($databaseFile)
	{
		# Define the table structure; note the SQLite format comments: http://stackoverflow.com/questions/7426205/
		$query = "
			CREATE TABLE IF NOT EXISTS main.settings (
			  `id` INTEGER PRIMARY KEY,						-- Site number
			  `url` VARCHAR(255) NOT NULL,					-- URL of site (match)
			  `style` VARCHAR(255) NOT NULL,				-- Style
			  `feedbackRecipient` VARCHAR(255) NOT NULL,	-- Contact page form recipient
			  `contactsPageHtml` TEXT NOT NULL,				-- Contact page text
			  `termsPageHtml` TEXT NOT NULL,				-- Terms page text
			  `aboutPageHtml` TEXT NOT NULL,				-- About page text
			  `earliestDate` DATE,							-- Earliest date to appear in export
			  `bbox` VARCHAR(225) NOT NULL					-- Bounding box for export
			);
		";
		
		# Create the table structure
		$this->databaseConnection->query ($query);
	}
	
	
	# Function to get the available styles
	private function getStyles ()
	{
		# Get the folders in the directory
		$directory = $_SERVER['DOCUMENT_ROOT'] . '/style/';
		require_once ('directories.php');
		$folderNames = directories::listContainedDirectories ($directory, array (), '^([a-zA-Z0-9]+)$');
		
		# Return the list
		return $folderNames;
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
	
	
	# Function to determine the existence of a specified writable directory
	private function getWritableDirectory ($folder)
	{
		# Check the existence of the directory
		$directory = $_SERVER['DOCUMENT_ROOT'] . $folder;
		if (!is_dir ($directory)) {return false;}
		
		# Ensure it is writeable
		if (!is_writeable ($directory)) {return false;}
		
		# Return the location, not slash-terminated
		return $directory;
	}
	
	
	# 404 page
	private function page404 ()
	{
		# Send the header
		application::sendHeader (404);
		
		# Show generic text if custom 404 not available
		$page = '/404.html';
		
		# Get the template
		$templateHtml = $this->convertDesignerHtmlToTemplate ($page);
		
		# Get the HTML
		$html = $this->doTemplateSubstitution ($templateHtml, $this->template);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to load the template
	private function getTemplateHtml ($action)
	{
		# Do not attempt to fetch a template if no URL specified
		if (!$this->actions[$action]['url']) {return false;}
		
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
		
		# If the file does not exist, fall back to the default (which will exist, as it is part of the repository)
		if (!is_readable ($file)) {
			$this->styleDirectory = $this->getStyleDirectory ('default');
			$path = $this->styleDirectory . $page;
			$file = $_SERVER['DOCUMENT_ROOT'] . $path;
		}
		
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
			
			# Absolute URLs should be left unchanged
			if (preg_match ('|^/.+$|', $paths[$i])) {continue;}
			
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
			if ($placeholder == 'map') {
				$replacements[$placeholder] = $this->locationsMap ();
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
			return false;	// Front controller will go on to serve a 404
		}
		
		# Prevent directory traversal attacks
		if (substr_count ($location, '../')) {
			return false;	// Front controller will go on to serve a 404
		}
		
		# Ensure page exists
		$file = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . $location;
		if (!is_file ($file) || !is_readable ($file)) {
			return false;	// Front controller will go on to serve a 404
		}
		
		# Enable caching to improve browser performance; see: http://stackoverflow.com/a/1583753/180733
		$lastModifiedTime = filemtime ($file);
		$etag = md5_file ($file);
		header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s', $lastModifiedTime) . ' GMT');
		header ('Etag: ' . $etag);
	    if (isset ($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset ($_SERVER['HTTP_IF_NONE_MATCH'])) {
			if (strtotime ($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModifiedTime || trim ($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
				header ('HTTP/1.1 304 Not Modified');
				return true;
			}
		}
		
#!# Move this check to constructor - should not have part of site working but not all
		# Ensure the fileinfo extension is loaded
		if (!function_exists ('finfo_open')) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error. Please check back shortly.</p>";
			echo $this->html;
			return true;
		}
		
		# Set a header for the MIME type of the file
		$mimeType = $this->getMimeType ($file);
		header ('Content-Type: ' . $mimeType);
		
		# Set a header for the length of the file
		header ('Content-Length: ' . filesize ($file));
		
		# Serve the file
		readfile ($file);
		
		# Signal success
		return true;
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
		
		# Show the submission page
		$html = $this->submissionPage ($current = false);
		
		# Register the HTML
		$this->template['form'] = $html;
	}
	
	
	# Page for auditing of current locations
	private function current ()
	{
		# Start the HTML
		$html = '';
		
		# Show the submission page
		$html = $this->submissionPage ($current = true);
		
		# Register the HTML
		$this->template['form'] = $html;
	}
	
	
	# Submission page logic
	private function submissionPage ($current = false)
	{
		# Start the HTML
		$html = '';
		
		# Create the form and process the data
		if (!$data = $this->locationSubmissionForm ($current, $html)) {		// &html written into by reference
			return $html;
		}
		
		# Send the data (including any image) to the API
		if (!$result = $this->postSubmission ($data, $this->action, $error)) {
			$html = $error;
			return $html;
		}
		
		# Unpack the response
		$result = json_decode ($result, true);
		
		# Thank the user
		$html  = "\n<p><strong>Thank you for your submission</strong>, which is number {$result['id']}.</p>";
		$html .= "\n<p><a href=\"{$this->actions[$this->action]['url']}\">Add another?</a></p>";
		
		// Mailing list addition - uses mailinglist,name,email fields
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to post submissions to the API
	private function postSubmission ($rawdata, $action, &$error = '')
	{
		# Define the API URL; note this uses a POST operation due to the presence of a username and password
		$apiUrl = $this->settings['apiBase'] . '/v2/photomap.add' . '?key=' . $this->settings['apiKey'];
		
		# Assemble the additional metadata
		$additionalMetadataFields = explode (',', $this->actions[$action]['additionalMetadata']);
		$additionalMetadata = application::arrayFields ($rawdata, $additionalMetadataFields);
		
		# Map the fields to the API
		$data = array (
			#!# Currently a fixed username/password
			'username'				=> $this->settings['username'],
			'password'				=> $this->settings['password'],
			'metacategory'			=> $this->actions[$action]['metacategory'],
			'category'				=> 'cycleparking',
			'caption'				=> $rawdata['message'],
			'latitude'				=> $rawdata['latitude'],
			'longitude'				=> $rawdata['longitude'],
			'zoom'					=> $rawdata['zoom'],
			'basemap'				=> 'mapnik',
			'credit'				=> $rawdata['name'] . ' <' . $rawdata['email'] . '>',
			'additionalMetadata'	=> json_encode ($additionalMetadata),
		);
		
		# Add the mediaupload field if a file has been submitted
		if ($rawdata['file']) {
			$filePath = $this->tmpDirectory . $rawdata['file'];
			if (function_exists ('curl_file_create')) {
				$mediaupload = curl_file_create ($filePath);	// Modern method, avoids CURL deprecation warnings from PHP 5.5+
			} else {
				$mediaupload = '@' . $filePath;	// Deprecated method using @ symbol - see: http://stackoverflow.com/a/4270282/180733
			}
			$data['mediaupload'] = $mediaupload;
		}
		
		# Post the file
		$result = application::file_post_contents ($apiUrl, $data, true, $error);
		
		# Report any transport error
		if ($error) {
			// echo $error;	// Debugging
			$error = 'Sorry, a technical error occured - please try again later.';
			return false;
		}
		
		# Delete the temporary file if a file was uploaded
		if ($rawdata['file']) {
			unlink ($filePath);
		}
		
		# Return the result
		return $result;
	}
	
	
	# Map of current locations
	private function locationsMap ()
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
		
		# Determine the URL for the browsing API
		#!# Improve way key is added here
		$browsingApiUrl = $this->settings['apiBase'] . $this->actions[$this->action]['apiUrl'] . '&key=' . $this->settings['apiKey'];
		
		# Create the map application HTML
		$html .= '
		<link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.1/leaflet.css" />
		<script src="http://cdn.leafletjs.com/leaflet-0.7.1/leaflet.js"></script>
		<style type="text/css">
			#helptext {margin: 0;}
			#helptext.display {background-color: yellow;}
			#helptext.hide {background-color: transparent;}
			input.ui-autocomplete-loading {background: white url(\'/images/ui-anim_basic_16x16.gif\') right center no-repeat;}
			.leaflet-popup-content-wrapper {width: 250px;}
			.placeholderbubble p.caption {padding-left: 20px;}
			.leaflet-popup-content p {margin-bottom: 5px;}
			.placeholderbubble p.caption:before {color: #900; content: "\201C"; /* http://monc.se/kitchen/129/rendering-quotes-with-css */ font-family: Arial, Helvetica, sans-serif; font-size: 4.5em; font-weight: bold; line-height: 0; margin: 0 5px 0 -10px; vertical-align: bottom;}
			
			/* \'Lines\' table style */
			table.lines {border-collapse: collapse; /* width: 95%; */}
			.lines td, .lines th {border-bottom: 1px solid #e9e9e9; padding: 6px 8px 2px 1px; vertical-align: top; text-align: left;}
			.lines tr:first-child {border-top: 1px solid #e9e9e9;}
			table.lines td.value p:first-child {margin-top: 0;}
			table.lines td.value p:last-child {margin-bottom: 0;}
			table.lines td:last-child ul:first-child {margin-top: 0;}
			table.lines td:last-child ul:first-child li:first-child {margin-top: 0;}
			table.compressed td {padding-top: 1px; padding-bottom: 1px;}
		</style>
		
		<p id="helptext">Zoom all the way in, then click on the map to set the marker.</p>
		<div id="map"></div>
		
		';
		
		# Create the map application Javascript
		$setMarkerInitiallyJs = ($setMarkerInitially ? 'true' : 'false');
		$html .= "
		<script type=\"text/javascript\" src=\"/js/jquery.exif.js\"></script>
		<script type=\"text/javascript\">
			
			jQuery( document ).ready(function( $ ) {
				
				/* Settings */
				
				// Determine the API endpoint to use for browsing
				var browsingApiUrl = '{$browsingApiUrl}';
				
				// Set the icon to use
				var useIcon = '{$this->action}';
				
				
				/* Core map functions */
				
				// Set map centre location
				map = L.map('map').setView([{$mapLocation['latitude']}, {$mapLocation['longitude']}], {$mapLocation['zoom']});
				
				// Initialise a marker
				var marker;
				
				// Set required accuracy for marker setting
				var minZoomLevelToSet = 18;
				
				// Define the icons; see: http://leafletjs.com/examples/custom-icons.html
				var largeIcon = L.Icon.extend({
					options: {
						shadowUrl: '/images/markers/shadow-large.png',
						iconSize:     [34, 40],
						shadowSize:   [51, 38],
						iconAnchor:   [17, 40],
						shadowAnchor: [0, 38],
						popupAnchor:  [0, -36]
					}
				});
				var smallIcon = L.Icon.extend({
					options: {
						shadowUrl: '/images/markers/shadow-small.png',
						iconSize:     [27, 30],
						shadowSize:   [43, 34],
						iconAnchor:   [13, 30],
						shadowAnchor: [0, 34],
						popupAnchor:  [0, -26]
					}
				});
				var icons = {
					suggest: new largeIcon({iconUrl: '/images/markers/suggest.png'}),
					current: new largeIcon({iconUrl: '/images/markers/current.png'}),
					already: new smallIcon({iconUrl: '/images/markers/already.png'})
				};
				
				// Determine whether to set the marker initially
				setMarkerInitially = {$setMarkerInitiallyJs};
				if(setMarkerInitially){
					var latlng = L.latLng({$mapLocation['latitude']}, {$mapLocation['longitude']});
					setMarker(latlng);
					map.setView(latlng,{$mapLocation['zoom']});
				}
				
				// Set tile layer
				var tileUrl = 'http://{s}.tile.cyclestreets.net/mapnik/{z}/{x}/{y}.png';
				var tileAttribution = 'Map data &copy; <a href=\"http://www.openstreetmap.org/\">OpenStreetMap</a> contributors (<a href=\"http://www.openstreetmap.org/copyright\">ODbL</a>)';
				var maxZoom = 18;
				L.tileLayer(tileUrl, {
					attribution: tileAttribution,
					maxZoom: maxZoom
				}).addTo(map);
				
				// Create marker and popup when clicking on the map
				function onMapClick(e) {
					
					// Show the help text
					\$('#helptext').addClass('display');
					
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
					\$('#helptext').removeClass('display').addClass('hide');
				}
				map.on('click', onMapClick);
				
				// Wrapper function to set the marker by supplying raw latitude and longitude markers
				function setMarkerLatitudeLongitude(latitude, longitude) {
					var latlng = L.latLng(latitude, longitude);
					map.setView(latlng, maxZoom);
					setMarker(latlng);
				}
				
				// Function to set the marker
				function setMarker(latlng) {
					// Set marker position
					marker = new L.Marker(latlng, {icon: icons[useIcon], draggable: true, zIndexOffset: 1000});
					map.addLayer(marker);
					marker.bindPopup('Cycle parking is needed here').openPopup();
					
					// Register dragend processing function
					marker.on('dragend', markerDrag);
					
					// Transmit the value to the form
					setFormValues (latlng.lat, latlng.lng, map.getZoom());
				}
				
				// After dragging, transmit the value to the form, and reopen the popup
				function markerDrag(e){
					setFormValues (e.target._latlng.lat, e.target._latlng.lng, map.getZoom());
					marker.openPopup();
				}
				
				// Function to transmit the values to the form
				function setFormValues (lat, lng, zoom){
					\$('#form_latitude').val(lat);
					\$('#form_longitude').val(lng);
					\$('#form_zoom').val(zoom);
				}
				
				// Show the help text also if the user zooms
				map.on('zoomstart', function() {
					\$('#helptext').addClass('display');
				});
				
				
				/* EXIF image marker setting functions */
				
				// Register function for adding to map
				var exifCallback = function(exifObject) {
					if(marker){
						map.removeLayer(marker);
					}
					geolocationData = extractGeolocationData(exifObject);
					if(geolocationData) {
						setMarkerLatitudeLongitude(geolocationData.latitude, geolocationData.longitude);
					}
					//console.log(exifObject);
				}
				try {
					\$('#form_file_0').change(function() {
						\$(this).fileExif(exifCallback);
					});
				}
				catch (e) {
					alert(e);
				}
				
				// Function to convert the complex EXIF geolocation data structure into standard lat,lon,bearing; see: https://confluence.videoplaza.org/display/BLOG/2012/07/22/Geolocation+data+from+Images
				function extractGeolocationData (exifObject) {
					
					// End if no data
					var aLat = exifObject.GPSLatitude;
					var aLon = exifObject.GPSLongitude;
					if (!aLat || !aLon) {return;}
					
					// Convert from minutes/seconds/degrees to decimal
					var strLatRef = exifObject.GPSLatitudeRef || 'N';
					var strLongRef = exifObject.GPSLongitudeRef || 'W';
					var latitude = (aLat[0] + aLat[1]/60 + aLat[2]/3600) * (strLatRef == 'N' ? 1 : -1);
					var longitude = (aLon[0] + aLon[1]/60 + aLon[2]/3600) * (strLongRef == 'W' ? -1 : 1);
					
					// Assemble the object to be returned
					var geolocationData = new Array;
					geolocationData['latitude'] = latitude;
					geolocationData['longitude'] = longitude;
					
					// Return the object
					return geolocationData;
				}
				
				
				/* Existing locations browsing functions; see: http://chris-osm.blogspot.co.uk/2013/11/using-leaflet-with-database.html */
				" . '
				function nl2br (str, is_xhtml) {
					var breakTag = (is_xhtml || typeof is_xhtml === \'undefined\') ? \'<br />\' : \'<br>\';
					return (str + \'\').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, \'$1\' + breakTag + \'$2\');
				}' . "
				
				// Define HTML to be used in the popup (suggest)
				function popupHtmlSuggest(properties) {
					
					var html = ''
					+ '<div class=\"' + (properties.hasPhoto == 'yes' ? 'photo' : 'placeholder') + 'bubble' + '\">'
					// + '<p class=\"metadata small\">#<strong>' + properties.id + '</strong>' + '</p>'
					+ '<p class=\"caption\">' + nl2br(properties.name,true) + '</p>'
					+ (properties.hasPhoto == 'yes' ? '<img src=\"' + properties.thumbnailUrl + '\" alt=\"Image\" />' : '')
					+ '</div>';
					
					// Return HTML
					return html;
				}
				
				// Define HTML to be used in the popup (current)
				function popupHtmlCurrent(properties) {
					
					var html = ''
					+ '<div class=\"current\">'
					+ ''
					+ (properties.osmTags ? 
						  '<p>Current cycle parking:</p>'
						+ '<table class=\"lines compressed\">'
						+ (typeof properties.osmTags.bicycle_parking !== 'undefined' ? '<tr><td>Type:</td><td>' + nl2br(properties.osmTags.bicycle_parking) + '</td></tr>' : '')
						+ (typeof properties.osmTags.capacity !== 'undefined' ? '<tr><td>Capacity:</td><td>' + properties.osmTags.capacity + '</td></tr>' : '')
						+ (typeof properties.osmTags.covered !== 'undefined' ? '<tr><td>Covered:</td><td>' + properties.osmTags.covered + '</td></tr>' : '')
						+ '</table>'
					  : '<p>Current cycle parking</p>')
					+ '</div>';
					
					// Return HTML
					return html;
				}
				
				function setIcon(feature,latlng) {
					var marker = L.marker(latlng, {icon: icons['already']});
					marker.bindPopup(popupHtml" . ucfirst ($this->action) . "(feature.properties));
					return marker;
				}
				
				currentDataLayer = L.geoJson(null, {
					pointToLayer: setIcon
					}
				);
				
				currentDataLayer.addTo(map);
				
				function showCurrentData(ajaxResponse) {
					currentDataLayer.clearLayers();
					currentDataLayer.addData(ajaxResponse);
				}
				
				function getData() {
					var data='bbox=' + map.getBounds().toBBoxString();
					\$.ajax({
						url: browsingApiUrl,
						dataType: 'json',
						data: data,
/*
// http://stackoverflow.com/questions/5507234/how-to-use-basic-auth-and-jquery-and-ajax
						beforeSend: function (xhr){ 
							xhr.setRequestHeader('Authorization', 'Basic ' + btoa(apiKey + ':' + 'password')); 
						},
*/
						success: showCurrentData
					});
				}
				
				function whenMapMoves(e) {
					getData();
				}
				
				map.on('moveend', whenMapMoves);
				
				getData();
			});
			</script>
		";
		
		# Add autocomplete name search
		$geocoderApiUrl = $this->settings['apiBase'] . '/v2/geocoder' . '?key=' . $this->settings['apiKey'];
		// Libraries available at: http://cdnjs.com/libraries/jqueryui/
		$html .= "\n" . '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery-ui.css" />';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery.ui.autocomplete.css" />';
		$html .= "\n" . "<script type=\"text/javascript\">var $ = jQuery.noConflict();</script>";
		$html .= "\n" . '<script type="text/javascript" src="/js/autocomplete.js"></script>';
		$html .= "\n" . "<script type=\"text/javascript\">addAutocomplete(\"input[name='location']\", \"{$geocoderApiUrl}\");</script>";
		// Stop a return keypress causing the whole form to be submitted
		$html .= "\n" . '<script type="text/javascript">
		$("input[name=\'location\']").keypress(function(e) {
		    var code = (e.keyCode ? e.keyCode : e.which);
		    if(code == 13) { //Enter keycode
		        return false;
		    }
		});
		</script>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Contact form
	private function locationSubmissionForm ($current = false, &$html = '')
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . "\n{latitude}\n{longitude}\n{zoom}" . $this->placeholderHtmlToFormTemplate ('form'),
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> 'Submit',
			'submitButtonAccesskey'		=> false,
			'nullText'					=> false,
		));
		
		# Widgets
		$allowedExtensions = array ('jpg');
		$form->upload (array (
			'name'				=> 'file',
			'title'				=> 'Select an image from your device/computer',
			'description'		=> '<span class="small comment">(' . strtoupper (implode ('/', $allowedExtensions)) . ' only, maximum size: ' . ini_get ('upload_max_filesize') . ')</span>',
			'required'			=> false,
			'size'				=> 40,
			'directory'				=> $this->tmpDirectory,
			'allowedExtensions'		=> $allowedExtensions,
			'lowercaseExtension'	=> true,
			'flatten'			=> true,
		));
		if ($current) {
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
		}
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
			'default'		=> 'Yes',
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
		$result = $form->process ($html);
		
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
	
	
	# Settings page
	private function admin ()
	{
		# Start the HTML
		$html = '';
		
		# Write the form into the template
		$this->template['contents']  = "\n<h2>Settings</h2>";
		$this->template['contents'] .= $this->settingsForm ();
		
		# Return the HTML
		return $html;
	}
	
	
	# Settings form
	private function settingsForm ()
	{
		# Start the HTML
		$html = '';
		
		# Determine whether there are already settings present
		$settingsPresent = (isSet ($this->settings['id']));		// id comes only from the database table
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'autofocus'					=> true,
			'formCompleteText'			=> false,
			'reappear'					=> true,
			'databaseConnection'		=> $this->databaseConnection,
			'richtextEditorToolbarSet'	=> 'BasicLongerFormat',
			'richtextEditorAreaCSS'		=> $this->settings['cssFileLocation'],
			'richtextWidth'				=> '500px',
			'richtextHeight'			=> '250px',
			'picker'					=> true,
		));
		if (!$settingsPresent) {
			$form->heading ('', 'The site is ready for first-run. The administrator should add the settings.');
		}
		$form->dataBinding (array (
			'database' => 'main',
			'table' => 'settings',
			'intelligence' => true,
			'data' => $this->settings,
			'attributes' => array (
				'url'				=> array ('heading' => array (3 => 'Core settings'), 'default' => $_SERVER['_SITE_URL'], 'editable' => false, ),
				'contactsPageHtml'	=> array ('heading' => array (3 => 'Page texts'), ),
				'style'				=> array ('type' => 'select', 'values' => $this->getStyles (), ),
				'earliestDate'		=> array ('heading' => array (3 => 'Export parameters'), ),
				'bbox'				=> array ('description' => 'W,S,E,N; data from: http://wiki.openstreetmap.org/wiki/Bounding_Box', ),
			),
		));
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Insert/update the data
		if ($settingsPresent) {
			$result = $this->databaseConnection->update ('main', 'settings', $result, array ('id' => $this->settings['id']));
		} else {
			$result = $this->databaseConnection->insert ('main', 'settings', $result);
		}
		
		# Confirm success
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// http://www.fileformat.info/info/unicode/char/2714/
		$message  = "\n<p><strong>{$unicodeTick} The settings have been saved.</strong></p>";
		$message .= "\n<p><a href=\"{$this->baseUrl}/\">Continue to the front page.</a></p>";
		$html = $message . $html;
		
		# Return the HTML
		return $html;
	}
	
	
	# Data page
	private function data ()
	{
		# Start the HTML
		$html = '';
		
		# Return the HTML
		return $html;
	}
	
	
	# Data downloads
	private function download ()
	{
		# Ensure a dataset is specified, and that it is valid
		$datasets = array ('suggest', 'current');
		if (!isSet ($_GET['dataset']) || !in_array ($_GET['dataset'], $datasets)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$dataset = $_GET['dataset'];
		
		# Define the parameters for the API call
		$parameters = array (
			'category'		=> 'cycleparking',
			'metacategory'	=> $this->actions[$dataset]['metacategory'],
			'bbox'			=> $this->settings['bbox'],
			'earliestTime'	=> ($this->settings['earliestDate'] ? strtotime ($this->settings['earliestDate'] . ' 00:00:00') : 0),
			'thumbnailsize'	=> '640',
			'limit'			=> '0',
			'format'		=> 'csv',
			'fields'		=> "id,latitude,longitude,areaName,caption,additionalMetadata[{$this->actions[$dataset]['additionalMetadata']}],datetime,hasPhoto,thumbnailUrl,shortlink,license",
		);
		
		# Assemble the API call URL
		$apiUrl = $this->settings['apiBase'] . '/v2/photos' . '?key=' . $this->settings['apiKey'] . '&' . http_build_query ($parameters);
		
		# Obtain the data
		$csv = file_get_contents ($apiUrl);
		
		# Serve the file
		$filenameBase = $dataset . '_savedAt' . date ('Ymd-His');
		header ('Content-type: application/octet-stream');
		header ('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
		echo $csv;
	}
	
	
	# Login page
	private function login ()
	{
		# Start the HTML
		$html = '';
		
		# If the user is logged in, state this
		if ($this->user) {
			$html .= "\n<p><strong>You are logged in</strong>, as " . $this->user['email'] . " .</p>";
			$html .= "\n<p>You can <a href=\"{$this->baseUrl}/logout/\">log out</a> if you wish.</p>";
			$this->template['text'] = $html;
			$this->template['form'] = false;
		} else {
			
			# Login form; if successful, log the user in
			$html .= "\n<p><strong>Please log in below to access this section:</strong></p>";
			$this->template['text'] = $html;
			$formHtml = '';
			if ($result = $this->loginForm ($formHtml)) {
				$this->doLogin ($result);	// $result now contains the user details (username, email, name, privileges)
			}
			$this->template['form'] = $formHtml;
		}
	}
	
	
	# Login form
	private function loginForm (&$html)
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $this->placeholderHtmlToFormTemplate ('form'),
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> 'Login',
			'submitButtonAccesskey'		=> false,
		));
		
		# Widgets
		$form->email (array (
			'name'		=> 'email',
			'title'		=> 'Your e-mail address',
			'required'	=> true,
			'autofocus'	=> true,
		));
		$form->password (array (
			'name'		=> 'password',
			'title'		=> 'Password',
			'required'	=> true,
		));
		
		# Validate the login
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if (strlen ($unfinalisedData['email']) && strlen ($unfinalisedData['password'])) {
				if (!$result = $this->doAuthentication ($unfinalisedData['email'], $unfinalisedData['password'], $error)) {
					$form->registerProblem ('authfail', $error);
				}
			}
		}
		
		# Process the form
		if (!$form->process ($html)) {return false;}
		
		# Return the result
		return $result;
	}
	
	
	# Authentication
	private function doAuthentication ($email, $password, &$error = '')
	{
		# Assemble the data to post
		$postData = array (
			'email'		=> $email,
			'password'	=> $password,
		);
		
		# Post to the API
		$apiUrl = $this->settings['apiBase'] . $this->actions[$this->action]['apiUrl'] . '?key=' . $this->settings['apiKey'];
		$resultJson = application::file_post_contents ($apiUrl, $postData, $error);
		if ($error) {
			// echo $error;		// Debug
			$error = 'Sorry, a technical error occured trying to validate the details you gave. Please try again later.';
			return false;
		}
		
		# Unpack the response
		$result = json_decode ($resultJson, true);
		
		# If there is an error, pass on the text and return false
		if (isSet ($result['error'])) {
			$error = $result['error'];
			return false;
		}
		
		# Otherwise return the account details
		return $result;
	}
	
	
	# Get user details (from the session)
	private function getUser ()
	{
		# Lock down PHP session management
		ini_set ('session.name', 'session');
		ini_set ('session.use_only_cookies', 1);
		
		# Start the session handling
		if (!session_id ()) {session_start ();}
		
		# Regenerate the session ID
		session_regenerate_id ($deleteOldSession = true);
		
		# Set the top-right login area
		// At present, the login box is not shown
		$this->template['login-status'] = '';
		
		# Return false if no user
		if (!isSet ($_SESSION['user'])) {return false;}
		
		# Write the login status in the top-right
		$this->template['login-status'] = "\n<p style=\"text-align: right\"><span style=\"color: #ccc;\">Logged in as: </span>" . htmlspecialchars ($_SESSION['user']['email']) . " | <a href=\"{$this->baseUrl}/admin/\">Admin</a> | <a href=\"{$this->baseUrl}/data/\">Data</a> | <a href=\"{$this->baseUrl}/logout/\">Logout</a></p>";
		
		# Return the user details
		return $_SESSION['user'];
	}
	
	
	# Function to log the user in
	private function doLogin ($result)
	{
		# Create the session entry
		$_SESSION['user'] = $result;
		
		# Refresh the page to ensure the session cookie is written
		application::sendHeader ('refresh');
	}
	
	
	# Function to log the user out
	private function logout ()
	{
		# Start the HTML
		$html = '';
		
		# Cache whether the user presented session data
		$userHadSessionData = (isSet ($_SESSION['user']));
		
		# Explicitly destroy the session
		session_unset ();
		session_destroy ();
		unset ($_SESSION['user']);
		$params = session_get_cookie_params ();
		setcookie (session_name (), '', time () - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		
		# Confirm logout if there was a session, and redirect the user to the login page if necessary
		$loginLocation = $this->baseUrl . $this->actions['login']['url'];
		if ($userHadSessionData) {
			$html .= "\n<p>You have been successfully logged out.</p>";
			$html .= "\n<p>You can <a href=\"" . htmlspecialchars ($loginLocation) . '">log in again</a> if you wish.</p>';
		} else {
			header ('Location: http://' . $_SERVER['SERVER_NAME'] . $this->baseUrl . $loginLocation);
			$html .= "\n<p>You are not logged in.</p>";
			$html .= "\n<p><a href=\"" . htmlspecialchars ($loginLocation) . '">Please click here to continue.</a></p>';
		}
		
		# Clear the login status indicator
		$this->template['login-status'] = '';
		
		# Register the HTML
		$this->template['text'] = $html;
	}
}

?>