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
			'apiKey'				=> false,
			'username'				=> false,
			'password'				=> false,
			'cssFileLocation'		=> NULL,
			'administratorEmail'	=> (isSet ($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : NULL),
			'flashMessageName'		=> 'confirmation',
			'editabilityPeriod'		=> 7 * 24 * 60 * 60,		// In seconds
			'trackingCode'			=> false,
			'dataset'			=> false,	// For audit
			#!# Needs to be added to database settings
			'geocoderBboxBounded'		=> '-6.6577,49.9370,1.7797,57.6924',	// English mainland
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
				'description' => 'Suggested %categoryLabel',
				'url' => '/suggest/',
				'apiUrl' => '/v2/photomap.locations?category=%category&metacategory=bad&limit=150&thumbnailsize=200&fields=id,caption,hasPhoto,thumbnailUrl,metacategoryId,likes,additionalMetadata',
				'metacategory' => 'bad',
				'additionalMetadata' => array (
					'cycleparking' => 'landtype,capacity',
					'bikeshare' => 'schemes',
				),
			),
			'current' => array (
				'description' => 'Current %categoryLabel',
				'url' => '/current/',
				'apiUrl' => '/v2/photomap.locations?category=%category&metacategory=other&limit=150&thumbnailsize=200&fields=id,caption,hasPhoto,thumbnailUrl,likes,additionalMetadata',
				// 'apiUrl2' => '/v2/pois.locations?type=cycleparking&limit=40&fields=id,latitude,longitude,name,nodeId,osmTags',
				'metacategory' => 'other',
				'additionalMetadata' => array (
					'cycleparking' => 'landtype,type,capacity',
					'bikeshare' => 'schemes',
				),
			),
			'audit' => array (
				'description' => 'Audit %categoryLabel',
				'url' => '/audit/',
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&limit=400',
				'apiUrl2' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
			),
			'auditadd' => array (
				'description' => 'Audit %categoryLabel',
				'url' => '/audit/add/',
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&limit=400',
				'apiUrl2' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
			),
			'auditaddlocation' => array (
				'description' => 'Audit %categoryLabel',
				'url' => '/audit/add/location/',	// Template location; URL will be /audit/add/%category/
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&type=%type&limit=400',
				'apiUrl2' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
			),
			'auditlocation' => array (
				'description' => 'Audit location',
				'url' => '/audit/location/',	// Will be /audit/location/<id>/
				'apiUrl' => '/v2/infrastructure.location&dataset=%dataset&id=%id',
			),
			'priorityareas' => array (
				'description' => 'Priority areas',
				'url' => '/audit/priorityareas/',
				'apiUrl' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
			),
			'embed' => array (
				'description' => false,
				'url' => '/embed/',	// E.g. /current/embed/
			),
			'location' => array (
				'description' => false,
				'url' => '/location/',	// Will be /location/<id>/
			),
			'problem' => array (
				'description' => false,
				'url' => false,	// No template; Will be /location/<id>/problem/
			),
			'news' => array (
				'description' => false,
				'url' => '/news/',
			),
			'about' => array (
				'description' => false,
				'url' => '/about/',
			),
			'programme' => array (
				'description' => false,
				'url' => '/programme/',
				'optional' => true,
			),
			'terms' => array (
				'description' => false,
				'url' => '/terms/',
			),
			'contacts' => array (
				'description' => false,
				'url' => '/contacts/',
			),
			'admin' => array (
				'description' => 'Admin area',
				'url' => '/admin/',
				'administrator' => true,
			),
			'data' => array (
				'description' => false,
				'url' => '/data/',
				'rightRequired' => 'downloader',
			),
			'download' => array (
				'description' => false,
				'url' => false,
				'rightRequired' => 'downloader',
				'export' => true,
			),
			'settings' => array (
				'description' => false,
				'url' => '/settings/',
				'administrator' => true,
			),
			'batch' => array (
				'description' => false,
				'url' => '/batch/',
				'rightRequired' => 'batchUploader',
			),
			'login' => array (
				'description' => false,
				'url' => '/login/',
				'apiUrl' => '/v2/user.authenticate',
			),
			'logout' => array (
				'description' => false,
				'url' => '/logout/',
			),
			'register' => array (
				'description' => false,
				'url' => '/register/',
			),
			'profile' => array (
				'description' => false,
				'url' => '/profile/',
			),
			'adminreview' => array (
				'description' => 'Review submissions',
				'url' => '/admin/review/',
				'administrator' => true,
			),
			'adminsearch' => array (
				'description' => 'Search locations',
				'url' => '/admin/search/',
				'administrator' => true,
			),
			'adminusers' => array (
				'description' => 'Manage users',
				'url' => '/admin/users/',
				'administrator' => true,
			),
			'adminboroughs' => array (
				'description' => 'Progress by borough',
				'url' => '/admin/boroughs/',
				'administrator' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	# Class properties
	public $baseUrl;
	public $databaseConnection = NULL;
	public $settings;
	public $user;
	public $userIsAdministrator = false;
	private $html = '';
	private $forcedAction = false;
	private $template = array ();	// Associative array of fragments to be replaced
	private $replacedPlaceholders = array ();	// Associative array of placeholder comments which have been replaced
	private $tmpFolder = '/tmp/';
	private $userIsDownloader = false;
	private $userIsBatchUploader = false;
	private $userIsNewsEditor = false;
	private $popupLabels = array ();
	private $popupLabelSubsetField = false;
	
	
	# Labels for known categories; these can also be supplied in the settings as three columns, tab-separated
	private $categoryLabels = array (
		'cycleparking'		=> array (
			'plural'			=> 'Cycle parking',
			'singular'			=> 'Cycle parking',
		),
		'bikeshare'		=> array (
			'plural'			=> 'Bikeshare locations',
			'singular'			=> 'Bikeshare location',
		),
		'obstructions'		=> array (
			'plural'			=> 'Obstructions',
			'singular'			=> 'Obstruction',
		),
		'cycleways'		=> array (
			'plural'			=> 'Cycleways',
			'singular'			=> 'Cycleway',
		),
		'dutchcycleways'	=> array (
			'plural'			=> 'Dutch-style cycleways',
			'singular'			=> 'Dutch-style cycleway',
		),
	);
	
	# Labels for metadata fields
	private $metadataFieldLabels = array (
		'type'		=> 'Type of parking',
		'capacity'	=> 'How many cycles can be parked?',
		'landtype'	=> 'Land type',
		'caption'	=> 'Additional info / comments',
		'schemes'	=> 'Which scheme(s), if any, have you used, and how did you find them?',
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
		
		# Load required libraries
		require_once ('application.php');
		require_once ('templating.php');
		
		# Define the location of the stub launching file
		$this->baseUrl = application::getBaseUrl ();
		
		# Function to merge the arguments; note that $errors returns the errors by reference and not as a result from the method
		if (!$fixedSettings = application::assignArguments ($errors, $settings, $this->defaults (), __CLASS__, NULL, $handleErrors = true)) {
			return false;
		}
		
		# Add additional settings from the database, ensuring the database is set up
		if (!$this->settings = $this->getSettings ($fixedSettings)) {
			$html = "\n<p class=\"warning\">The website could not be set up due to a configuration error (error #1). Please check back shortly.</p>";
			echo $html;
			return false;
		}
		
		# Determine the tmp directory in use for file uploads and ensure it is writeable
		if (!$this->tmpDirectory = $this->getWritableDirectory ($this->tmpFolder)) {
			$html = "\n<p class=\"warning\">The website could not be loaded due to a configuration error (error #2). Please check back shortly.</p>";
			echo $html;
			return false;
		}
		
		# Determine the style directory in use
		$this->styleDirectory = $this->getStyleDirectory ();
		
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
			if ($this->isFirstRun) {$this->forcedAction = 'settings';}
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
		if (isSet ($this->actions[$this->action]['authentication']) || isSet ($this->actions[$this->action]['administrator']) || isSet ($this->actions[$this->action]['rightRequired'])) {
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
		if (isSet ($this->actions[$this->action]['rightRequired'])) {
			$property = 'userIs' . ucfirst ($this->actions[$this->action]['rightRequired']);
			if (!$this->{$property}) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
		}
		
		# Determine if the action is an export type, in which files are served rather than a page generated
		$isExportAction = (isSet ($this->actions[$this->action]['export']) && $this->actions[$this->action]['export']);
		
		# Assume not in frame mode, unless overridden
		$this->iframeSuffix = false;
		
		# Load the template; if not present, fallback to 404 page; export type pages do not have a template
		if (!$isExportAction) {
			if (!$this->templateHtml = $this->getTemplateHtml ($this->action)) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
		}
		
		# Determine the supported metacategories and the action they are mapped to
		$this->metacategories = array ();
		foreach ($this->actions as $action => $attributes) {
			if (isSet ($attributes['metacategory'])) {
				$this->metacategories[$attributes['metacategory']] = $action;
			}
		}
		
		# Determine the supported categories, supplied in the settings form as either as three columns (tab-separated) for ID,plural,singular, or a simple list of IDs
		if (substr_count ($this->settings['categories'], "\t")) {
			$categories = explode ("\n", $this->settings['categories']);
			$this->categories = array ();
			$this->categoryLabels = array ();
			foreach ($categories as $categoryLine) {
				list ($id, $plural, $singular) = explode ("\t", trim ($categoryLine));
				$this->categories[] = $id;
				$this->categoryLabels[$id] = array ('plural' => $plural, 'singular' => $singular);
			}
		} else {
			$this->categories = ($this->settings['categories'] ? preg_split ("/\s+/", trim ($this->settings['categories'])) : array ());	// The ternary exists because first run will have none
		}
		
		# Perform the action, which will write into the page template array
		#!# Need to handle 404s properly by using return value for each action
		$this->{$this->action} ();
		
		# End if an export action, which should not generate HTML
		if ($isExportAction) {return;}
		
		# Render the page
		$html = templating::doTemplateSubstitution ($this->templateHtml, $this->template, $this->styleDirectory);
		
		# Add stats tracking code if required
		$html = $this->analyticsTrackingCode ($html);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to add additional settings from the database, ensuring the database is set up
	private function getSettings ($settings)
	{
		# Ensure the server has PDO SQLite support (usually enabled using "extension=php_pdo_sqlite.ext" in php.ini)
		if (!extension_loaded ('pdo_sqlite')) {return false;}
		
		# Ensure the database directory exists and is writable
		$databaseFolder = '/db/sqlite/';
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
		
		# Ensure the database file itself is writable
		if (!is_writable ($databaseFile)) {return false;}
		
		# Set a flag to indicate first-run mode
		$this->isFirstRun = false;
		
		# Obtain the settings
		if (!$databaseSettings = $this->databaseConnection->selectOne ('main', 'settings', array ('url' => $_SERVER['_SITE_URL']))) {
			$this->isFirstRun = true;
			$databaseSettings = array (	// $databaseSettings = false would crash array_merge below
				'categories' => false,
				'administrators' => false,
				'downloaders' => false,
				'batchUploaders' => false,
				'newsEditors' => false,
			);
		}
		
		# Add in the database settings, with the database settings taking priority
		$settings = array_merge ($settings, $databaseSettings);
		
		# Return the settings
		return $settings;
	}
	
	
	# Function to bootstrap the database structure; note the SQLite format comments: https://stackoverflow.com/questions/7426205/
	private function createDatabaseStructure ($databaseFile)
	{
		# Settings table
		$query = "
			CREATE TABLE IF NOT EXISTS main.settings (
			  `id` INTEGER PRIMARY KEY,						-- Site number
			  `url` VARCHAR(255) NOT NULL,					-- URL of site (match)
			  `applicationName` VARCHAR(255) NOT NULL,		-- Site name
			  `apiKey` VARCHAR(255) NOT NULL,				-- API key
			  `username` VARCHAR(255) NOT NULL,				-- Username for submissions
			  `password` VARCHAR(255) NOT NULL,				-- Password for submissions
			  `feedbackRecipient` VARCHAR(255) NOT NULL,	-- Contact page form recipient
			  `categories` TEXT NOT NULL,					-- Categories
			  `showOthers` INT(1) NULL,					-- Show submissions by others?
			  `privateSubmissions` INT(1) NULL,					-- Make submissions private?
			  `aboutPageHtml` TEXT NOT NULL,				-- About page text
			  `contactsPageHtml` TEXT NOT NULL,				-- Contact page text
			  `termsPageHtml` TEXT NOT NULL,				-- Terms page text
			  `administrators` TEXT NOT NULL,				-- E-mail logins of administrators
			  `downloaders` TEXT NOT NULL,					-- E-mail logins for access to downloads
			  `batchUploaders` TEXT NULL,					-- E-mail logins for access to batch upload section
			  `newsEditors` TEXT NULL,						-- E-mail logins for access to news editors
			  `defaultLatitude` FLOAT NOT NULL,				-- Default latitude
			  `defaultLongitude` FLOAT NOT NULL,			-- Default longitude
			  `defaultZoom` FLOAT NOT NULL,					-- Default zoom
			  `earliestDate` DATE,							-- Earliest date to appear in export
			  `bbox` VARCHAR(225) NOT NULL,					-- Bounding box for export
			  `trackingCode` TEXT NULL,						-- Analytics tracking code
			  `areas` TEXT,									-- Area names
			  `auditDataset` VARCHAR(255)					-- Audit dataset
			);
		";
		$this->databaseConnection->query ($query);
		
		# News table
		$query = "
			CREATE TABLE IF NOT EXISTS main.news (
			  `id` INTEGER PRIMARY KEY,						-- Article number
			  `area` VARCHAR(255),							-- Area
			  `title` VARCHAR(255) NOT NULL,				-- Title
			  `urlMoniker` VARCHAR(255) NOT NULL UNIQUE,			-- Web address
			  `articleRichtext` TEXT NOT NULL,				-- Text of article
			  `name` VARCHAR(255) NOT NULL,					-- Your name
			  `date` DATE									-- Date
			);
		";
		$this->databaseConnection->query ($query);
	}
	
	
	# Function to determine the style directory in use, not slash-terminated
	private function getStyleDirectory ($default = false)
	{
		# Get the folders in the directory
		$stylesLocation = '/style/';
		$directory = $_SERVER['DOCUMENT_ROOT'] . $stylesLocation;
		require_once ('directories.php');
		$styles = directories::listContainedDirectories ($directory, array (), '^([a-zA-Z0-9]+)$');
		
		# If forced to default style, return that
		if ($default) {return $stylesLocation . 'default';}
		
		# Find the first style which matches the domain name
		foreach ($styles as $style) {
			if (substr_count ($_SERVER['SERVER_NAME'], $style)) {
				return $stylesLocation . $style;
			}
		}
		
		# Return the default if no match
		return $stylesLocation . 'default';
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
	public function page404 ()
	{
		# Send the header
		application::sendHeader (404);
		
		# Show generic text if custom 404 not available
		$page = '/404.html';
		
		# Get the template
		$templateHtml = templating::convertDesignerHtmlToTemplate ($page, $this->styleDirectory, $this->replacedPlaceholders, $this->getStyleDirectory (true));
		
		# Get the HTML
		$html = templating::doTemplateSubstitution ($templateHtml, $this->template, $this->styleDirectory);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to load the template
	private function getTemplateHtml ($action)
	{
		# Do not attempt to fetch a template if no URL specified
		if (!$this->actions[$action]['url']) {return false;}
		
		# Add iframe versions of suggest/current; these have the full GUI, rather than just the map (as per embed)
		if (isSet ($_GET['iframe']) && $_GET['iframe'] == '1') {
			if (is_readable ($_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . $this->actions[$action]['url'] . 'iframe.html')) {
				$this->iframeSuffix = 'iframe.html';
				$this->actions[$action]['url'] .= $this->iframeSuffix;
			}
		}
		
		# Determine the location of the template
		$templateLocation = $this->actions[$action]['url'] . (substr ($this->actions[$action]['url'], -1) == '/' ? 'index.html' : '');	// Convert /path/ to /path/index.html
		
		# If the template does not exist, and the action is optional, signal this by returning false
		$templateFile = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . $templateLocation;
		if (!file_exists ($templateFile)) {
			if (isSet ($this->actions[$this->action]['optional']) && $this->actions[$this->action]['optional']) {
				return false;
			}
		}
		
		# Obtain the template
		$html = templating::convertDesignerHtmlToTemplate ($templateLocation, $this->styleDirectory, $this->replacedPlaceholders, $this->getStyleDirectory (true));
		
		# Return the template HTML
		return $html;
	}
	
	
	# Function to take an extracted part of the template and convert to ultimateForm form template format
	private function placeholderHtmlToFormTemplate ($placeholderName, $action, $optional = false, $selectedIdData = false, &$formFieldsInTemplate = array ())
	{
		# If internal placeholdering is optional, if there are no internal placeholders, end, meaning the form will be treated as a single block
		if ($optional) {
			if (!$this->replacedPlaceholders) {return false;}
		}
		
		# Obtain the form template which was extracted during the template pre-processing
		$htmlBlock = $this->replacedPlaceholders[$placeholderName];
		
		# Extract the HTML between placeholder-comments nested within the form template to leave a standard template for the form
		$template = templating::commentsToPlaceholders ($htmlBlock, $replacedPlaceholders);
		
		# Capture the list of form fields in the template and pass back by reference
		$formFieldsInTemplate = array_keys ($replacedPlaceholders);
		
		# Convert each replaced placeholder to ultimateForm format
		$replacements = array ();
		foreach ($replacedPlaceholders as $placeholder => $originalHtml) {
			$replacements[$placeholder] = '{' . $placeholder . '}';
			if ($placeholder == 'submit') {
				$replacements[$placeholder] = '{[[SUBMIT]]}';
			}
			if ($placeholder == 'map') {
				$mapLocation = (isSet ($selectedIdData['latitude']) ? $selectedIdData : array ());
				$replacements[$placeholder] = $this->locationsMap ($action, $mapLocation, true);
			}
		}
		
		# Substitute the placeholders for the ultimateForm placeholders
		$templateUltimateFormFormat = templating::doTemplateSubstitution ($template, $replacements);
		
		# Strip <form> and </form> tags if present
		$templateUltimateFormFormat = preg_replace ('/<form ([^>]+)>/s', '', $templateUltimateFormFormat);
		$templateUltimateFormFormat = str_replace ('</form>', '', $templateUltimateFormFormat);
		
		# Return the HTML
		return $templateUltimateFormFormat;
	}
	
	
	# Function to add analytics tracking code
	private function analyticsTrackingCode ($html)
	{
		# End if not required
		if (!$this->settings['trackingCode']) {return $html;}
		
		# Inject the tracking code
		$html = preg_replace ('/(<body([^>]*)>)/', "\\1\n<!-- Analytics tracking code -->\n" . $this->settings['trackingCode'] . "\n", $html);
		
		# Return the HTML
		return $html;
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
		
		# Enable caching to improve browser performance; see: https://stackoverflow.com/a/1583753/180733
		$lastModifiedTime = filemtime ($file);
		$etag = md5_file ($file);
		header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s', $lastModifiedTime) . ' GMT');
		header ('Etag: ' . $etag);
		$notModified = false;
		if (isSet ($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime ($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModifiedTime)) {$notModified = true;}
		if (isSet ($_SERVER['HTTP_IF_NONE_MATCH']) && (trim ($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)) {$notModified = true;}
		if ($notModified) {
			header ('HTTP/1.1 304 Not Modified');
			return true;
		}
		
#!# Move this check to constructor - should not have part of site working but not all
		# Ensure the fileinfo extension is loaded
		if (!function_exists ('finfo_open')) {
			$this->html .= "\n<p class=\"warning\">The website could not be loaded due to a configuration error (error #4). Please check back shortly.</p>";
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
	
	
	# Function to get the MIME type; this is basically a wrapper to finfo_file because of a PHP bug; see: https://stackoverflow.com/a/17736797/180733
	private function getMimeType ($file)
	{
		# Workaround for bug with finfo_file(); see https://bugs.php.net/53035
		$extension = pathinfo ($file, PATHINFO_EXTENSION);
		switch ($extension) {
			case 'css':
				return 'text/css';
			case 'js':
				return 'application/javascript';
			case 'svg':
				return 'image/svg+xml';
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
		
		# Add login form or status, where supported by the template
		if ($this->user) {
			$this->template['login'] = "<p>You are logged in.</a>";
		} else {
			$formHtml = '';
			$this->loginForm ($formHtml, false);
			$this->template['login'] = $formHtml;
		}
		
		# Add areas drop-down if supported
		$this->template['areas'] = $this->areasDropdown ();
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create an areas drop-down
	private function areasDropdown ()
	{
		# Determine the file or end if not supported
		$file = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . '/areas.csv';
		if (!file_exists ($file)) {return;}
		
		# Convert to CSV
		require_once ('libraries/csv.php');
		$areas = csv::getData ($file);
		
		# Construct the HTML
		$html  = "\n<select id=\"regionswitcher\">";
		$html .= "\n<option value=\"\">Go to borough:</option>";
		foreach ($areas as $area) {
			$html .= "\n\t<option value=\"{$this->baseUrl}/audit/#16/{$area['longitude']}/{$area['latitude']}\">" . htmlspecialchars ($area['name']) . '</option>';
		}
		$html .= "\n</select>";
		$html .= "\n" . '<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>';
		$html .= "\n<script type=\"text/javascript\">
			$('#regionswitcher').change (function () {
				if (this.value) {
					window.location = $(this).val();
				}
			});
		</script>
		";
		
		# Return the HTML
		return $html;
	}
	
	
	# Suggest a location page
	private function suggest ($existingData = array ())
	{
		# Start the HTML
		$html = '';
		
		# If there are multiple categories, force selection
		$category = $this->categories[0];
		if (!$existingData) {
			if (count ($this->categories) > 1) {
				
				# Force selection if not specified
				if (!isSet ($_GET['category']) || !strlen ($_GET['category'])) {
					$this->template['form'] = $this->categorySelection ();
					return true;
				}
				
				# End if not valid
				if (!in_array ($_GET['category'], $this->categories)) {
					$html = $this->page404 ();
					echo $html;
					return false;
				}
				
				# Register the category
				$category = $_GET['category'];
			}
		}
		
		# Finalise the API URL
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%category', $category, $this->actions[__FUNCTION__]['apiUrl']);
		
		# Show the submission page
		$html = $this->submissionPage (__FUNCTION__, $category, $existingData);
		
		# Register the HTML
		$this->template['form'] = $html;
	}
	
	
	# Function to create category selection
	private function categorySelection ()
	{
		# Create the list
		$list = array ();
		foreach ($this->categories as $category) {
			$list[$category] = "<a href=\"{$this->baseUrl}{$this->actions[$this->action]['url']}{$category}/\">" . htmlspecialchars ($this->categoryLabels[$category]['plural']) . '</a>';
		}
		
		# Compile the HTML
		$html  = "\n<p>Please select a category:</p>";
		$html .= application::htmlUl ($list, 0, 'spaced');
		
		# Return the HTML
		return $html;
	}
	
	
	# Page for auditing of current locations
	private function current ($existingData = array ())
	{
		# Start the HTML
		$html = '';
		
		# Set the category
		// #!# Multiple category support not yet in place - see code in suggest which is probably repurposable
		$category = $this->categories[0];
		
		# Finalise the API URL
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%category', $category, $this->actions[__FUNCTION__]['apiUrl']);
		
		# Show the submission page
		$html = $this->submissionPage (__FUNCTION__, $category, $existingData);
		
		# Register the HTML
		$this->template['form'] = $html;
	}
	
	
	# Page for audit map page browsing
	private function audit ($existingData = array ())
	{
		# End if not enabled
		if (!$this->settings['auditDataset']) {return false;}
		
		# Obtain the schema
		if (!$schema = $this->getAuditSchema ()) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Show the audit map
		$this->auditMap ($schema);
		
		# Add areas drop-down if supported
		$this->template['areas'] = $this->areasDropdown ();
	}
	
	
	# Audit map for browsing
	private function auditMap ($schema)
	{
		# Assign the popup labels
		$this->auditSetPopupLabels ($schema);
		
		# Finalise the API URL
		$this->actions[$this->action]['apiUrl'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[$this->action]['apiUrl']);
		$this->actions[$this->action]['apiUrl2'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[$this->action]['apiUrl2']);
		
		# Create the map HTML
		$html  = "\n" . '<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>';
		$html .= $this->locationsMap ($this->action, false, false, $viewOnlyMode = true);
		
		# Register the HTML
		$this->template['map'] = $html;
	}
	
	
	# Function to set the popup labels from the schema
	private function auditSetPopupLabels ($schema, $flatten = true)
	{
		# Flatten the schema if required
		#!# Creates clashing names where not unique within container
		if ($flatten) {
			$schemaFlattened = array ('fields' => array ());
			foreach ($schema as $id => $type) {
				$schemaFlattened['fields'] += $type['fields'];
			}
			$schema = $schemaFlattened;
		}
		
		# Assign the labels
		$popupLabels = array ();
		foreach ($schema['fields'] as $fieldname => $field) {
			$popupLabels[$fieldname] = $field['field'];
		}
		
		# Add core fields
		$popupLabels['surveyDate'] = 'Survey date';
		
		# Hide internal fields
		$popupLabels['_type'] = NULL;
		$popupLabels['iconUrl'] = NULL;
		$popupLabels['road_name'] = NULL;
		$popupLabels['osm_id'] = NULL;
		
		# Set the labels
		$this->popupLabels = $popupLabels;
		#!# Not yet working
		$this->popupLabelSubsetField = false;
	}
	
	
	# Page for adding a location, as stage 1 to select the type
	private function auditadd ()
	{
		# End if not enabled
		if (!$this->settings['auditDataset']) {return false;}
		
		# Obtain the schema
		if (!$schema = $this->getAuditSchema ()) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Show the audit map
		$this->auditMap ($schema);
	}
	
	
	# Page for adding a location, as stage 2 to add the location
	private function auditaddlocation ()
	{
		# End if not enabled
		if (!$this->settings['auditDataset']) {return false;}
		
		# Obtain the schema
		if (!$schema = $this->getAuditSchema ()) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Get the category (type) or end
		if (!isSet ($_GET['type'])) {
			$html = $this->page404 ();
			return;
		}
		
		# Obtain the category (type), or end
		$category = (isSet ($_GET['type']) ? $_GET['type'] : false);
		if (!isSet ($schema[$category])) {
			#!# Rest of GUI is still showing
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Set the category for the template
		$this->template['category'] = $schema[$category]['name'];
		
		# Finalise the API URL
		$this->actions[$this->action]['apiUrl'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[$this->action]['apiUrl']);
		$this->actions[$this->action]['apiUrl2'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[$this->action]['apiUrl2']);
		$this->actions[$this->action]['apiUrl'] = str_replace ('%type', $category, $this->actions[$this->action]['apiUrl']);
		
		# Assign the popup labels
		$this->auditSetPopupLabels ($schema[$category], $flatten = false);
		
		# Create the audit form (with map)
		if ($result = $this->auditFormPresent ($schema[$category]['fields'], $category, array ())) {
			$this->template['presentForm'] = $this->auditPresentCommit ($result, false, $category);
		}
	}
	
	
	# Page for auditing an existing location
	private function auditlocation ()
	{
		# End if not enabled
		if (!$this->settings['auditDataset']) {return false;}
		
		# Ensure there is an ID
		if (!isSet ($_GET['id']) || !strlen ($_GET['id'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$id = $_GET['id'];
		
		# Finalise the API URL
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[__FUNCTION__]['apiUrl']);
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%id', $id, $this->actions[__FUNCTION__]['apiUrl']);
		$apiUrl = $this->settings['apiBase'] . $this->actions[__FUNCTION__]['apiUrl'] . '&key=' . $this->settings['apiKey'];
		
		# Obtain the data
		$data = file_get_contents ($apiUrl);
		$data = json_decode ($data, true);
		
		# End if no such ID
		if (isSet ($data['error'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Set the (now-validated) ID for the template
		$this->template['id'] = $id;
		
		# Extract the single feature
		$data = $data['features'][0];
		
		# Extract the category (type)
		$category = $data['properties']['_type'];
		
		# Obtain the schema
		if (!$schema = $this->getAuditSchema ($category)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Assign the popup labels
		$this->auditSetPopupLabels ($schema, $flatten = false);
		
		# Add memory support for tabs to avoid loss of correct tab on POST; see: https://stackoverflow.com/a/18602487
		$this->template['tabsJs'] = "
		<script src=\"https://code.jquery.com/ui/1.12.1/jquery-ui.js\"></script>
		<script src=\"https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js\"></script>
		<script>
			$(function() {
				var cookieName = 'location{$id}-activetab';	// Namespaced by location
				$('#tabs').tabs({
					active : Cookies.get (cookieName),
					activate : function (event, ui){
						Cookies.set (cookieName, ui.newTab.index (), {
							expires: 7
						});
					}
				});
			} );
		</script>
		";
		
		# Create the audit location present form (with map)
		if ($result = $this->auditFormPresent ($schema['fields'], $category, $data)) {
			$this->template['presentForm'] = $this->auditPresentCommit ($result, $id, false);
		}
		
		# Create the unchanged form
		if ($result = $this->auditStatusChangeForm ('unchanged', 'unchangedForm')) {
			$this->template['unchangedForm'] = $this->auditStatusCommit ('infrastructure.unchanged', $result, $id, 'unchanged');
		}
		
		# Create the gone form
		if ($result = $this->auditStatusChangeForm ('no longer present', 'deleteForm')) {
			$this->template['deleteForm'] = $this->auditStatusCommit ('infrastructure.delete', $result, $id, 'no longer present');
		}
	}
	
	
	# Function to create the audit form for infrastructure present, which includes the map
	private function auditFormPresent ($fields /* for the current category */, $category, $data = array () /* or GeoJSON feature */)
	{
		# Extract the properties for dataBinding and the map popup
		$locationData = ($data ? $data['properties'] : array ());
		
		# Combine mutually-exclusive boolean fields into a single drop-down
		$fieldsOriginal = $fields;	// Cache for later use
		$locationDataOriginal = $locationData;
		$fields = $this->auditFormCombineBooleanFields ($fields, $locationData /* amended by reference */, $combinationValues /* returned by reference */);
		
		# Convert boolean true/false to checkbox
		$fields = $this->auditFormConvertBooleanCheckbox ($fields, $locationData /* amended by reference */);
		
		# Reformat descriptions
		foreach ($fields as $fieldname => $field) {
			if ($field['datatype'] == 'INT(1)') {
				$fields[$fieldname]['description'] = str_replace ('False =', "<br />" . 'False = ', $fields[$fieldname]['description']);
				$fields[$fieldname]['description'] = str_replace ('True =', '&#10004; = ', $fields[$fieldname]['description']);
				$fields[$fieldname]['description'] = str_replace ('False =', '<span class="faded">&#9633;</span> =', $fields[$fieldname]['description']);
			}
		}
		
		# Add documentation link if present
		foreach ($fields as $fieldname => $field) {
			if ($field['documentationUrl']) {
				$fields[$fieldname]['description'] .= ($fields[$fieldname]['description'] ? '<br />' : '') . "<a href=\"{$field['documentationUrl']}\" target=\"_blank\" title=\"[Link opens in a new window]\">Full details</a>";
			}
		}
		
		# Convert the schema to dataBinding schema format
		$schemaDatabinding = array ();
		$attributes = array ();
		foreach ($fields as $fieldname => $field) {
			#!# Also need to be supplying $field['description']
			$schemaDatabinding[$fieldname] = $this->sqlFieldnameToStructure ($field['datatype'], $field['field']);
			$attributes[$fieldname]['description'] = $field['description'];
			if (isSet ($field['labels'])) {
				$attributes[$fieldname]['values'] = $field['labels'];
			}
		}
		
		# Set combined fields to be required, as they indicate an overall type selection
		foreach ($combinationValues as $combinedField => $values) {
			$attributes[$combinedField]['required'] = true;
		}
		
		# Assemble selected ID data
		$selectedIdData = array ();
		if ($data) {
			$selectedIdData = array (
				'id' => $data['properties']['id'],
				'latitude' => $data['geometry']['coordinates'][1],
				'longitude' => $data['geometry']['coordinates'][0],
				'zoom' => 16,
			);
		}
		
		# Create the map HTML
		$mapHtml  = "\n" . '<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>';
		$mapHtml .= $this->locationsMap ($this->action, $selectedIdData, $markerSetInitiallyIsDraggable = true, false, $selectedIdData, false, $locationDataOriginal);
		$this->template['map'] = $mapHtml;
		
		# Create a new form
		$formHtml = '';
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'requiredFieldIndicator'	=> false,
			'submitButtonText'		=> 'Save changes &nbsp; &gt;',
			'submitButtonAccesskey'		=> false,
			'nullText'			=> false,
			'div'				=> 'auditform',
			'labelsSurround'		=> true,
			'uploadThumbnailWidth'		=> 160,
			'uploadThumbnailHeight'		=> 120,
		));
		if ($data) {
			$form->heading ('p', 'Please check the map location to ensure it is correct. If not, you can drag the marker to give an accurate location.');
		} else {
			$form->heading ('p', 'Firstly, click on the map to set the location. You can then drag the marker to get an accurate location.');
		}
		$form->dataBinding (array (
			'schema' => $schemaDatabinding,
			'intelligence' => true,
			'int1ToCheckbox' => true,
			'data' => $locationData,
			'attributes' => $attributes,
		));
		
		# Add photo upload
		$tempDir = sys_get_temp_dir () . '/';
		$photos = 2;
		$form->upload (array (
			'name' => 'photos',
			'title' => 'Two photos',
			'description' => 'Must be JPG format',
			'directory' => $tempDir,
			'allowedExtensions' => array ('jpg', 'jpeg'),	// 'jpeg' variant needed as iOS picker may supply as *.jpeg
			'draganddrop' => true,
			'subfields' => $photos,
			#!# Needs to be uniqued per session
			'forcedFileName' => array ('photo0', 'photo1'),
			'default' => ($locationData ? $locationData['images'] : false),
			// Size is set above
		));
		
		# Add survey date
		$form->datetime (array (
			'name' => 'surveyDate',
			'title' => 'Survey date',
			'description' => 'Date when this location was surveyed on-street',
			'required' => true,
			'picker' => true,
			'default' => date ('Y-m-d'),
		));
		
		# Location (hidden)
		$this->addHiddenLocationFields ($form /* modified by reference */, $formHtml /* modified by reference */, $selectedIdData);
		
		# Process the form, and send to the template
		$result = $form->process ($formHtml);
		$this->template['presentForm'] = $formHtml;
		if (!$result) {return false;}
		
		# Un-convert boolean true/false to checkbox
		$result = $this->auditFormUnconvertBooleanCheckbox ($result, $fieldsOriginal);
		
		# Un-combine mutually-exclusive boolean fields into a single drop-down
		$result = $this->auditFormUncombineBooleanFields ($result, $combinationValues, $fieldsOriginal);
		
		# Ensure files use .jpg rather than .jpeg
		#!# Should be a generic option in ultimateForm
		for ($i = 0; $i < $photos; $i++) {
			$file = $tempDir . $result['photos'][$i];
			if (preg_match ('/.jpeg$/', $file)) {
				$result['photos'][$i] = preg_replace ('/.jpeg$/', '.jpg', $result['photos'][$i]);	// Update variable
				rename ($file, $tempDir . $result['photos'][$i]);	// Move file
			}
		}
		
		# Split out and prepare the photos fields
		$result['photo0'] = $this->prepareFile ($tempDir . $result['photos'][0]);
		$result['photo1'] = $this->prepareFile ($tempDir . $result['photos'][1]);
		unset ($result['photos']);
		
		# Assemble the location field to a GeoJSON geometry
		$geometry = array (
			'type' => 'Point',
			'coordinates' => array ((float) number_format ($result['longitude'], 6), (float) number_format ($result['latitude'], 6)),
		);
		unset ($result['latitude'], $result['longitude'], $result['zoom']);
		$result['location'] = json_encode ($geometry);
		
		# Return the result
		return $result;
	}
	
	
	# Audit form helper function to combine mutually-exclusive boolean fields into a single drop-down
	private function auditFormCombineBooleanFields ($fields, &$data, &$combinationValues)
	{
		# Combine separate boolean fields to a drop-down, where present
		$fieldsCombined = array ();
		$combinationValues = array ();
		foreach ($fields as $fieldname => $field) {
			if ($field['combine'] && $field['datatype'] == "ENUM('TRUE','FALSE')") {
				$combinedFieldname = $field['combine'];
				$fieldsCombined[$combinedFieldname] = array (
					'fieldname'	=> $combinedFieldname,
					'field'		=> $field['combineLabel'],
					'description'	=> $field['combineLabel'],
					'datatype'	=> NULL,	// Will be populated at the end from $combinationValues
					'documentationUrl'	=> $field['documentationUrl'],
					'labels'	=> NULL,	// Will be populated at the end from $combinationValues
				);
				$combinationValues[$combinedFieldname][$fieldname] = $field['field'];
				// Do not copy the original field across
				
				# Amend the data for this field also
				if (array_key_exists ($fieldname, $data)) {
					$data[$combinedFieldname] = $fieldname;
					unset ($data[$fieldname]);
				}
			} else {
				$fieldsCombined[$fieldname] = $field;	// Copy-as is, done to ensure that the ordering remains
			}
		}
		if ($combinationValues) {
			foreach ($combinationValues as $combinedFieldname => $values) {
				$fieldsCombined[$combinedFieldname]['datatype'] = "ENUM('" . implode ("','", array_keys ($values)) . "')";
				$fieldsCombined[$combinedFieldname]['labels'] = $combinationValues[$combinedFieldname];
			}
		}
		
		# Return the potentially-combined fields
		return $fieldsCombined;
	}
	
	
	# Audit form helper function to un-combine a drop-down back to separate boolean fields
	private function auditFormUncombineBooleanFields ($result, $combinationValues, $fieldsOriginal)
	{
		# Substitute back
		$resultUncombined = array ();
		foreach ($result as $field => $value) {
			if (isSet ($combinationValues[$field])) {
				foreach ($combinationValues[$field] as $originalField => $label) {
					$result[$originalField] = ($value == $originalField ? 'TRUE' : 'FALSE');
				}
				unset ($result[$field]);
			}
		}
		
		# Reorder as per original schema
		$fields = array_keys ($fieldsOriginal);
		#!# Should be determined automatically rather than using this hard-coded list
		$fields[] = 'surveyDate';
		$fields[] = 'latitude';
		$fields[] = 'longitude';
		$fields[] = 'zoom';
		$fields[] = 'photos';
		$result = application::arrayFields ($result, $fields);
		
		# Return the result
		return $result;
	}
	
	
	# Audit form helper function to convert boolean true/false to checkbox
	private function auditFormConvertBooleanCheckbox ($fields, &$data)
	{
		# Convert relevant fields
		foreach ($fields as $fieldname => $field) {
			if ($field['datatype'] == "ENUM('TRUE','FALSE')") {
				$fields[$fieldname]['datatype'] = 'INT(1)';
				
				# Amend the data for this field also
				if ($data) {
					if ($data[$fieldname] == 'TRUE') {$data[$fieldname] = 1;}
					if ($data[$fieldname] == 'FALSE') {$data[$fieldname] = '';}
				}
			}
		}
		
		# Return the fields
		return $fields;
	}
	
	
	# Audit form helper function to convert un-convert checkbox back to boolean true/false
	private function auditFormUnconvertBooleanCheckbox ($result, $fieldsOriginal)
	{
		# Unconvert relevant fields
		foreach ($fieldsOriginal as $fieldname => $field) {
			if ($field['datatype'] == "ENUM('TRUE','FALSE')") {
				
				# Convert the data back
				if ($result[$fieldname] == 1) {$result[$fieldname] = 'TRUE';}
				if ($result[$fieldname] == '') {$result[$fieldname] = 'FALSE';}
			}
		}
		
		# Return the result
		return $result;
	}

	# Helper function to get the schema for auditing
	private function getAuditSchema ($category = false)
	{
		# Obtain the schema
		$schemaUrl = $this->settings['apiBase'] . '/v2/infrastructure.schema?key=' . $this->settings['apiKey'] . '&dataset=' . $this->settings['auditDataset'];
		if ($category) {
			$schemaUrl .= '&type=' . $category;
		}
		$schema = file_get_contents ($schemaUrl);
		$schema = json_decode ($schema, true);
		
		# End if no schema (which should never happen if the data in the API is consistent)
		if (isSet ($schema['error'])) {
			return false;
		}
		
		# Return the schema
		return $schema;
	}
	
	
	# Function to commit the results of an audit form for infrastructure present
	private function auditPresentCommit ($result, $updateId = false, /* or if insert instead: */ $category = false)
	{
		# Assemble the update
		$location = $result['location'];
		unset ($result['location']);
		$photo0 = $result['photo0'];
		$photo1 = $result['photo1'];
		unset ($result['photo0']);
		unset ($result['photo1']);
		$data = array (
			'dataset'	=> $this->settings['auditDataset'],
			#!# API should really be renamed location
			'geometry'	=> $location,
			'attributes'	=> json_encode ($result),
			'surveydate'	=> $result['surveyDate'],
			'photo0'	=> $photo0,
			'photo1'	=> $photo1,
		);
		if ($updateId) {
			$data['id'] = $updateId;
		} else {
			$data['type'] = $category;
		}
		
		# Perform the commit; see: https://www.cyclestreets.net/api/v2/infrastructure.update/
		$apiCall = ($updateId ? 'infrastructure.update' : 'infrastructure.add');
		$schemaUrl = $this->settings['apiBase'] . '/v2/' . $apiCall . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($schemaUrl, $data, $multipart = true);
		$result = json_decode ($result, true);
		//application::dumpData ($result);
		
		# Construct the URL of the new location
		$url = "/audit/location/{$result['id']}/";
		
		# Confirm outcome
		$action = ($updateId ? 'updated' : 'added');
		return $this->auditConfirmation ($result, $action, $url);
	}
	
	
	# Function to confirm the outcome of the audit form change
	private function auditConfirmation ($result, $action /* added/updated */, $urlLink)
	{
		#!# Error handling needed
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
		$resultHtml  = "<p>{$unicodeTick} Thank you! This location has now been {$action}.</p>";
		if ($urlLink) {
			$resultHtml .= "\n<p>You can now <a href=\"{$urlLink}\">see it on the map or edit it further</a> if you wish.</p>";
		}
		$resultHtml .= "\n<p>Having up-to-date data like this helps apps, mapping, transport planning, and other uses that help cyclists.</p>";
		return $resultHtml;
	}
	
	
	# Form to mark a location with a new status (unchanged/gone)
	private function auditStatusChangeForm ($label, $placeholder)
	{
		# Create a new form
		$formHtml = '';
		require_once ('ultimateForm.php');
		$form = new form (array (
			'name' => str_replace ('Form', '', $placeholder),
			'submitButtonText'		=> "Mark as {$label} &nbsp; &gt;",
			'submitButtonAccesskey'		=> false,
		));
		$form->heading ('p', "Or you can mark this location as {$label}:");
		
		# Add survey date
		$form->datetime (array (
			'name' => 'surveyDate',
			'title' => 'Survey date',
			'description' => 'Date when this location was surveyed on-street',
			'required' => true,
			'picker' => true,
			'default' => date ('Y-m-d'),
		));
		
		# Process the form, and send to the template
		$result = $form->process ($formHtml);
		$this->template[$placeholder] = $formHtml;
		if (!$result) {return false;}
		
		# Return the result
		return $result;
	}
	
	
	# Function to commit the results of an audit form for infrastructure unchanged/gone
	private function auditStatusCommit ($apiMethod, $result, $id, $label)
	{
		# Assemble the update
		$data = array (
			'dataset'	=> $this->settings['auditDataset'],
			'id'		=> $id,
			'surveydate'	=> $result['surveyDate'],
		);
		
		# Perform the commit; see: https://www.cyclestreets.net/api/v2/infrastructure.update/
		$schemaUrl = $this->settings['apiBase'] . '/v2/' . $apiMethod . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($schemaUrl, $data, $multipart = true);
		$result = json_decode ($result, true);
		//application::dumpData ($result);
		
		# Confirm outcome
		return $this->auditConfirmation ($result, 'marked as ' . $label, false);
	}
	
	
	# Page to set priority areas
	private function priorityareas ()
	{
		# Finalise the API URL
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[__FUNCTION__]['apiUrl']);
		
		# Create the map, in drawing mode
		$mapHtml  = "\n" . '<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>';
		$mapHtml .= $this->locationsMap (__FUNCTION__, false, false, $viewOnlyMode = true, array (), $enableDrawing = true);
		$this->template['map'] = $mapHtml;
		
		# Handle posted data
		$this->template['result'] = '';
		if (isSet ($_POST['loc_json'])) {
			
			# Construct the GeoJSON geometry structure
			$geometry = array (
				'type' => 'Polygon',
				'coordinates' => array (
					json_decode ($_POST['loc_json'], true)	// Single feature only
				),
			);
			
			# Assemble the data to be submitted to the API; see: https://www.cyclestreets.net/api/v2/infrastructure.priorityareas.add/
			$data = array (
				'dataset' => $this->settings['auditDataset'],
				'geometry' => json_encode ($geometry),
				'name' => (isSet ($_POST['name']) ? $_POST['name'] : NULL),
			);
			
			# Post the data
			$url = $this->settings['apiBase'] . '/v2/infrastructure.priorityareas.add' . '?key=' . $this->settings['apiKey'];
			$result = application::file_post_contents ($url, $data);
			$result = json_decode ($result, true);
			
			# State if error
			if ($result['error']) {
				$this->template['result'] = "<p>Sorry, a problem occured when trying to set the priority area. Please try again later.</p>";
				return;
			}
			
			# Refresh the page, which will show the added location
			application::sendHeader ('refresh');
			return;
		}
	}
	
	
	# Function to convert an SQL-style fieldname definition to a MySQL-style data structure
	private function sqlFieldnameToStructure ($sqlFieldname, $comment)
	{
		# Extract values
		preg_match ('/^(varchar|integer|enum)\((.+)\)$/', strtolower ($sqlFieldname), $matches);
		
		# Assemble the field
		$field = array (
			'Type' => $sqlFieldname,
			'Null' => true,
			'Key' => false,
			'Default' => NULL,
			'Extra' => false,
			'Comment' => $comment,
			'_values' => ($matches[1] == 'ENUM' ? str_getcsv ($matches[2], ',', "'"): NULL),
		);
		
		# Return the field data
		return $field;
	}
	
	
	# Embeddable map iframe pages
	private function embed ()
	{
		# Start the HTML
		$html = '';
		
		# Define the embeddable map types
		$types = array (
			'suggest' => 'Suggested %categoryLabel',
			'current' => 'Current %categoryLabel',
		);
		
		# Determine the type selected, if any, throwing a 404 if invalid
		$type = false;
		if (isSet ($_GET['type'])) {
			$type = $_GET['type'];
			if (!isSet ($types[$type])) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
		}
		
		# Show listing if no type selected
		if (!$type) {
			$tableHtml  = "\n<table class=\"buttons\">";
			foreach ($this->categories as $category) {
				$tableHtml .= "\n\t<tr>";
				foreach ($types as $type => $label) {
					$label = str_replace ('%categoryLabel', $this->categoryLabels[$category]['plural'], $label);
					$tableHtml .= "\n\t\t<td style=\"padding: 20px;\">" . "<a class=\"button color huge circle\" href=\"/{$type}/embed/\">{$label}</a></td>";
				}
				$tableHtml .= "\n\t</tr>";
			}
			$tableHtml .= "\n</table>";
			$this->template['links'] = $tableHtml;
			return true;
		}
		
		# Set to show the customisation page if location parameters not present
		$parameters = array ('latitude', 'longitude', 'zoom');
		$showCustomisationPage = false;
		foreach ($parameters as $parameter) {
			if (!isSet ($_GET[$parameter]) || !strlen ($_GET[$parameter])) {
				$showCustomisationPage = true;
				break;
			}
		}
		
		# Define the map HTML
		$mapHtml = $this->locationsMap ($type, false, false, $viewOnlyMode = true, $_GET);
		
		# Show the customisation page if required
		if ($showCustomisationPage) {
			
			# Construct the introduction HTML
			$html .= "\n<p>Here, you can create a map widget to embed on your own website.</p>";
			$html .= "\n<p>The map widget will show <strong>" . lcfirst (str_replace ('%categoryLabel', $this->categoryLabels[$this->categories[0]['plural']], $types[$type])) . "</strong> [<a href=\"{$this->baseUrl}/embed/\">change?</a>].</p>";
			$html .= "\n<p>To add it to your website:</p>";
			$html .= "\n<style type=\"text/css\">
				div.code {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc; overflow: auto;}
				div.code pre {font-size: 0.83em;}
				ol.instructions {margin-bottom: 2em;}
				ol.instructions li {margin-top: 5px;}
				ol {margin-left: 10px; padding-left: 10px; list-style: decimal;}	/* #!# Undo override in style.css */
			</style>";
			$html .= "\n<ol class=\"instructions\">
				<li>Firstly, position the map below to your desired location and zoom</li>
				<li>Copy this iframe HTML code:
					<div class=\"code\">
					<pre>". htmlspecialchars ('<iframe src="') . "<span id=\"currentMapLocationUrl\">//{$_SERVER['SERVER_NAME']}{$this->baseUrl}/{$type}/embed/</span>" . htmlspecialchars ('" width="100%" height="500" frameborder="0"></iframe>') . "</pre>
					</div>
				</li>
				<li>Paste the HTML into your own webpage.</li>
			</ol>";
			
			# Overwrite the default template by creating a virtual action and using its template
			$action = 'embed_instructions';
			$this->actions[$action]['url'] = '/embed/instructions.html';
			$this->templateHtml = $this->getTemplateHtml ($action);
			
			# Write into the template
			$this->template['instructions'] = $html;
			$this->template['map'] = $mapHtml;
			return true;
		}
		
		# Overwrite the default template by creating a virtual action and using its template
		$action = 'embed_iframe';
		$this->actions[$action]['url'] = '/embed/iframe.html';
		$this->templateHtml = $this->getTemplateHtml ($action);
		
		# Write into the template
		$this->template['title'] = "{$this->settings['applicationName']} - embedded map of " . lcfirst ($types[$type]) . ' locations';
		$this->template['map'] = $mapHtml;
		$this->template['about'] = "Powered by <a href=\"https://{$_SERVER['SERVER_NAME']}/{$type}/\" target=\"_top\">{$this->settings['applicationName']}</a> - contribute your knowledge";
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
		#!# /v2/photomap.location metacategoryId,categoryId are inconsistent with metacategory,category in photomap.add/photomap.update
		$apiUrl = $this->settings['apiBase'] . '/v2/photomap.location' . '?key=' . $this->settings['apiKey'] . '&id=' . $id . '&format=flat' . '&fields=id,metacategoryId,categoryId,caption,latitude,longitude,zoom,basemap,credit,additionalMetadata,hasPhoto,thumbnailUrl,likes' . '&thumbnailsize=400';
		
		# Enable private submissions if required
		if ($this->settings['privateSubmissions']) {
			$apiUrl .= '&private=1';
		}
		
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
		
		# End if not a supported metacategory or category
		if (!array_key_exists ($data['metacategoryId'], $this->metacategories) || !in_array ($data['categoryId'], $this->categories)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Assign the virtual action (e.g. if the data's metacategory is 'bad', then the action is 'suggest')
		$action = $this->metacategories[$data['metacategoryId']];
		
		# Divert to Like action if required
		if (isSet ($_GET['mode'])) {
			if ($_GET['mode'] == 'like') {
				
				# Permit only on suggested parking page
				if ($action != 'suggest') {
					$html = $this->page404 ();
					echo $html;
					return false;
				}
				
				# Delegate to Like class
				require_once ('classes/like.class.php');
				$like = new like ($this);
				$like->main ($data);
				$html .= $like->getHtml ();
				echo $html;
				return false;
			}
		}
		
		# Start an editing rights session and determine if the user has edit rights
		$userCanEdit = NULL;
		$this->sessionInit ();
		if ($locationCreationTime = $this->sessionGet ("id_{$id}")) {
			$editableUntilUnixtime = $locationCreationTime + $this->settings['editabilityPeriod'];
			$userCanEdit = (time () < $editableUntilUnixtime);
		}
		
		# Divert to CRUD action if required
		$userEditMessage = false;
		if (isSet ($_GET['mode'])) {
			
			# Validate requested action
			$crudActions = array ('edit', );
			if (!in_array ($_GET['mode'], $crudActions)) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
			
			# End if no editing rights
			if ($userCanEdit === NULL) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
			
			# Run the CRUD method if the user can edit
			if ($userCanEdit) {
				$method = __FUNCTION__ . ucfirst ($_GET['mode']);	// e.g. locationEdit()
				return $this->$method ($id, $action, $data);	// Pass in existing data $data
			} else {
				$userEditMessage = "\n<p>Editing of this entry is no longer possible.</p>";
				// Drop back to page viewing as below
			}
		}
		
		# Start the metadata panel with the caption
		$metadataHtml = '';
		if ($data['caption']) {
			$metadataHtml .= application::formatTextBlock (htmlspecialchars ($data['caption']), 'metadata');
		}
		
		# Show the thumbnail if present (workaround until this and the metadata can be shown in the bubble)
		if ($data['hasPhoto']) {
			$metadataHtml .= "\n<p><img src=\"{$data['thumbnailUrl']}\" alt=\"\" border=\"0\"></p>";
		}
		
		# Determine the category
		$category = $data['categoryId'];
		
		# Show additional metadata table
		if ($data['additionalMetadata']) {
			
			# Filter to supported fields for this action
			$additionalMetadataFields = explode (',', $this->actions[$action]['additionalMetadata'][$category]);
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
		if ($confirmationString = application::getFlashMessage ($this->settings['flashMessageName'], $this->baseUrl . '/')) {
			if (preg_match ('/^([1-9][0-9]*)-([a-z]+)$/', $confirmationString, $matches)) {		// e.g. 80-update or 80-insert
				list ($confirmationId, $type) = array ($matches[1], $matches[2]);
				if ($confirmationId == $id) {
					$flashMessage = $this->confirmationMessage ($confirmationId, ($type == 'update'), $action);
					$flashMessage = "\n<div class=\"notification success\">" . $flashMessage . "\n</div>";
				}
			}
		}
		
		# Add an edit link
		$editlink = false;
		if ($userCanEdit) {
			$editlink = "\n<p id=\"editlink\"><a href=\"{$this->baseUrl}/location/{$id}/edit/" . $this->iframeSuffix . "\"><img src=\"{$this->baseUrl}/images/icons/pencil.png\" alt=\"\" width=\"16\" height=\"16\" border=\"0\" /> Edit</a></p>";
		}
		
		# Register HTML components
		$this->template['id'] = str_replace ('%categoryLabel', lcfirst ($this->categoryLabels[$category]['singular']), $this->actions[$action]['description']) . ' &mdash; #' . $id;
		$this->template['message'] = $flashMessage . $userEditMessage;
		$this->template['editlink'] = $editlink;
		$this->template['map'] = $this->locationsMap ($action, $data, false);
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
	private function submissionPage ($action, $category, $existingData = array (), $schema = array ())
	{
		# Start the HTML
		$html = '';
		
		# Create the form and process the data
		if (!$data = $this->locationSubmissionForm ($action, $existingData, $schema, $html)) {		// &html written into by reference
			return $html;
		}
		
		# Send the data (including any image) to the API
		if (!$result = $this->postSubmission ($data, $action, $category, 'publicdomain', $this->tmpDirectory, $existingData, $errorText)) {
			$html = "\n<p class=\"warning\">" . htmlspecialchars ($errorText) . '</p>';
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
		if (isSet ($data['mailinglist'])) {	// Templates can chose to include/omit this optionally
			if ($data['mailinglist'] == 'Yes') {
				$file = $_SERVER['DOCUMENT_ROOT'] . '/db/mailinglist.csv';
				$string = $data['email'] . ',' . $data['name'] . ',' . $result['id'] . "\n";
				file_put_contents ($file, $string, FILE_APPEND);
			}
		}
		
		# Determine the redirection target, namely the location page
		$redirectToPath = $this->baseUrl . "/location/{$result['id']}/" . $this->iframeSuffix;
		
		# Thank the user, resetting the HTML
		$html  = $this->confirmationMessage ($result['id'], $existingData, $action);
		$html .= "\n<p><a href=\"{$redirectToPath}\">Click here to continue to the next page.</a></p>";
		
		# Add editing rights into the session, by logging the current time for this ID
		$this->sessionWrite ("id_{$result['id']}", time ());
		
		# Set a flash message and redirect the user (which will override the confirmation above)
		$valueString = $result['id'] . ($existingData ? '-update' : '-insert');
		application::setFlashMessage ($this->settings['flashMessageName'], $valueString, $redirectToPath, $html, $this->baseUrl . '/');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to set the addition confirmation message
	private function confirmationMessage ($id, $isUpdate, $action)
	{
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
		$html  = "\n<p>{$unicodeTick}" . ($isUpdate ? '<strong> Thank you for your update</strong>.' : "<strong> Thank you for your submission</strong>, which is number {$id}.") . '</p>';
		$html .= "\n<p><a href=\"{$this->actions[$action]['url']}{$this->iframeSuffix}\">Add another?</a></p>";
		return $html;
	}
	
	
	# Function to post submissions to the API
	private function postSubmission ($rawdata, $action, $category, $license, $filePath, $existingData, &$errorText = '')
	{
		# Define the API URL; note this uses a POST operation due to the presence of a username and password
		$apiCall = ($existingData ? 'photomap.update' : 'photomap.add');
		$apiUrl = $this->settings['apiBase'] . '/v2/' . $apiCall . '?key=' . $this->settings['apiKey'];
		
		# If the message is empty, add a generic message as the API sets caption as a required field
		if (empty ($rawdata['caption'])) {
			$rawdata['caption'] = $this->categoryLabels[$category]['singular'] . ' ' . ($action == 'suggest' ? 'needed' : 'present') . ' here.';
		}
		
		# Map the fields to the API
		$data = array (
			#!# Currently a fixed username/password
			'username'				=> $this->settings['username'],
			'password'				=> $this->settings['password'],
			'metacategory'			=> $this->actions[$action]['metacategory'],
			'category'				=> $category,
			'caption'				=> $rawdata['caption'],
			'latitude'				=> $rawdata['latitude'],
			'longitude'				=> $rawdata['longitude'],
			'zoom'					=> $rawdata['zoom'],
			'basemap'				=> 'mapnik',
			'credit'				=> (isSet ($rawdata['name']) ? $rawdata['name'] . ' <' . $rawdata['email'] . '>' : $rawdata['email']),
			'license'				=> $license,
		);
		
		# If additional metadata is present (templates can choose to include/omit it optionally), assemble and add it
		$additionalMetadataFields = explode (',', $this->actions[$action]['additionalMetadata'][$category]);
		$additionalMetadata = application::arrayFields ($rawdata, $additionalMetadataFields);
		if ($additionalMetadata) {
			$data['additionalMetadata'] = json_encode ($additionalMetadata);
		}
		
		#!# Currently no support for deleting an existing image when doing an update
		
		# Add the mediaupload field if a file has been submitted
		$file = false;
		if (isSet ($rawdata['filename'])) {		// If there is an existing photo, this field will not be present
			if ($rawdata['filename']) {
				$data['mediaupload'] = $this->prepareFile ($filePath . $rawdata['filename']);
			}
		}
		
		# If editing an existing location, include the ID
		if ($existingData) {
			$data['id'] = $existingData['id'];
		}
		
		# Enable private submissions if required
		if ($this->settings['privateSubmissions']) {
			$data['private'] = '1';
		}
		
		# Post the data
		$result = application::file_post_contents ($apiUrl, $data, true, $transportError);
		
		# Delete the temporary file if a file was uploaded
		if ($file) {
			unlink ($file);
		}
		
		# Report any transport error
		if ($transportError) {
			// echo $transportError;	// Debugging
			$errorText = 'Sorry, a technical error occured - please try again later.';
			return false;
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to prepare a file for upload; see: https://stackoverflow.com/a/4270282/180733
	private function prepareFile ($file)
	{
		if (function_exists ('curl_file_create')) {
			$mediaupload = curl_file_create ($file);	// Modern method, avoids CURL deprecation warnings from PHP 5.5+
		} else {
			$mediaupload = '@' . $file;	// Deprecated method using @ symbol - see: https://stackoverflow.com/a/4270282/180733
		}
		return $mediaupload;
	}
	
	
	# Map of locations
	private function locationsMap ($showLayer, $selectedIdData = array (), $markerSetInitiallyIsDraggable = false, $viewOnlyMode = false, $initialLocation = array (), $enableDrawing = false, $markerData = array ())
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
		
		# If a selected ID was supplied, use that data
		if ($selectedIdData) {
			$mapLocation = array (
				'latitude'	=> $selectedIdData['latitude'],
				'longitude'	=> $selectedIdData['longitude'],
				'zoom'		=> $selectedIdData['zoom'],
			);
			$setMarkerInitially = true;
		}
		
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
		
		# In view-only mode, look for optional setting of location
		if ($viewOnlyMode) {
			$parameters = array ('latitude', 'longitude', 'zoom');
			$requestedLocation = array ();
			foreach ($parameters as $parameter) {
				if (isSet ($initialLocation[$parameter]) && strlen ($initialLocation[$parameter])) {
					$requestedLocation[$parameter] = $initialLocation[$parameter];
				}
			}
			if (count ($requestedLocation) == count ($parameters)) {		// Ensure complete collection
				$mapLocation = $requestedLocation;
			}
		}
		
		# Determine the URL for the browsing API; if a selected ID is requested, request that this always be included in the returned data
		#!# Improve way key is added here
		$browsingApiUrl = (($this->settings['showOthers'] || $this->userIsAdministrator || $this->action == 'audit' || $this->action == 'priorityareas') ? $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl'] . '&key=' . $this->settings['apiKey'] . ($selectedIdData ? "&selectedid={$selectedIdData['id']}" : '') : false);
		if ($this->settings['privateSubmissions']) {
			$browsingApiUrl .= '&private=1';
		}
		$browsingApiUrlJs = ($browsingApiUrl ? "'" . $browsingApiUrl . "'" : 'false');
		
		# Define a second browsing layer if required
		$browsingApiUrl2 = (isSet ($this->actions[$showLayer]['apiUrl2']) ? "'" . $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl2'] . '&key=' . $this->settings['apiKey'] . "'" : 'false');
		
		# Load Leaflet.js
		$html .= "\n\n" . '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css" />';
		$html .= "\n" . '<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"></script>';
		
		# Load leaflet-hash
		$html .= "\n\n" . '<script src="/js/lib/leaflet-hash/leaflet-hash.js"></script>';
		
		# Load Geolocation control; see: https://github.com/domoritz/leaflet-locatecontrol
		$html .= "\n\n" . '<script src="/js/lib/leaflet-locatecontrol/dist/L.Control.Locate.min.js"></script>';
		$html .= "\n" . '<link rel="stylesheet" href="/js/lib/leaflet-locatecontrol/dist/L.Control.Locate.min.css" />';
		$html .= "\n" . '<link rel="stylesheet" href="/js/lib/font-awesome/4.7.0/css/font-awesome.min.css" />';
		
		# Drawing mode
		if ($enableDrawing) {
			$html .= "\n\n" . '<script type="text/javascript" src="/js/lib/Leaflet.draw-0.4.14/dist/leaflet.draw.js"></script>';
			$html .= "\n" . '<link rel="stylesheet" href="/js/lib/Leaflet.draw-0.4.14/dist/leaflet.draw.css" rel="stylesheet" />';
		}
		
		# Create the map application HTML
		$html .= "\n" . '
		<style type="text/css">
			#helptext {margin: 0;}
			#helptext.display {background-color: yellow;}
			#helptext.hide {background-color: transparent;}
			input.ui-autocomplete-loading {background: white url(\'/images/ui-anim_basic_16x16.gif\') right center no-repeat;}
			body .ui-front {z-index: 500;}
			ul.ui-autocomplete li a {color: #ed1c24;}
			ul.ui-autocomplete li span {color: #333; font-size: smaller;}
			.leaflet-popup-content-wrapper {width: 250px; min-height: 80px;}
			.bubble p {margin: 0 0 5px;}
			.bubble p.id {text-align: right; font-size: 0.83em; margin: 0; padding: 0 0 3px;}
			.bubble p.id a {color: #bbb;}
			.bubble p.caption:before {color: #900; content: "\201C"; /* https://monc.se/kitchen/129/rendering-quotes-with-css/ */ font-family: Arial, Helvetica, sans-serif; font-size: 4.5em; font-weight: bold; line-height: 0; margin: 0 5px 0 -5px; vertical-align: bottom;}
			p.problem {text-align: right; margin: 4px 0 0; padding: 0; font-size: 0.92em;}
			p.problem a {color: #898989;}
			.leaflet-popup-content form#problem p {margin-bottom: 5px; padding-bottom: 0;}
			.leaflet-popup-content form#problem input, .leaflet-popup-content form#problem textarea {margin-top: 0; padding-top: 0;}
			p#formwarning {color: red;}
			table.metadatatable td.value, p.metadata {font-weight: bold;}
			p.metadata {margin-bottom: 2em;}
			
			/* Likes */
			#likes {float: right; margin: 0; margin-left: 4px; padding: 3px 5px; min-width: 7em; border: 1px solid #eee; background-color: #fcfcfc; border-radius: 5px;}
			#likes {transition: background-color .5s ease-in-out; transition: border-color .5s ease-in-out;}
			#likes:hover {background-color: #eee; border-color: gray;}
			#likes.liked {border: 1px solid #999;}
			#likes p {margin: 0; padding: 0; line-height: 16px;}
			#likes p img, #likes p span {vertical-align: middle;}
			#likes p span, #likes a {color: gray; text-decoration: none;}
			#likes span {padding-left: 2px;}
			#likes.liked #likestext {color: #603;}
			#likes.changed {animation: yellow-fade 2s ease-in 1;}
			@keyframes yellow-fade {
				0% {background-color: yellow;}
				100% {background-color: none;}
			}
			
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
		if (!$viewOnlyMode) {
			if (!$selectedIdData) {
				$html .= "\n" . '<p id="helptext">Zoom all the way in, using +/- or mouse scroll functions, then click on the map to set the marker.</p>';
			}
		}
		$html .= "\n" . '<div id="map"></div>';
		
		# Load EXIF Filereader support
		$html .= "\n<script type=\"text/javascript\" src=\"/js/lib/jquery.exif.js\"></script>";
		
		# Load the map application Javascript and run it
		$setMarkerInitiallyJs = ($setMarkerInitially ? 'true' : 'false');
		$markerSetInitiallyIsDraggableJs = ($markerSetInitiallyIsDraggable ? 'true' : 'false');
		$selectedIdJs = ($selectedIdData ? (ctype_digit ($selectedIdData['id']) ? $selectedIdData['id'] : "'{$selectedIdData['id']}'") : 'false');
		$viewOnlyModeJs = ($viewOnlyMode ? 'true' : 'false');
		$enableDrawingJs = ($enableDrawing ? 'true' : 'false');
		$popupLabelsJs = ($this->popupLabels ? json_encode ($this->popupLabels) : 'false');
		$popupLabelSubsetFieldJs = ($this->popupLabelSubsetField ? "'{$this->popupLabelSubsetField}'" : 'false');
		$markerDataJs = ($markerData ? json_encode ($markerData) : 'false');
		$html .= "\n<script type=\"text/javascript\" src=\"/js/telluswhere.js?20\"></script>";
		$html .= "\n<script type=\"text/javascript\">
			// NB: Obtain your own CycleStreets API key from: https://www.cyclestreets.net/api/apply/
			var map = telluswhere.createMap ({
				baseUrl: '{$this->baseUrl}',
				action: '{$this->action}',
				initialLatitude: {$mapLocation['latitude']},
				initialLongitude: {$mapLocation['longitude']},
				initialZoom: {$mapLocation['zoom']},
				browsingApiUrl: {$browsingApiUrlJs},
				useIcon: '{$showLayer}',
				setMarkerInitially: {$setMarkerInitiallyJs},
				markerSetInitiallyIsDraggable: {$markerSetInitiallyIsDraggableJs},
				selectedId: {$selectedIdJs},
				browsingApiUrl2: {$browsingApiUrl2},
				viewOnlyMode: {$viewOnlyModeJs},
				enableDrawing: {$enableDrawingJs},
				popupLabels: {$popupLabelsJs},
				popupLabelSubsetField: {$popupLabelSubsetFieldJs},
				markerData: {$markerDataJs}
			});
		</script>
		";
		
		# Add autocomplete name search
		$geocoderApiUrl = $this->settings['apiBase'] . '/v2/geocoder' . '?key=' . $this->settings['apiKey'];
		// Libraries available at: https://cdnjs.com/libraries/jqueryui/
		$html .= "\n" . '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery-ui.css" />';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery.ui.autocomplete.css" />';
		$html .= "\n" . '<script type="text/javascript" src="/js/autocomplete.js?4"></script>';
		$html .= "\n" . "<script type=\"text/javascript\">
		// Function to determine requirement for IE<=9 to use JSONP instead of JSON; see: https://stackoverflow.com/a/19562445/180733
		function useJsonpTransport () {
			
			// Determine details of the current browser
			var Browser = {
				IsIe: function () {
					return navigator.appVersion.indexOf('MSIE') != -1;
				},
				Navigator: navigator.appVersion,
					Version: function() {
					var version = 999; // we assume a sane browser
					if (navigator.appVersion.indexOf('MSIE') != -1)
					// bah, IE again, lets downgrade version number
					version = parseFloat(navigator.appVersion.split('MSIE')[1]);
					return version;
				}
			};
			
			// Test browser version
			var useJsonpTransport = (Browser.IsIe && Browser.Version() <= 9);
			
			// Return the result
			return useJsonpTransport;
		}
		</script>";
		$html .= "\n" . "<script type=\"text/javascript\">
			autocomplete.addTo (\"input[name='location']\", {
				sourceUrl: '{$geocoderApiUrl}&bounded=1&bbox=' + '{$this->settings['geocoderBboxBounded']}',
				select: function (event, ui) {
					var bbox = ui.item.feature.properties.bbox.split(',');
					map.fitBounds([ [bbox[1], bbox[0]], [bbox[3], bbox[2]] ], {maxZoom: 19});	// See: https://leafletjs.com/reference.html#latlngbounds
					event.preventDefault();
				}
			});
		</script>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Location submission form
	private function locationSubmissionForm ($action, $existingData, $schema = array (), &$html = '')
	{
		# Start the HTML
		$html = '';
		
		# Unpack user details cookie if present from a previous submission
		$data = $this->getCourtesyUserdetails ();
		
		# Map the data structure to the form data, flattening out the additional metadata if present
		if ($existingData) {
			$data = array_merge ($data, $existingData);
			if ($existingData['additionalMetadata']) {
				foreach ($existingData['additionalMetadata'] as $field => $value) {
					$data[$field] = $value;
				}
				unset ($existingData['additionalMetadata']);
			}
		}
		
		# Determine the form template
		$displayTemplate = $this->placeholderHtmlToFormTemplate ('form', $action, false, $data, $formFieldsInTemplate);
		
		# Determine whether an existing photo already exists
		$existingPhoto = ($existingData && $existingData['hasPhoto'] ? $existingData['thumbnailUrl'] : false);
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
			'errorsCssClass'			=> 'notification error',
		));
		
		# Widgets
		if (!$existingPhoto) {
			$allowedExtensions = array ('jpg');
			$form->upload (array (
				'name'				=> 'filename',
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
		
		# If a schema is supplied, generate the form from the schema
		if ($schema) {
			
			# Databind the form
			$form->dataBinding (array (
				'schema' => $schema,
				'intelligence' => true,
				'int1ToCheckbox' => true,
				'data' => $data,
			));
		} else {
			
			# Otherwise, generate manually
			if ($action == 'current') {
				$form->select (array (
					'name'			=> 'type',
					'title'			=> $this->metadataFieldLabels['type'],
					'required'		=> true,
					'values'		=> $this->parkingTypes,
					'default'		=> (isSet ($data['type']) ? $data['type'] : false),
				));
			}
			if (in_array ('capacity', $formFieldsInTemplate)) {
				$form->number (array (
					'name'			=> 'capacity',
					'title'			=> $this->metadataFieldLabels['capacity'],
					'required'		=> true,
					'default'		=> (isSet ($data['capacity']) ? $data['capacity'] : false),
				));
			}
			if (in_array ('landtype', $formFieldsInTemplate)) {
				$form->select (array (
					'name'			=> 'landtype',
					'title'			=> $this->metadataFieldLabels['landtype'],
					'required'		=> true,
					'values'		=> $this->landTypes,
					'default'		=> (isSet ($data['landtype']) ? $data['landtype'] : false),
				));
			}
			$form->textarea (array (
				'name'			=> 'caption',
				'title'			=> $this->metadataFieldLabels['caption'],
				'required'		=> false,
				'rows'			=> 2,
				'cols'			=> 20,
				'default'		=> (isSet ($data['caption']) ? $data['caption'] : false),
			));
			if (in_array ('schemes', $formFieldsInTemplate)) {
				$form->textarea (array (
					'name'			=> 'schemes',
					'title'			=> $this->metadataFieldLabels['schemes'],
					'required'		=> false,
					'rows'			=> 2,
					'cols'			=> 20,
					'default'		=> (isSet ($data['schemes']) ? $data['schemes'] : false),
				));
			}
			if (in_array ('name', $formFieldsInTemplate)) {		// Templates can choose to require only e-mail
				$form->input (array (
					'name'			=> 'name',
					'title'			=> 'Your name',
					'placeholder'	=> 'Your name',
					'required'		=> true,
					'default'		=> (isSet ($data['name']) ? $data['name'] : false),
				));
			}
		}
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'Your e-mail address',
			'placeholder'	=> 'Your e-mail address',
			'required'		=> true,
			'default'		=> (isSet ($data['email']) ? $data['email'] : false),
		));
		if (in_array ('mailinglist', $formFieldsInTemplate)) {
			$form->select (array (
				'name'			=> 'mailinglist',
				'title'			=> 'Would you like to be kept up-to-date via e-mail?',
				'required'		=> true,
				'values'		=> array ('Yes', 'No'),
				'default'		=> 'Yes',
			));
		}
		$form->checkboxes (array (
			'name'			=> 'terms',
			'required'		=> true,
			'values'		=> array ('Yes' => "I accept the <a target=\"_blank\" href=\"{$this->baseUrl}/terms/{$this->iframeSuffix}\">terms &amp; conditions</a>."),
			'entities'		=> false,
			'default'		=> 'Yes',
			'discard'		=> true,
		));
		
		# Location (hidden)
		$this->addHiddenLocationFields ($form /* modified by reference */, $html /* modified by reference */);
		
		# Process the form
		$result = $form->process ($html);
		
		# Upon a successful submission, save the name and e-mail in a cookie for a short period to save the user having to re-type these
		if ($result) {
			$name = (isSet ($result['name']) ? $result['name'] : false);
			$this->setCourtesyUserdetails ($name, $result['email']);
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to provide hidden location fields in a form
	private function addHiddenLocationFields (&$form, &$html, $initialValue = array ())
	{
		#!# ultimateForm has multiple bugs for hidden fields when using templating; for now, standard input widgets are used and then hidden using CSS
		$html .= "\n" . '<style type="text/css">
			#form_latitude, #form_longitude, #form_zoom {display: none;}
			form tr.latitude, form tr.longitude, form tr.zoom {display: none;}
		</style>
		';
		$form->input (array (
			'name'			=> 'latitude',
			'title'			=> 'Latitude (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
			'default'		=> ($initialValue ? $initialValue['latitude'] : false),
		));
		$form->input (array (
			'name'			=> 'longitude',
			'title'			=> 'Longitude (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
			'default'		=> ($initialValue ? $initialValue['longitude'] : false),
		));
		$form->input (array (
			'name'			=> 'zoom',
			'title'			=> 'Zoom level (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
			'default'		=> ($initialValue ? $initialValue['zoom'] : false),
		));
		
		# Validate
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if (!strlen ($unfinalisedData['latitude']) || !strlen ($unfinalisedData['longitude']) || !strlen ($unfinalisedData['zoom']) || !preg_match ('/^[0-9-.]+$/', $unfinalisedData['latitude']) || !preg_match ('/^[0-9-.]+$/', $unfinalisedData['longitude']) || !preg_match ('/^[0-9]{1,2}$/', $unfinalisedData['zoom'])) {
				$form->registerProblem ('location', 'The map location needs to be set.');
			}
		}
	}
	
	
	# Function to get retrieved user cookie details; there is no security involved - this is merely a courtesy to the user
	private function getCourtesyUserdetails ()
	{
		# Get the data, if any
		$data = array ();
		if (isSet ($_COOKIE['userdetails'])) {
			if (preg_match ('/^(.*) <([^>]+)>$/', $_COOKIE['userdetails'], $matches)) {
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
	
	
	# Problem reporting AJAX endpoint
	public function problem ()
	{
		# Response always returns JSON
		header ('Content-type: application/json');
		
		# End if no/incomplete posted data
		if (!isSet ($_POST) || !isSet ($_POST['id']) || !isSet ($_POST['email']) || !isSet ($_POST['message'])) {
			application::sendHeader (400);
			echo json_encode (array ('response' => 'Sorry, there was a problem submitting your comment. Please try again later.'));
			return;
		}
		
		# Assemble the data to be written
		$data = array (
			'timestamp'	=> date ('Y-m-d H:i:s'),
			'ip'		=> $_SERVER['REMOTE_ADDR'],
			'type'		=> 'Current',
			'id'		=> $_POST['id'],
			'email'		=> $_POST['email'],
			'message'	=> $_POST['message'],
		);
		
		# Append the data
		$file = $_SERVER['DOCUMENT_ROOT'] . '/db/problem.csv';
		$string = implode (',', $data) . "\n";	// #!# Should really be proper CSV writer given message may contain commas
		file_put_contents ($file, $string, FILE_APPEND);
		
		# Return success
		echo json_encode (array ('response' => 'Many thanks - your comment has been received.'));
	}
	
	
	# News page
	private function news ()
	{
		# Get the areas
		$areas = $this->getAreas ();
		
		# Load the news module
		require_once ('classes/news.class.php');
		$news = new news ($this, $areas, $this->userIsNewsEditor);
		$news->main ();
		$html = $news->getHtml ();
		
		# Register the HTML
		$this->template['contents'] = $html;
	}
	
	
	# Function to return list of areas
	private function getAreas ()
	{
		# Convert the areas to a list
		$string = explode ("\n", str_replace ("\r\n", "\n", trim ($this->settings['areas'])));
		
		# Convert to moniker => name format
		$areas = array ();
		foreach ($string as $area) {
			$moniker = application::createUrlSlug ($area);
			$areas[$moniker] = $area;
		}
		
		# Return the list
		return $areas;
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
	
	
	# Programme page
	private function programme ()
	{
		// No action - template contains everything
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
		
		# Obtain the internal form element templates
		$formTemplate = $this->placeholderHtmlToFormTemplate ('form', $this->action, true);
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> "Many thanks for your message - we'll be in touch shortly if applicable.",
			'display'					=> ($formTemplate ? 'template' : 'tables'),
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $formTemplate,
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
	
	
	# Admin area
	private function admin ()
	{
		#!# TODO
	}
	
	
	# Settings page
	private function settings ()
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
			'richtextEditorBasePath'	=> $this->baseUrl . '/js/lib/ckeditor/',
			'richtextEditorToolbarSet'	=> 'BasicLongerFormat',
			'richtextEditorAreaCSS'		=> $this->settings['cssFileLocation'],
			'richtextWidth'				=> 500,
			'richtextHeight'			=> 250,
			'richtextEditorFileBrowser'	=> false,
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
			'int1ToCheckbox' => true,
			'data' => $data,
			'attributes' => array (
				'url'				=> array ('heading' => array (3 => 'Core settings'), 'default' => $_SERVER['_SITE_URL'], 'editable' => false, ),
				'categories'	=> array ('description' => 'One category ID per line', ),
				'aboutPageHtml'		=> array ('heading' => array (3 => 'Page texts'), ),
				'administrators'	=> array ('heading' => array (3 => 'Privileged users'), 'description' => 'One e-mail address per line', ),
				'downloaders'		=> array ('description' => 'One e-mail address per line', ),
				'batchUploaders'		=> array ('type' => 'textarea', ),
				#!# Add max/min/step/pattern for defaultLatitude/defaultLongitude when ultimateForm has support; see: https://stackoverflow.com/questions/15303940/
				'defaultLatitude'	=> array ('heading' => array (3 => 'Initial map location'), ),
				'earliestDate'		=> array ('heading' => array (3 => 'Export parameters'), ),
				'bbox'				=> array ('description' => 'W,S,E,N; data from: https://wiki.openstreetmap.org/wiki/Bounding_Box', ),
				'trackingCode'		=> array ('heading' => array (3 => 'Analytics'), 'rows' => 11, ),
				'password'			=> array ('type' => 'input', 'confirmation' => false, 'editable' => true, ),	// Override intelligence=true for field named 'password'
				'areas'				=> array ('heading' => array (3 => 'Areas'), 'rows' => 12, ),
				'auditDataset'		=> array ('heading' => array (3 => 'Auditing'), ),
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
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
		$message  = "\n<p><strong>{$unicodeTick} The settings have been saved.</strong></p>";
		$message .= "\n<p><a href=\"{$this->baseUrl}/\">Continue to the front page.</a></p>";
		$html = $message . $html;
		
		# Return the HTML
		return $html;
	}
	
	
	/**
	 * Batch import page
	 *
	 */
	public function batch ()
	{
		# Define the images directory and the forced filename for this user
		$folder = 'batch-' . md5 ($this->user['email']) . '/';
		$this->imagesDirectory = $this->tmpDirectory . $folder;
		$this->imagesLocation = $this->tmpFolder . $folder;
		
		# Define the session name
		$sessionName = 'batch';
		
		# Start the HTML
		$html = '';
		
		# Clear the session if required, returning afterwards to the main batch page
		if (isSet ($_GET['do']) && $_GET['do'] == 'cancel') {
			$this->sessionDestroy ($sessionName);
			$redirectTo = $_SERVER['_SITE_URL'] . $this->baseUrl . $this->actions[__FUNCTION__]['url'];
			$html .= application::sendHeader (302, $redirectTo, true);
			$this->template['contents'] = $html;
			return;
		}
		
		# Get initial data or end
		if (!$data = $this->batchInitialDataForm ($sessionName)) {return;}
		
		# Confirm data
		if (!$data = $this->batchConfirmDataForm ($sessionName, $data)) {return;}
		
		# Add each entry via the API, reporting any error
		foreach ($data as $location) {
			$action = $this->metacategories[$location['metacategory']];
			$category = $this->categories[0];	// #!# Multiple category support not yet in place
			if (!$result = $this->postSubmission ($location, $action, $category, $location['license'], $this->imagesDirectory, false, $errorText)) {
				$html .= "\n<p class=\"warning\">Error: " . htmlspecialchars ($errorText) . '</p>';
			}
		}
		
		# Remove any existing images directory if present
		$this->rrmdir ($this->imagesDirectory);
		
		# Confirm success
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
		$html .= "\n<p>{$unicodeTick} The data has been imported. Many thanks.</p>";
		$category = $this->categories[0];	// #!# Multiple category support not yet in place
		$html .= "\n<p>You can view these on the <a href=\"{$this->baseUrl}/{$action}/\">" . lcfirst (str_replace ('%categoryLabel', $this->categoryLabels[$category]['plural'], $this->actions[$action]['description'])) . "</a> page.</p>";
		
		# Find the bounding box containing all the points
		$locationsCentrepoint = $this->locationsCentrepoint ($data);
		
		# Create the map HTML
		$html .= $this->locationsMap ($action, false, false, $viewOnlyMode = true, $locationsCentrepoint);
		
		# Register the HTML
		$this->template['contents'] = $html;
	}
	
	
	# Helper function to delete a directory and its contents
	private function rrmdir ($directory)
	{
		# Ensure a directory is specified
		if (!strlen ($directory)) {return false;}
		if (!is_dir ($directory)) {return false;}
		
		# Delete all files in the directory
		foreach (new RecursiveIteratorIterator (
			new RecursiveDirectoryIterator ($directory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
			RecursiveIteratorIterator::CHILD_FIRST
			) as $value) {
				$value->isFile () ? unlink ($value) : rmdir ($value);
		}
		
		# Remove the directory itself
		rmdir ($directory);
	}
	
	
	# Function to compute the centrepoint of a set of locations
	private function locationsCentrepoint ($data)
	{
		# Create a list of the latitudes and longitudes
		$latitudes = array ();
		$longitudes = array ();
		foreach ($data as $location) {
			$latitudes[] = $location['latitude'];
			$longitudes[] = $location['longitude'];
		}
		
		# Determine the centrepoint
		$centrepoint = array (
			'latitude'	=> ((min ($latitudes) + max ($latitudes)) / 2),
			'longitude'	=> ((min ($longitudes) + max ($longitudes)) / 2),
			'zoom'		=> 13,		// Sensible value, not particularly scientific
		);
		
		# Return the centre point
		return $centrepoint;
	}
	
	
	# Batch form stage 1
	private function batchInitialDataForm ($sessionName)
	{
		# Retrieve and return session data, if it exists
		if ($data = $this->sessionGet ($sessionName)) {
			return $data;
		}
		
		# Remove any existing images directory if present
		$this->rrmdir ($this->imagesDirectory);
		
		# Start the HTML
		$html = '';
		
		# Define the location fields
		$locationFields = array (
			'international'	=> array ('latitude', 'longitude'),
			'os'			=> array ('northings', 'eastings'),
		);
		
		# Define other, optional fields
		$optionalFields = array (
			'caption',
			'filename',
		);
		
		# Define a required fields list string; this has to be done manually because only one latitude/longitude nothings/eastings pair is required
		$requiredLocationFieldsHtml = array ();
		foreach ($locationFields as $locationFieldGroup) {
			$requiredLocationFieldsHtml[] = implode (', ', $locationFieldGroup);
		}
		$requiredLocationFieldsHtml = implode (' OR ', $requiredLocationFieldsHtml);
		
		# Define the metacategory labels
		$category = $this->categories[0];	// #!# Multiple category support not yet in place
		$metacategories = array ();
		foreach ($this->metacategories as $metacategory => $action) {
			$metacategories[$metacategory] = str_replace ('%categoryLabel', lcfirst ($this->categoryLabels[$category]['singular']), $this->actions[$action]['description']);
		}
		
		#!# Fix styles in london
		$html .= "\n<style type=\"text/css\">
			input[type=checkbox] {width: auto; margin-right: 10px;}
			label {display: inline;}
		</style>";
		
		# Instruction text
		$instructionBoxHtml  = "\n<style type=\"text/css\">
			div.graybox {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc; overflow: hidden; /* overflow prevents floats not being enclosed - see https://gtwebdev.com/workshop/floats/enclosing-floats.php */}
			div.graybox:hover {background-color: #fafafa; border-color: #aaa;}
			div.graybox p {text-align: left; margin-top: 10px;}
		</style>";
		$instructionBoxHtml .= "\n<div class=\"graybox\">";
		$instructionBoxHtml .= "\n\t<p>To add multiple locations, firstly assemble a spreadsheet containing the locations (either {$requiredLocationFieldsHtml}) in a spreadsheet.</p>";
		$instructionBoxHtml .= "\n\t<p>The spreadsheet file must have a header row, as shown in this example:</p>";
		$instructionBoxHtml .= "\n\t<p><img src=\"{$this->baseUrl}/images/multipleupload.png\" alt=\"Multiple upload example\" width=\"606\" height=\"172\" /></p>";
		$instructionBoxHtml .= "\n\t<p><strong>Required fields</strong> are: {$requiredLocationFieldsHtml}<br /><strong>Optional fields</strong> are: " . implode (', ', $optionalFields);
		$instructionBoxHtml .= "\n\t<p>Lat/lon pairs are assumed to be supplied in WGS84 (Web Mercator) projection.<br />If supplying northings/eastings pairs instead, these must be in OSGB36 projection; they will be converted to WGS84.</p>";
		$instructionBoxHtml .= "\n\t<p>If you have <strong>images</strong> of the locations, you will need to create a zip file of all the files. If these have been taken on a phone which captures the location automatically, that will be used in preference to the given latitutde/longitudes.</p>";
		$instructionBoxHtml .= "\n</div>";
		
		# Create the upload form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'name' => 'stage1',
			'div' => 'lines',
			'display' => 'paragraphs',
			'submitButtonText' => 'Upload',
			'displayRestrictions' => false,
			'requiredFieldIndicator' => false,
			'formCompleteText' => false,
			'errorsCssClass'			=> 'notification error',
		));
		$form->heading ('', $instructionBoxHtml);
		$form->select (array (
			'name'			=> 'metacategory',
			'title'			=> 'Type',
			'required'		=> true,
			'values'		=> $metacategories,
		));
		$form->select (array (
			'name'			=> 'license',
			'title'			=> 'License',
			'values'		=> array ('publicdomain' => 'Public domain (preferred)', 'ogl' => 'Open Government Licence'),
			'required'		=> true,
		));
		$form->textarea (array (
			'name' => 'metadata',
			'title' => 'Paste in the box copied from your spreadsheet - see notes above',
			'required' => true,
			'rows' => 12,
			'cols' => 60,
		));
		/*
		$form->select (array (
			'name'			=> 'projection',
			'title'			=> 'Projection (co-ordinate system) in data',
			'required'		=> true,
			'values'		=> array (
				'wgs84'			=> 'WGS84 (Web Mercator)',
				'osgb36'		=> 'OSGB36 (Ordnance Survey)',
			),
			'default'		=> 'wgs84',
		));
		*/
		$form->upload (array (
			'name' => 'images',
			'title' => '(Optional) Images - zipped as single file (maximum size: ' . ini_get ('upload_max_filesize') . ')',
			'directory' => $this->imagesDirectory,
			'required' => false,
			'allowedExtensions' => array ('zip'),
			'enableVersionControl' => false,
			'flatten' => true,
			'unzip' => true,
		));
		$form->input (array (
			'name'			=> 'name',
			'title'			=> 'Name of data owner',
			'required'		=> true,
		));
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'E-mail of data owner',
			'required'		=> true,
		));
		
		# Validate and assemble the TSV data
		$data = array ();
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['metadata']) {
				if (!$data = $this->getBatchData ($unfinalisedData['metadata'], $optionalFields, $locationFields, $requiredLocationFieldsHtml, $errorMessage)) {
					$form->registerProblem ('tsvinvalid', $errorMessage);
				}
			}
		}
		
		# Process the form
		if (!$result = $form->process ($html)) {
			$this->template['contents'] = $html;
			return false;
		}
		
		# Ensure any images specified are present
		$missingImages = array ();
		foreach ($data as $index => $location) {
			if (isSet ($location['filename']) && $location['filename']) {
				if (!is_file ($this->imagesDirectory . $location['filename'])) {
					$missingImages[] = $location['filename'];
				}
			}
		}
		if ($missingImages) {
			$html  = "\n<p>Not all images were present: <em>" . htmlspecialchars (implode (', ', $missingImages)) . '.</em></p>';
			$html .= "\n<p>Please check the zip file, then <a href=\"javascript: history.go(-1);\">go back</a> and try again.</p>";
			$this->template['contents'] = $html;
			return false;
		}
		
		# Add in a caption where not present
		$metacategory = $result['metacategory'];
		$action = $this->metacategories[$metacategory];
		$defaultCaption = str_replace ('%categoryLabel', lcfirst ($this->categoryLabels[$category]['singular']), $this->actions[$action]['description']);
		foreach ($data as $index => $location) {
			$data[$index]['caption'] = (isSet ($location['caption']) ? $location['caption'] : $defaultCaption);
			$data[$index]['metacategory'] = $metacategory;
			$data[$index]['license'] = $result['license'];
			$data[$index]['name'] = $result['name'];
			$data[$index]['email'] = $result['email'];
		}
		
		# Register the HTML
		$this->template['contents'] = $html;
		
		# Create the session entry
		$this->sessionWrite ($sessionName, $data);
		
		# Return the data
		return $data;
	}
	
	
	# Batch form stage 2
	private function batchConfirmDataForm ($sessionName, $stage1Data)
	{
		# Start the HTML
		$html = '';
		
		# Define standard map JS
		$html .= "
			<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.5.1/dist/leaflet.css\" />
			<script src=\"https://unpkg.com/leaflet@1.5.1/dist/leaflet.js\"></script>
			<script type=\"text/javascript\">
				var osmLayer = 'https://{s}.tile.osm.org/{z}/{x}/{y}.png';
				var osmAttribution = '&copy; <a href=\"https://osm.org/copyright\">OpenStreetMap</a> contributors'
			</script>
		";
		
		# Add CSS
		$html .= '
			<style type="text/css">
				/* \'Lines\' table style */
				table.lines {border-collapse: collapse; /* width: 95%; */}
				.lines td, .lines th {border-bottom: 1px solid #e9e9e9; padding: 6px 8px 2px 1px; vertical-align: top; text-align: left;}
				.lines tr:first-child {border-top: 1px solid #e9e9e9;}
				table.lines td.value p:first-child {margin-top: 0;}
				table.lines td.value p:last-child {margin-bottom: 0;}
				table.lines td:last-child ul:first-child {margin-top: 0;}
				table.lines td:last-child ul:first-child li:first-child {margin-top: 0;}
				
				p.right {float: right;}
			</style>
		';
		
		# Define default zoom
		$defaultZoom = 15;
		
		# Define the form name
		$formName = 'stage2';
		
		# Determine if the submission has images
		$hasImages = false;
		foreach ($stage1Data as $index => $location) {
			if (isSet ($location['filename']) && $location['filename']) {
				$hasImages = true;
				break;	// No point checking for more
			}
		}
		
		# Create the template
		$table = array ();
		foreach ($stage1Data as $index => $location) {
			
			# Take account of any posted map changes in the map display
			if (isSet ($_POST[$formName])) {	// If form posted
				if (isSet ($_POST[$formName]["location_{$index}"])) {
					$fields = array ('latitude', 'longitude', 'zoom');
					foreach ($fields as $field) {
						$collectionPresent = true;
						if (!isSet ($_POST[$formName]["location_{$index}"][$field]) || !preg_match ('/^[-.0-9]+$/', $_POST[$formName]["location_{$index}"][$field])) {
							$collectionPresent = false;
							break;
						}
					}
					if ($collectionPresent) {
						foreach ($fields as $field) {
							$location[$field] = $_POST[$formName]["location_{$index}"][$field];
						}
					}
				}
			}
			
			# Define the map JS
			$mapJsHtml = "
				<div id=\"map{$index}\" class=\"confirmationmap\" style=\"width: 250px; height: 120px; border: 1px solid gray;\"></div>
				<script type=\"text/javascript\">
					var map{$index} = L.map('map{$index}', {
						center: [{$location['latitude']}, {$location['longitude']}],
						zoom: {$defaultZoom},
						layers: [ L.tileLayer(osmLayer, {attribution: osmAttribution}) ],
					});
					var marker{$index} = L.marker([{$location['latitude']}, {$location['longitude']}], {draggable: true});
					marker{$index}.on('dragend', function (e) {
						var newPosition = e.target.getLatLng();
						document.getElementById('{$formName}_location_{$index}_latitude').value = newPosition.lat;
						document.getElementById('{$formName}_location_{$index}_longitude').value = newPosition.lng;
					});
					map{$index}.addLayer(marker{$index});
					map{$index}.on('zoomend', function (e) {
						document.getElementById('{$formName}_location_{$index}_zoom').value = map{$index}.getZoom();
					});
				</script>
			";
			
			# Add the table entries; hidden fields will be added to the end of the form HTML automatically
			$table[$index] = array (
				'No.'			=> ($index + 1),
				'caption'	=> "{caption_{$index}}",
				'map'		=> $mapJsHtml,
			);
			
			# Show image(s) if present
			#!# Replace with thumbnails, and add cross-user security
			if ($hasImages) {
				$location = $this->imagesLocation . htmlspecialchars (str_replace (' ', '%20', $location['filename']));
				$table[$index]['photo'] = "<img src=\"{$location}\" width=\"200\" />";
			}
		}
		$template = application::htmlTable ($table, $tableHeadingSubstitutions = array (), 'lines', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true);
		
		# Define instruction text
		$instructionBoxHtml  = "\n<style type=\"text/css\">
			div.graybox {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc; overflow: hidden; /* overflow prevents floats not being enclosed - see https://gtwebdev.com/workshop/floats/enclosing-floats.php */}
			div.graybox:hover {background-color: #fafafa; border-color: #aaa;}
			div.graybox p {text-align: left; margin-top: 10px;}
		</style>";
		$instructionBoxHtml .= "\n<div class=\"graybox\">";
		$instructionBoxHtml .= "\n<p>Please now <strong>check the locations</strong>, adjusting them on the map if necessary.</p>";
		$instructionBoxHtml .= "\n<p><strong>Then press the submit button</strong> at the end.</p>";
		$instructionBoxHtml .= "\n</div>";
		
		# Provide a link to cancel
		$instructionBoxHtml .= "\n<p class=\"right\">Or <a href=\"{$this->baseUrl}/batch/cancel.html\" onclick=\"return confirm('Are you sure?');\">Clear and go back &hellip;</a></p>";
		
		# Start a confirmation form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'name' => $formName,
			'div' => 'lines',
			'display' => 'template',
			'displayTemplate' => $instructionBoxHtml . '{[[PROBLEMS]]}' . $template . '{[[SUBMIT]]}',
			'errorsCssClass'			=> 'notification error',
			'submitButtonText' => 'Confirm',
		));
		foreach ($stage1Data as $index => $location) {
			$form->hidden (array (
				'name'		=> "location_{$index}",
				'values'	=> array (
					'latitude'	=> $location['latitude'],
					'longitude'	=> $location['longitude'],
					'zoom'		=> $defaultZoom,
				),
				'security'	=> false,	// Allow hidden data to be modified - here by moving the map location
			));
			$form->textarea (array (
				'name'			=> "caption_{$index}",
				'title'			=> 'Caption',
				'required'		=> true,
				'rows'			=> 2,
				'cols'			=> 40,
				'default'		=> $location['caption'],
			));
		}
		if (!$result = $form->process ($html)) {
			$this->template['contents'] = $html;
			return false;	// End, but retain the session data
		}
		
		# Assemble the posted second-stage data
		$totalLocations = count ($stage1Data);
		$data = array ();
		for ($i = 0; $i < $totalLocations; $i++) {
			$data[$i] = array (
				'caption'		=> $result["caption_{$i}"],
				'latitude'		=> $result["location_{$i}"]['latitude'],
				'longitude'		=> $result["location_{$i}"]['longitude'],
				'zoom'			=> $result["location_{$i}"]['zoom'],
				'metacategory'	=> $stage1Data[$i]['metacategory'],
				'license'		=> $stage1Data[$i]['license'],
				'name'			=> $stage1Data[$i]['name'],
				'email'			=> $stage1Data[$i]['email'],
			);
			if (isSet ($stage1Data[$i]['filename'])) {
				$data[$i]['filename'] = $stage1Data[$i]['filename'];
			}
		}
		
		# Register the HTML
		$this->template['contents'] = $html;
		
		# Destroy the session data from the first stage
		$this->sessionDestroy ($sessionName);
		
		# Return the finalised data
		return $data;
	}
	
	
	# Function to process submitted TSV batch string and assemble the data from it
	private function getBatchData ($tsv, $optionalFields, $locationFields, $requiredLocationFieldsHtml, &$errorMessage = '')
	{
		# Parse the TSV string
		require_once ('csv.php');
		$data = csv::tsvToArray (trim ($tsv), $firstColumnIsId = false, $firstColumnIsIdIncludeInData = true);
		
		# Extract the fields in the data by taking the first row of data
		$fields = array_keys ($data[0]);
		
		# Ensure one of the location sets is present
		$locationGroupPresent = array ();
		foreach ($locationFields as $type => $locationFieldGroup) {
			if (!array_diff ($locationFieldGroup, $fields)) {
				$locationGroupPresent[] = $type;
			}
		}
		if (!$locationGroupPresent) {
			$errorMessage = "The data does not appear to contain a valid pair of location columns - {$requiredLocationFieldsHtml}.";
			return false;
		}
		if (count ($locationGroupPresent) > 1) {
			$errorMessage = "The data appears to contain more than one pair of location columns - {$requiredLocationFieldsHtml}.";
			return false;
		}
		$typeChosen = $locationGroupPresent[0];
		
		# Ensure headers are valid and that required headers are present
		$invalidFields = array_diff ($fields, array_merge ($optionalFields, $locationFields[$typeChosen]));
		if ($invalidFields) {
			$errorMessage = "The fields in the pasted data do not match the specification noted below. Please correct the spreadsheet and try again.";
			return false;
		}
		
		# Convert co-ordinates to lat/lon if required
		if ($typeChosen != 'international') {
			$conversionFunction = 'convertCoordinates' . ucfirst ($typeChosen);		// e.g. convertCoordinatesOs
			$data = $this->{$conversionFunction} ($data);
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to convert OS co-ordinates to lat/lon; this assumes Eastings & Northings are always OSGB36 and need to be converted to WGS84
	private function convertCoordinatesOs ($data)
	{
		# Load required library
		//require_once ('libraries/osLonLat.class.class.php');	// Disabled, as outputs OSGB36 values
		require_once ('libraries/conversionslatlong.class.php');
		$conversionslatlong = new conversionslatlong ();
		
		# Convert each set, and remove the original values
		foreach ($data as $index => $location) {
			//list ($data[$index]['latitude'], $data[$index]['longitude']) = osLonLat::EastingNorthingToLatLong ($location['eastings'], $location['northings']);
			list ($data[$index]['latitude'], $data[$index]['longitude']) = $conversionslatlong->osgb36_to_wgs84 ($location['eastings'], $location['northings']);
			unset ($data[$index]['northings']);
			unset ($data[$index]['eastings']);
		}
		
		# Return the modified dataset
		return $data;
	}
	
	
	# Data page
	private function data ()
	{
		# Start the HTML
		$html = '';
		
		# Define the embeddable map types
		$types = array (
			'suggest' => 'Suggested %categoryLabel &mdash; CSV export',
			'current' => 'Current %categoryLabel &mdash; CSV export',
		);
		
		# Create listing
		$tableHtml  = "\n<table class=\"buttons\">";
		foreach ($this->categories as $category) {
			$tableHtml .= "\n\t<tr>";
			foreach ($types as $type => $label) {
				$label = str_replace ('%categoryLabel', $this->categoryLabels[$category]['plural'], $label);
				$tableHtml .= "\n\t\t<td style=\"padding: 20px;\">" . "<a class=\"button color huge circle\" href=\"/data/{$type}.csv\">{$label}</a></td>";
			}
			$tableHtml .= "\n\t</tr>";
		}
		$tableHtml .= "\n</table>";
		$this->template['links'] = $tableHtml;
		
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
		
		# Get the category
		#!# Does not yet support multiple categories
		$category = $this->categories[0];
		
		# Define the parameters for the API call
		$parameters = array (
			'category'		=> $category,
			'metacategory'	=> $this->actions[$dataset]['metacategory'],
			'bbox'			=> $this->settings['bbox'],
			'since'			=> ($this->settings['earliestDate'] ? strtotime ($this->settings['earliestDate'] . ' 00:00:00') : 0),
			'thumbnailsize'	=> '640',
			'limit'			=> '0',
			'format'		=> 'csv',
			'fields'		=> "id,latitude,longitude,areaName,caption,additionalMetadata[{$this->actions[$dataset]['additionalMetadata'][$category]}],datetime,hasPhoto,url,license" . ($dataset == 'suggest' ? ',likes' : ''),
			'datetime'		=> 'sqldatetime',
			'domain'		=> "https://{$_SERVER['SERVER_NAME']}",
		);
		
		# Assemble the API call URL
		$apiUrl = $this->settings['apiBase'] . '/v2/photomap.locations' . '?key=' . $this->settings['apiKey'] . '&' . http_build_query ($parameters);
		
		# Enable private submissions if required
		if ($this->settings['privateSubmissions']) {
			$apiUrl .= '&private=1';
		}
		
		# Obtain the data
		$csv = file_get_contents ($apiUrl);
		
		# Replace cycle.st links with internal links
		#!# Bit of a dirty way to do this - should have an API parameter, e.g. shortlink=https://{$_SERVER['SERVER_NAME']}/location/%s/
		$csv = preg_replace ('|,https://www.cyclestreets.net/location/([0-9]+)/,|', ",https://{$_SERVER['SERVER_NAME']}/location/\$1/,", $csv);
		
		# Serve the file
		$filenameBase = $dataset . '_savedAt' . date ('Ymd-His');
		header ('Content-type: text/csv');	// Note that Chrome will still give "Resource interpreted as Document but transferred with MIME type text/csv" - see: https://stackoverflow.com/a/3899453/180733
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
	private function loginForm (&$html, $autofocus = true)
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'submitTo'			=> $this->baseUrl . '/login/',
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
			'autofocus'	=> $autofocus,
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
			'identifier'	=> $email,
			'password'	=> $password,
		);
		
		# Post to the user authentication API
		$apiUrl = $this->settings['apiBase'] . $this->actions[$this->action]['apiUrl'] . '?key=' . $this->settings['apiKey'];
		$resultJson = application::file_post_contents ($apiUrl, $postData, $error);
		if ($error) {
			// echo $error;		// Debug
			$error = 'Sorry, a technical error occured trying to validate the details you gave. Please try again later.';
			return false;
		}
		
		# Unpack the response
		$result = json_decode ($resultJson, true);
		
		# Detect unparsable JSON (e.g. the API is not properly installed)
		if ($result === NULL && json_last_error () !== JSON_ERROR_NONE) {
			$error = 'Sorry, a technical error occured trying to validate the details you gave. Please try again later.';
			return false;
		}
		
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
		# Begin the session
		$this->sessionInit ();
		
		# Set the top-right login area
		// At present, the login box is not shown
		$this->template['login-status'] = '';
		
		# Get the user login status
		$user = $this->sessionGet ('user');
		
		# Set CSS classes where the template supports this
		if ($user) {
			$this->template['css'] = '
			<style type="text/css">
				nav li.login {display: none;}
				nav li.register {display: none;}
			</style>
			';
		} else {
			$this->template['css'] = '
			<style type="text/css">
				nav li.profile {display: none;}
			</style>
			';
		}
		
		# Return false if no user
		if (!$user) {return false;}
		
		# Determine privileges
		$this->userIsAdministrator = $this->userIs ('administrators', $user['email'], NULL);
		$this->userIsDownloader = $this->userIs ('downloaders', $user['email'], $this->userIsAdministrator);
		$this->userIsBatchUploader = $this->userIs ('batchUploaders', $user['email'], $this->userIsAdministrator);
		$this->userIsNewsEditor = $this->userIs ('newsEditors', $user['email'], $this->userIsAdministrator);
		
		# Write the login status in the top-right
		$loginStatusHtml  = "\n<p style=\"text-align: right\"><span style=\"color: #ccc;\">Logged in as: </span>" . htmlspecialchars ($user['email']);
		if ($this->userIsAdministrator) {
			$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/settings/\">Settings</a>";
		}
		if ($this->userIsDownloader) {
			$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/data/\">Data</a>";
		}
		if ($this->userIsBatchUploader) {
			$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/batch/\">Batch</a>";
		}
		if ($this->userIsNewsEditor) {
			$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/news/\">News</a>";
		}
		$loginStatusHtml .= " | <a title=\"Link to embed page (public)\" href=\"{$this->baseUrl}/embed/\">Embed</a>";
		$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/logout/\">Logout</a></p>";
		$this->template['login-status'] = $loginStatusHtml;
		
		# Return the user details
		return $user;
	}
	
	
	# Function to parse a list of e-mails to check for privilege
	private function userIs ($field, $email, $userIsAdministrator)
	{
		# If the user is an administrator, grant right
		if ($userIsAdministrator) {return true;}
		
		# Determine if the user is an administrator
		$emails = ($this->settings[$field] ? preg_split ("/\s+/", trim ($this->settings[$field])) : array ());
		return (in_array ($email, $emails));
	}
	
	
	# Function to log the user in
	private function doLogin ($result)
	{
		# Regenerate the session ID
		session_regenerate_id ($deleteOldSession = true);
		
		# Create the session entry
		$this->sessionWrite ('user', $result);
		
		# Refresh the page to ensure the session cookie is written
		application::sendHeader ('refresh');
	}
	
	
	# Function to log the user out
	private function logout ()
	{
		# Start the HTML
		$html = '';
		
		# Cache whether the user presented session data
		$userHadSessionData = ($this->sessionGet ('user'));
		
		# Explicitly destroy the session
		$this->sessionDestroy ('user');
		
		# Confirm logout if there was a session, and redirect the user to the login page if necessary
		$loginLocation = $this->baseUrl . $this->actions['login']['url'];
		if ($userHadSessionData) {
			$html .= "\n<p>You have been successfully logged out.</p>";
			$html .= "\n<p>You can <a href=\"" . htmlspecialchars ($loginLocation) . '">log in again</a> if you wish.</p>';
		} else {
			header ('Location: https://' . $_SERVER['SERVER_NAME'] . $this->baseUrl . $loginLocation);
			$html .= "\n<p>You are not logged in.</p>";
			$html .= "\n<p><a href=\"" . htmlspecialchars ($loginLocation) . '">Please click here to continue.</a></p>';
		}
		
		# Clear the login status indicator
		$this->template['login-status'] = '';
		
		# Register the HTML
		$this->template['text'] = $html;
	}
	
	
	# Function to start session handling if not already running
	private function sessionInit ()
	{
		# Lock down PHP session management
		ini_set ('session.name', 'session');
		ini_set ('session.use_only_cookies', 1);
		
		# Start the session handling
		if (!session_id ()) {session_start ();}
	}
	
	
	# Function to get the current session data
	private function sessionGet ($field)
	{
		# End session if basic fingerprint match fails
		if (!isSet ($_SESSION['_fingerprint'])) {return false;}
		if ($_SESSION['_fingerprint'] != md5 ($_SERVER['HTTP_USER_AGENT'])) {
			$this->sessionDestroy ($field);
			return false;
		}
		
		# Return the field's data if present
		return (isSet ($_SESSION[$field]) ? $_SESSION[$field] : false);
	}
	
	
	# Function to write into the session
	private function sessionWrite ($field, $data)
	{
		# Add/update fingerprint
		$_SESSION['_fingerprint'] = md5 ($_SERVER['HTTP_USER_AGENT']);
		
		# Write the value
		$_SESSION[$field] = $data;
	}
	
	
	# Function to destroy a session
	private function sessionDestroy ($field)
	{
		# Remove the field
		unset ($_SESSION[$field]);
		
		# If the session is now empty, destroy it entirely
		if (!$_SESSION) {
			
			# Regenerate the session ID
			session_regenerate_id ($deleteOldSession = true);
			
			# Destroy the session cookie
			session_unset ();
			session_destroy ();
			$params = session_get_cookie_params ();
			setcookie (session_name (), '', time () - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		}
	}
	
	
	# Registration page
	private function register ()
	{
		#!# TODO
	}
	
	
	# Profile page
	private function profile ()
	{
		#!# TODO
	}
	
	
	# Admin review submissions page
	private function adminreview ()
	{
		#!# TODO
	}
	
	
	# Admin search locations page
	private function adminsearch ()
	{
		#!# TODO
	}
	
	
	# Admin manage users page
	private function adminusers ()
	{
		#!# TODO
	}
	
	
	# Admin progress by borough
	private function adminboroughs ()
	{
		#!# TODO
	}
}

?>
