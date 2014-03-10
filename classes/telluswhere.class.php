<?php

# Class to implement a website asking visitors to say where public infrastructure changes are needed and to report on existing infrastructure
class telluswhere
{
	# Settings
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'style'					=> 'default',
			'apiBase'				=> 'https://api.cyclestreets.net',
			'cssFileLocation'		=> NULL,
			'apiKey'				=> NULL,
			'administratorEmail'	=> (isSet ($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : NULL),
			'username'				=> NULL,
			'password'				=> NULL,
			'flashMessageName'		=> 'confirmation',
		);
		
		# Return the defaults
		return $defaults;
	}
	
	# Register actions
	private function actions ()
	{
		# Specify available actions; URL refers both to the public URL and the template location
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '/',
			),
			'suggest' => array (
				'description' => 'Suggested cycle parking location',
				'url' => '/suggest/',
				'apiUrl' => '/v2/photos?category=cycleparking&metacategory=bad&limit=150&thumbnailsize=200&fields=id,name,hasPhoto,thumbnailUrl,additionalMetadata',
				'metacategory' => 'bad',
				'additionalMetadata' => 'landtype',
			),
			'current' => array (
				'description' => 'Current cycle parking location',
				'url' => '/current/',
				'apiUrl' => '/v2/photos?category=cycleparking&metacategory=other&limit=150&thumbnailsize=200&fields=id,name,hasPhoto,thumbnailUrl,additionalMetadata',
				// 'apiUrl' => '/v2/pois?type=cycleparking&limit=40',
				'metacategory' => 'other',
				'additionalMetadata' => 'landtype,type,capacity',
			),
			'location' => array (
				'description' => false,
				'url' => '/location/',	// Will be /location/<id>/
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
				'downloader' => true,
			),
			'download' => array (
				'description' => false,
				'url' => false,
				'downloader' => true,
			),
			'admin' => array (
				'description' => false,
				'url' => '/admin/',
				'administrator' => true,
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
	private $userIsAdministrator = false;
	private $userIsDownloader = false;
	
	# Labels for metadata fields
	private $metadataFieldLabels = array (
		'type'		=> 'Type of parking',
		'capacity'	=> 'How many cycles can be parked?',
		'landtype'	=> 'Land type',
		'caption'	=> 'Additional info / comments',
	);
	
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
			$html = "\n<p class=\"warning\">The website could not be set up due to a configuration error. Please check back shortly.</p>";
			echo $html;
			return false;
		}
		
		# Determine the tmp directory in use for file uploads and ensure it is writeable
		if (!$this->tmpDirectory = $this->getWritableDirectory ($this->tmpDirectory)) {
			$html = "\n<p class=\"warning\">The website could not be loaded due to a configuration error. Please check back shortly.</p>";
			echo $html;
			return false;
		}
		
		# Determine the style directory in use
		if (!$this->styleDirectory = $this->getStyleDirectory ($this->settings['style'])) {
			$html = "\n<p class=\"warning\">The website could not be loaded due to a configuration error. Please check back shortly.</p>";
			echo $html;
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
		
		# In first run mode, force settings to be entered, temporarily promoting the logged-in user to be an administrator
		if ($this->isFirstRun) {
			if ($this->isFirstRun) {$this->forcedAction = 'admin';}
			if ($this->user) {
				$this->userIsAdministrator = true;
			}
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
		if (isSet ($this->actions[$this->action]['authentication']) || isSet ($this->actions[$this->action]['administrator']) || isSet ($this->actions[$this->action]['downloader'])) {
			if (!$this->user) {
				$this->action = 'login';
			}
		}
		
		# Require administrative privileges if specified
		if (isSet ($this->actions[$this->action]['administrator'])) {
			if (!$this->userIsAdministrator) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
		}
		
		# Require downloader privileges if specified
		if (isSet ($this->actions[$this->action]['downloader'])) {
			if (!$this->userIsDownloader) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
		}
		
		# Load the template
		$this->templateHtml = $this->getTemplateHtml ($this->action);
		
		# Perform the action, which will write into the page template array
		$this->{$this->action} ();
		
		# Render the page
		$html = $this->doTemplateSubstitution ($this->templateHtml, $this->template);
		
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
		
		# Set a flag to indicate first-run mode
		$this->isFirstRun = false;
		
		# Obtain the settings
		if (!$databaseSettings = $this->databaseConnection->selectOne ('main', 'settings', array ('url' => $_SERVER['_SITE_URL']))) {
			$this->isFirstRun = true;
			$databaseSettings = array ('administrators' => false, 'downloaders' => false);	// $databaseSettings = false would crash array_merge below
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
			  `applicationName` VARCHAR(255) NOT NULL,		-- Site name
			  `style` VARCHAR(255) NOT NULL,				-- Style
			  `feedbackRecipient` VARCHAR(255) NOT NULL,	-- Contact page form recipient
			  `aboutPageHtml` TEXT NOT NULL,				-- About page text
			  `contactsPageHtml` TEXT NOT NULL,				-- Contact page text
			  `termsPageHtml` TEXT NOT NULL,				-- Terms page text
			  `administrators` TEXT NOT NULL,				-- E-mail logins of administrators
			  `downloaders` TEXT NOT NULL,					-- E-mail logins for access to downloads
			  `defaultLatitude` FLOAT NOT NULL,				-- Default latitude
			  `defaultLongitude` FLOAT NOT NULL,			-- Default longitude
			  `defaultZoom` FLOAT NOT NULL,					-- Default zoom
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
	private function placeholderHtmlToFormTemplate ($placeholderName, $action, $selectedId = false)
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
				$replacements[$placeholder] = $this->locationsMap ($action, $selectedId);
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
	private function suggest ($existingData = array ())
	{
		# Start the HTML
		$html = '';
		
		# Show the submission page
		$html = $this->submissionPage (__FUNCTION__, $existingData);
		
		# Register the HTML
		$this->template['form'] = $html;
	}
	
	
	# Page for auditing of current locations
	private function current ($existingData = array ())
	{
		# Start the HTML
		$html = '';
		
		# Show the submission page
		$html = $this->submissionPage (__FUNCTION__, $existingData);
		
		# Register the HTML
		$this->template['form'] = $html;
	}
	
	
	# Page showing an existing location
	private function location ()
	{
		# Start the HTML
		$html = '';
		
		# Ensure there is an ID
		if (!isSet ($_GET['id']) || !ctype_digit ($_GET['id'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Get the data for this location
		$id = $_GET['id'];
		#!# API call output needs to rename metacategoryTag and categoryTag to metacategory and category
		$apiUrl = $this->settings['apiBase'] . '/v2/photomap.location' . '?key=' . $this->settings['apiKey'] . '&id=' . $id . '&format=flat' . '&fields=id,metacategoryTag,categoryTag,caption,latitude,longitude,zoom,basemap,credit,additionalMetadata,hasPhoto,thumbnailUrl' . '&thumbnailsize=200';
		
		# Obtain the data
		$data = file_get_contents ($apiUrl);
		
		# Decode to JSON
		$data = json_decode ($data, true);
		
		# End if no such ID
		if (isSet ($data['error'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Determine the supported metacategories, and the marker layer for each
		$supportedMetacategories = array ();
		foreach ($this->actions as $action => $attributes) {
			if (isSet ($attributes['metacategory'])) {
				$supportedMetacategories[$attributes['metacategory']] = $action;
			}
		}
		
		# Determine the supported categories
		#!# Generalise to setting
		$supportedCategories = array ('cycleparking');
		
		# End if not a supported metacategory or category
		if (!array_key_exists ($data['metacategoryTag'], $supportedMetacategories) || !in_array ($data['categoryTag'], $supportedCategories)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Assign the virtual action (e.g. if the data's metacategory is 'bad', then the action is 'current'
		$action = $supportedMetacategories[$data['metacategoryTag']];
		
		# Divert to CRUD action if required
		if (isSet ($_GET['mode'])) {
			
			# Validate requested action
			$crudActions = array ('edit', );
			if (!in_array ($_GET['mode'], $crudActions)) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
			
			# Run the CRUD method
			$method = __FUNCTION__ . ucfirst ($_GET['mode']);	// e.g. locationEdit()
			return $this->$method ($id, $action, $data);	// Pass in existing data $data
		}
		
		# Start the metadata panel with the caption
		$metadataHtml = '';
		if ($data['caption']) {
			$metadataHtml .= application::formatTextBlock (htmlspecialchars ($data['caption']), 'metadata');
		}
		
		# Show additional metadata table
		if ($data['additionalMetadata']) {
			
			# Filter to supported fields for this action
			$additionalMetadataFields = explode (',', $this->actions[$action]['additionalMetadata']);
			$table = application::arrayFields ($data['additionalMetadata'], $additionalMetadataFields);
			
			# Substitute internal names in table
			if (isSet ($table['type'])) {
				$table['type'] = $this->parkingTypes[$table['type']];
			}
			if (isSet ($table['landtype'])) {
				$table['landtype'] = $this->landTypes[$table['landtype']];
			}
			
			# Add to the HTML
			$metadataHtml .= application::htmlTableKeyed ($table, $this->metadataFieldLabels, true, 'lines metadatatable');
		}
		
		# Set the flash message message, if any
		$flashMessage = false;
		if ($confirmationId = application::getFlashMessage ($this->settings['flashMessageName'], $this->baseUrl . '/')) {
			if ($confirmationId == $id) {
				$flashMessage = $this->confirmationMessage ($confirmationId, $action);
				$flashMessage = "\n<div id=\"flashmessage\">" . $flashMessage . "\n</div>";
			}
		}
		
		# Determine whether the user can edit
		#!# TODO
		$userCanEdit = true;
		
		# Add an edit link
		$editlink = false;
		if ($userCanEdit) {
			$editlink = "\n<p id=\"editlink\"><a href=\"{$this->baseUrl}/location/{$id}/edit/\"><img src=\"{$this->baseUrl}/images/pencil.png\" alt=\"\" width=\"16\" height=\"16\" border=\"0\" /> Edit</a></p>";
		}
		
		# Register HTML components
		$this->template['id'] = $this->actions[$action]['description'] . ' &mdash; #' . $id;
		$this->template['message'] = $flashMessage;
		$this->template['editlink'] = $editlink;
		$this->template['map'] = $this->locationsMap ($action, $id);
		$this->template['metadata'] = $metadataHtml;
	}
	
	
	# Editing of location
	private function locationEdit ($id, $action, $data)
	{
		# Overwrite the default template
		$this->templateHtml = $this->getTemplateHtml ($action);
		
		# Hand off to current/suggest in edit mode
		return $this->$action ($data);
	}
	
	
	# Submission page logic
	private function submissionPage ($action, $existingData = array ())
	{
		# Start the HTML
		$html = '';
		
		# Create the form and process the data
		if (!$data = $this->locationSubmissionForm ($action, $existingData, $html)) {		// &html written into by reference
			return $html;
		}
		
		# Send the data (including any image) to the API
		if (!$result = $this->postSubmission ($data, $action, $existingData, $error)) {
			$html = $error;
			return $html;
		}
		
		# Unpack the response
		$result = json_decode ($result, true);
		
		# End if the API returned an error
		if (isSet ($result['error'])) {
			// echo $result['error'];	// Debugging
			$html = 'Sorry, a technical error occured - please try again later.';
			return $html;
		}
		
		# Add to mailing list data if required
		if ($data['mailinglist'] == 'Yes') {
			$file = $_SERVER['DOCUMENT_ROOT'] . '/db/mailinglist.csv';
			$string = $data['email'] . ',' . $data['name'] . ',' . $result['id'] . "\n";
			file_put_contents ($file, $string, FILE_APPEND);
		}
		
		# Determine the redirection target, namely the location page
		$redirectToPath = $this->baseUrl . "/location/{$result['id']}/";
		
		# Thank the user, resetting the HTML
		$html  = $this->confirmationMessage ($result['id'], $action);
		$html .= "\n<p><a href=\"{$redirectToPath}\">Click here to continue to the next page.</a></p>";
		
		# Set a flash message and redirect the user (which will override the confirmation above)
		application::setFlashMessage ($this->settings['flashMessageName'], $result['id'], $redirectToPath, $html, $this->baseUrl . '/');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to set the addition confirmation message
	private function confirmationMessage ($id, $action)
	{
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// http://www.fileformat.info/info/unicode/char/2714/
		$html  = "\n<p><strong>{$unicodeTick} Thank you for your submission</strong>, which is number {$id}.</p>";
		$html .= "\n<p><a href=\"{$this->actions[$action]['url']}\">Add another?</a></p>";
		return $html;
	}
	
	
	# Function to post submissions to the API
	private function postSubmission ($rawdata, $action, $existingData, &$error = '')
	{
		# Define the API URL; note this uses a POST operation due to the presence of a username and password
		$apiCall = ($existingData ? 'photomap.update' : 'photomap.add');
		$apiUrl = $this->settings['apiBase'] . '/v2/' . $apiCall . '?key=' . $this->settings['apiKey'];
		
		# Assemble the additional metadata
		$additionalMetadataFields = explode (',', $this->actions[$action]['additionalMetadata']);
		$additionalMetadata = application::arrayFields ($rawdata, $additionalMetadataFields);
		
		# If the message is empty, add a generic message as the API sets caption as a required field
		if (empty ($rawdata['caption'])) {
			$rawdata['caption'] = 'Cycle parking ' . ($action == 'suggest' ? 'needed' : 'present') . ' here.';
		}
		
		# Map the fields to the API
		$data = array (
			#!# Currently a fixed username/password
			'username'				=> $this->settings['username'],
			'password'				=> $this->settings['password'],
			'metacategory'			=> $this->actions[$action]['metacategory'],
			'category'				=> 'cycleparking',
			'caption'				=> $rawdata['caption'],
			'latitude'				=> $rawdata['latitude'],
			'longitude'				=> $rawdata['longitude'],
			'zoom'					=> $rawdata['zoom'],
			'basemap'				=> 'mapnik',
			'credit'				=> $rawdata['name'] . ' <' . $rawdata['email'] . '>',
			'additionalMetadata'	=> json_encode ($additionalMetadata),
		);
		
		#!# Currently no support for deleting an existing image when doing an update
		
		# Add the mediaupload field if a file has been submitted
		$filePath = false;
		if (isSet ($rawdata['file'])) {		// If there is an existing photo, this field will not be present
			if ($rawdata['file']) {
				$filePath = $this->tmpDirectory . $rawdata['file'];
				if (function_exists ('curl_file_create')) {
					$mediaupload = curl_file_create ($filePath);	// Modern method, avoids CURL deprecation warnings from PHP 5.5+
				} else {
					$mediaupload = '@' . $filePath;	// Deprecated method using @ symbol - see: http://stackoverflow.com/a/4270282/180733
				}
				$data['mediaupload'] = $mediaupload;
			}
		}
		
		# If editing an existing location, include the ID
		if ($existingData) {
			$data['id'] = $existingData['id'];
		}
		
		# Post the data
		$result = application::file_post_contents ($apiUrl, $data, true, $transportError);
		
		# Delete the temporary file if a file was uploaded
		if ($filePath) {
			unlink ($filePath);
		}
		
		# Report any transport error
		if ($transportError) {
			// echo $transportError;	// Debugging
			$error = 'Sorry, a technical error occured - please try again later.';
			return false;
		}
		
		# Return the result
		return $result;
	}
	
	
	# Map of current locations
	private function locationsMap ($showLayer, $selectedId = false)
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
		$browsingApiUrl = $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl'] . ($selectedId ? "&selectedid={$selectedId}" : '') . '&key=' . $this->settings['apiKey'];
		
		# Create the map application HTML
		$html .= '
		<link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.css" />
		<script src="http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.js"></script>
		<style type="text/css">
			#helptext {margin: 0;}
			#helptext.display {background-color: yellow;}
			#helptext.hide {background-color: transparent;}
			input.ui-autocomplete-loading {background: white url(\'/images/ui-anim_basic_16x16.gif\') right center no-repeat;}
			.leaflet-popup-content-wrapper {width: 250px; min-height: 80px;}
			.bubble p {margin: 0 0 5px;}
			.bubble p.id {text-align: right; font-size: 0.83em; margin: 0; padding: 0 0 3px;}
			.bubble p.id a {color: #bbb;}
			.bubble p.caption:before {color: #900; content: "\201C"; /* http://monc.se/kitchen/129/rendering-quotes-with-css */ font-family: Arial, Helvetica, sans-serif; font-size: 4.5em; font-weight: bold; line-height: 0; margin: 0 5px 0 -5px; vertical-align: bottom;}
			table.metadatatable td.value, p.metadata {font-weight: bold;}
			p.metadata {margin-bottom: 2em;}
			#flashmessage {clear: both; border: 1px solid #603; background-color: #f7f7f7; padding: 10px; margin: 1em 0 2em;}
			p#editlink {clear: both; float: right; padding: 0; margin: 0 0 10px 10px;}
			p#editlink a {border: 1px solid #ddd; display: block; padding: 5px 10px; border-radius: 4px; background-color: #f7f7f7; font-weight: bold;}
			p#editlink a:hover {text-decoration: none; background-color: #eee;}
			form div.error {clear: both; border: 2px solid red; background-color: #f7f7f7; padding: 10px; margin: 1em 0 2em;}
			
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
		';
		if (!$selectedId) {
			$html .= "\n" . '<p id="helptext">Zoom all the way in, using +/- or mouse scroll functions, then click on the map to set the marker.</p>';
		}
		$html .= "\n" . '<div id="map"></div>';
		
		# Load EXIF Filereader support
		$html .= "\n<script type=\"text/javascript\" src=\"/js/jquery.exif.js\"></script>";
		
		# Load the map application Javascript and run it
		$setMarkerInitiallyJs = ($setMarkerInitially ? 'true' : 'false');
		$selectedIdJs = ($selectedId ? $selectedId : 'false');
		$html .= "\n<script type=\"text/javascript\" src=\"/js/telluswhere.js\"></script>";
		$html .= "\n<script type=\"text/javascript\">
			var map = telluswhere.createMap('{$this->baseUrl}', {$mapLocation['latitude']}, {$mapLocation['longitude']}, {$mapLocation['zoom']}, '{$browsingApiUrl}', '{$showLayer}', {$setMarkerInitiallyJs}, {$selectedIdJs});
		</script>
		";
		
		# Add autocomplete name search
		$geocoderApiUrl = $this->settings['apiBase'] . '/v2/geocoder' . '?key=' . $this->settings['apiKey'];
		// Libraries available at: http://cdnjs.com/libraries/jqueryui/
		$html .= "\n" . '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery-ui.css" />';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery.ui.autocomplete.css" />';
		$html .= "\n" . '<script type="text/javascript" src="/js/autocomplete.js"></script>';
		$html .= "\n" . "<script type=\"text/javascript\">autocompleteNS.addTo(map, \"input[name='location']\", \"{$geocoderApiUrl}\");</script>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Location submission form
	private function locationSubmissionForm ($action, $existingData, &$html = '')
	{
		# Start the HTML
		$html = '';
		
		# Unpack user details cookie if present from a previous submission
		$data = $this->getCourtesyUserdetails ();
		
		# Map the data structure to the form data, flattening out the additional metadata if present
		if ($existingData) {
			$data['caption'] = $existingData['caption'];
			if ($existingData['additionalMetadata']) {
				foreach ($existingData['additionalMetadata'] as $field => $value) {
					$data[$field] = $value;
				}
			}
		}
		
		# Determine whether to select an existing marker on the map
		$selectedId = ($existingData ? $existingData['id'] : false);
		
		# Determine the form template
		$displayTemplate = $this->placeholderHtmlToFormTemplate ('form', $action, $selectedId);
		
		# Determine whether an existing photo already exists
		$existingPhoto = ($existingData && $existingData['hasPhoto'] == 'yes' ? $existingData['thumbnailUrl'] : false);
		if ($existingPhoto) {
			#!# Slightly hacky - ultimateForm::upload needs support for uneditable existing photos by supplying a URL to show the image instead of the form widget
			$displayTemplate = str_replace ('{file}', "<img src=\"{$existingPhoto}\" alt=\"Existing image\" />", $displayTemplate);
		}
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . "\n{latitude}\n{longitude}\n{zoom}" . $displayTemplate,
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> 'Submit',
			'submitButtonAccesskey'		=> false,
			'nullText'					=> false,
		));
		
		# Widgets
		if (!$existingPhoto) {
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
		}
		if ($action == 'current') {
			$form->select (array (
				'name'			=> 'type',
				'title'			=> $this->metadataFieldLabels['type'],
				'required'		=> true,
				'values'		=> $this->parkingTypes,
				'default'		=> (isSet ($data['type']) ? $data['type'] : false),
			));
			$form->number (array (
				'name'			=> 'capacity',
				'title'			=> $this->metadataFieldLabels['capacity'],
				'required'		=> true,
				'default'		=> (isSet ($data['capacity']) ? $data['capacity'] : false),
			));
		}
		$form->select (array (
			'name'			=> 'landtype',
			'title'			=> $this->metadataFieldLabels['landtype'],
			'required'		=> true,
			'values'		=> $this->landTypes,
			'default'		=> (isSet ($data['landtype']) ? $data['landtype'] : false),
		));
		$form->textarea (array (
			'name'			=> 'caption',
			'title'			=> $this->metadataFieldLabels['caption'],
			'required'		=> false,
			'rows'			=> 2,
			'cols'			=> 20,
			'default'		=> (isSet ($data['caption']) ? $data['caption'] : false),
		));
		$form->input (array (
			'name'			=> 'name',
			'title'			=> 'Your name',
			'required'		=> true,
			'default'		=> (isSet ($data['name']) ? $data['name'] : false),
		));
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'Your e-mail address',
			'required'		=> true,
			'default'		=> (isSet ($data['email']) ? $data['email'] : false),
		));
		$form->select (array (
			'name'			=> 'mailinglist',
			'title'			=> 'Would you like to be kept up-to-date via e-mail?',
			'required'		=> true,
			'values'		=> array ('Yes', 'No'),
			'default'		=> 'Yes',
		));
		$form->select (array (
			'name'			=> 'terms',
			'title'			=> "Do you accept our <a target=\"_blank\" href=\"{$this->baseUrl}/terms/\">terms &amp; conditions</a>?",
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
		
		# Upon a successful submission, save the name and e-mail in a cookie for a short period to save the user having to re-type these
		if ($result) {
			$this->setCourtesyUserdetails ($result['name'], $result['email']);
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to get retrieved user cookie details; there is no security involved - this is merely a courtesy to the user
	private function getCourtesyUserdetails ()
	{
		# Get the data, if any
		$data = array ();
		if (isSet ($_COOKIE['userdetails'])) {
			if (preg_match ('/^(.+) <([^>]+)>$/', $_COOKIE['userdetails'], $matches)) {
				$data = array ('name' => $matches[1], 'email' => $matches['2']);
			}
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to set retrieved user cookie details; there is no security involved - this is merely a courtesy to the user
	private function setCourtesyUserdetails ($name, $email)
	{
		$cookiePeriodHours = 12;
		$userdetails = "{$name} <{$email}>";
		setcookie ('userdetails', $userdetails, time () + ($cookiePeriodHours * 60*60), $this->baseUrl . '/');
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
		
		# Unpack user details cookie if present from a previous submission
		$data = $this->getCourtesyUserdetails ();
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> "Many thanks for your message - we'll be in touch shortly if applicable.",
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $this->placeholderHtmlToFormTemplate ('form', $this->action),
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
			'default'	=> ($data ? $data['name'] : false),
		));
		$form->email (array (
			'name'		=> 'email',
			'title'		=> 'E-mail',
			'required'	=> true,
			'default'	=> ($data ? $data['email'] : false),
		));
		
		# Set the processing options
		$form->setOutputEmail ($this->settings['feedbackRecipient'], $this->settings['administratorEmail'], $this->settings['applicationName'] . ' contact form', NULL, $replyToField = 'email');
		
		# Process the form
		$result = $form->process ($html);
		
		# Upon a successful submission, save the name and e-mail in a cookie for a short period to save the user having to re-type these
		if ($result) {
			$this->setCourtesyUserdetails ($result['name'], $result['email']);
		}
		
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
		
		# If no settings present, set a default for the initial administrator value
		$data = $this->settings;
		if ($this->isFirstRun) {
			$data['administrators'] = $this->user['email'] . "\n";
		}
		
		# Add form styles
		$html .= "\n<link rel=\"stylesheet\" href=\"/css/generic.css\" />";
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'div'						=> 'ultimateform horizontalonly',
			'autofocus'					=> true,
			'formCompleteText'			=> false,
			'reappear'					=> true,
			'databaseConnection'		=> $this->databaseConnection,
			'richtextEditorToolbarSet'	=> 'BasicLongerFormat',
			'richtextEditorAreaCSS'		=> $this->settings['cssFileLocation'],
			'richtextWidth'				=> '500px',
			'richtextHeight'			=> '250px',
			'picker'					=> true,
			'displayRestrictions'		=> false,
		));
		if ($this->isFirstRun) {
			$form->heading ('', 'The site is ready for first-run. The administrator should add the settings.');
		}
		$form->dataBinding (array (
			'database' => 'main',
			'table' => 'settings',
			'intelligence' => true,
			'data' => $data,
			'attributes' => array (
				'url'				=> array ('heading' => array (3 => 'Core settings'), 'default' => $_SERVER['_SITE_URL'], 'editable' => false, ),
				'aboutPageHtml'		=> array ('heading' => array (3 => 'Page texts'), ),
				'style'				=> array ('type' => 'select', 'values' => $this->getStyles (), ),
				'administrators'	=> array ('heading' => array (3 => 'Privileged users'), 'description' => 'One e-mail address per line', ),
				'downloaders'		=> array ('description' => 'One e-mail address per line', ),
				#!# Add max/min/step/pattern for defaultLatitude/defaultLongitude when ultimateForm has support; see: http://stackoverflow.com/questions/15303940/
				'defaultLatitude'	=> array ('heading' => array (3 => 'Initial map location'), ),
				'earliestDate'		=> array ('heading' => array (3 => 'Export parameters'), ),
				'bbox'				=> array ('description' => 'W,S,E,N; data from: http://wiki.openstreetmap.org/wiki/Bounding_Box', ),
			),
		));
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Insert/update the data
		if ($this->isFirstRun) {
			$this->databaseConnection->insert ('main', 'settings', $result);
		} else {
			$this->databaseConnection->update ('main', 'settings', $result, array ('id' => $this->settings['id']));
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
			'fields'		=> "id,latitude,longitude,areaName,caption,additionalMetadata[{$this->actions[$dataset]['additionalMetadata']}],datetime,hasPhoto,shortlink,license",
		);
		
		# Assemble the API call URL
		$apiUrl = $this->settings['apiBase'] . '/v2/photos' . '?key=' . $this->settings['apiKey'] . '&' . http_build_query ($parameters);
		
		# Obtain the data
		$csv = file_get_contents ($apiUrl);
		
		# Replace cycle.st links with internal links
		#!# Bit of a dirty way to do this - should have an API parameter, e.g. shortlink=http://{$_SERVER['SERVER_NAME']}/location/%s/
		$csv = preg_replace ('|,http://cycle.st/p([0-9]+),|', ",http://{$_SERVER['SERVER_NAME']}/location/\$1/,", $csv);
		
		# Serve the file
		$filenameBase = $dataset . '_savedAt' . date ('Ymd-His');
		header ('Content-type: text/csv');	// Note that Chrome will still give "Resource interpreted as Document but transferred with MIME type text/csv" - see: http://stackoverflow.com/a/3899453/180733
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
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $this->placeholderHtmlToFormTemplate ('form', $this->action),
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
		
		# Determine if the user is an administrator
		$administratorsList = ($this->settings['administrators'] ? preg_split ("/\s+/", trim ($this->settings['administrators'])) : array ());
		$this->userIsAdministrator = (in_array ($_SESSION['user']['email'], $administratorsList));
		
		# Determine if the user is a downloader
		$downloadersList = ($this->settings['downloaders'] ? preg_split ("/\s+/", trim ($this->settings['downloaders'])) : array ());
		$this->userIsDownloader = (in_array ($_SESSION['user']['email'], $downloadersList) || $this->userIsAdministrator);
		
		# Write the login status in the top-right
		$this->template['login-status'] = "\n<p style=\"text-align: right\"><span style=\"color: #ccc;\">Logged in as: </span>" . htmlspecialchars ($_SESSION['user']['email']) . ($this->userIsAdministrator ? " | <a href=\"{$this->baseUrl}/admin/\">Admin</a>" : '') . ($this->userIsDownloader ? " | <a href=\"{$this->baseUrl}/data/\">Data</a>" : '') . " | <a href=\"{$this->baseUrl}/logout/\">Logout</a></p>";
		
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