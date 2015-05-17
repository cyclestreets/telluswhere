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
				'descriptionMultiple' => 'Suggested cycle parking locations',
				'url' => '/suggest/',
				'apiUrl' => '/v2/photomap.locations?category=cycleparking&metacategory=bad&limit=150&thumbnailsize=200&fields=id,caption,hasPhoto,thumbnailUrl,additionalMetadata',
				'metacategory' => 'bad',
				'additionalMetadata' => 'landtype,capacity',
			),
			'current' => array (
				'description' => 'Current cycle parking location',
				'descriptionMultiple' => 'Current cycle parking locations',
				'url' => '/current/',
				'apiUrl' => '/v2/photomap.locations?category=cycleparking&metacategory=other&limit=150&thumbnailsize=200&fields=id,caption,hasPhoto,thumbnailUrl,additionalMetadata',
				// 'apiUrl2' => '/v2/pois.locations?type=cycleparking&limit=40&fields=id,latitude,longitude,name,nodeId,osmTags',
				'metacategory' => 'other',
				'additionalMetadata' => 'landtype,type,capacity',
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
				'rightRequired' => 'downloader',
			),
			'download' => array (
				'description' => false,
				'url' => false,
				'rightRequired' => 'downloader',
			),
			'admin' => array (
				'description' => false,
				'url' => '/admin/',
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
			$html = "\n<p class=\"warning\">The website could not be set up due to a configuration error. Please check back shortly.</p>";
			echo $html;
			return false;
		}
		
		# Determine the tmp directory in use for file uploads and ensure it is writeable
		if (!$this->tmpDirectory = $this->getWritableDirectory ($this->tmpFolder)) {
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
		
		# Load the template
		$this->templateHtml = $this->getTemplateHtml ($this->action);
		
		# Determine the supported metacategories and the action they are mapped to
		$this->metacategories = array ();
		foreach ($this->actions as $action => $attributes) {
			if (isSet ($attributes['metacategory'])) {
				$this->metacategories[$attributes['metacategory']] = $action;
			}
		}
		
		# Determine the supported categories
		#!# Generalise to setting
		$this->categories = array ('cycleparking');
		
		# Perform the action, which will write into the page template array
		$this->{$this->action} ();
		
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
		
		# Ensure the database file itself is writable
		if (!is_writable ($databaseFile)) {return false;}
		
		# Set a flag to indicate first-run mode
		$this->isFirstRun = false;
		
		# Obtain the settings
		if (!$databaseSettings = $this->databaseConnection->selectOne ('main', 'settings', array ('url' => $_SERVER['_SITE_URL']))) {
			$this->isFirstRun = true;
			$databaseSettings = array ('administrators' => false, 'downloaders' => false);	// $databaseSettings = false would crash array_merge below
		}
		
		# Add in the database settings, with the database settings taking priority
		$settings = array_merge ($settings, $databaseSettings);
		
		# Return the settings
		return $settings;
	}
	
	
	# Function to bootstrap the database structure; note the SQLite format comments: http://stackoverflow.com/questions/7426205/
	private function createDatabaseStructure ($databaseFile)
	{
		# Settings table
		$query = "
			CREATE TABLE IF NOT EXISTS main.settings (
			  `id` INTEGER PRIMARY KEY,						-- Site number
			  `url` VARCHAR(255) NOT NULL,					-- URL of site (match)
			  `applicationName` VARCHAR(255) NOT NULL,		-- Site name
			  `style` VARCHAR(255) NOT NULL,				-- Style
			  `apiKey` VARCHAR(255) NOT NULL,				-- API key
			  `username` VARCHAR(255) NOT NULL,				-- Username for submissions
			  `password` VARCHAR(255) NOT NULL,				-- Password for submissions
			  `feedbackRecipient` VARCHAR(255) NOT NULL,	-- Contact page form recipient
			  `aboutPageHtml` TEXT NOT NULL,				-- About page text
			  `contactsPageHtml` TEXT NOT NULL,				-- Contact page text
			  `termsPageHtml` TEXT NOT NULL,				-- Terms page text
			  `administrators` TEXT NOT NULL,				-- E-mail logins of administrators
			  `downloaders` TEXT NOT NULL,					-- E-mail logins for access to downloads
			  `batchUploaders` TEXT NULL,					-- E-mail logins for access to batch upload section
			  `newsEditors` TEXT NULL,					-- E-mail logins for access to news editors
			  `defaultLatitude` FLOAT NOT NULL,				-- Default latitude
			  `defaultLongitude` FLOAT NOT NULL,			-- Default longitude
			  `defaultZoom` FLOAT NOT NULL,					-- Default zoom
			  `earliestDate` DATE,							-- Earliest date to appear in export
			  `bbox` VARCHAR(225) NOT NULL,					-- Bounding box for export
			  `trackingCode` TEXT NULL,						-- Analytics tracking code
			  `areas` TEXT									-- Area names
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
	public function page404 ()
	{
		# Send the header
		application::sendHeader (404);
		
		# Show generic text if custom 404 not available
		$page = '/404.html';
		
		# Get the template
		$templateHtml = templating::convertDesignerHtmlToTemplate ($page, $this->styleDirectory, $this->replacedPlaceholders, $this->getStyleDirectory ('default'));
		
		# Get the HTML
		$html = templating::doTemplateSubstitution ($templateHtml, $this->template);
		
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
		$html = templating::convertDesignerHtmlToTemplate ($templateLocation, $this->styleDirectory, $this->replacedPlaceholders, $this->getStyleDirectory ('default'));
		
		# Return the template HTML
		return $html;
	}
	
	
	# Function to take an extracted part of the template and convert to ultimateForm form template format
	private function placeholderHtmlToFormTemplate ($placeholderName, $action, $selectedIdData = false)
	{
		# Obtain the form template which was extracted during the template pre-processing
		$htmlBlock = $this->replacedPlaceholders[$placeholderName];
		
		# Extract the HTML between placeholder-comments nested within the form template to leave a standard template for the form
		$template = templating::commentsToPlaceholders ($htmlBlock, $replacedPlaceholders);
		
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
	
	
	# Embeddable map iframe pages
	private function embed ()
	{
		# Start the HTML
		$html = '';
		
		# Define the embeddable map types
		$types = array (
			'suggest' => 'Suggested cycle parking',
			'current' => 'Current cycle parking',
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
			$list = array ();
			foreach ($types as $type => $label) {
				$list[] = "<a class=\"button color huge circle\" href=\"/{$type}/embed/\">{$label}</a>";
			}
			$this->template['links'] = "\n<p>" . implode ('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $list) . '</p>';
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
			$html .= "\n<p>The map widget will show <strong>" . lcfirst ($types[$type]) . "</strong> [<a href=\"{$this->baseUrl}/embed/\">change?</a>].</p>";
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
		$this->template['about'] = "Powered by <a href=\"http://{$_SERVER['SERVER_NAME']}/{$type}/\" target=\"_top\">{$this->settings['applicationName']}</a> - contribute your knowledge";
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
		$apiUrl = $this->settings['apiBase'] . '/v2/photomap.location' . '?key=' . $this->settings['apiKey'] . '&id=' . $id . '&format=flat' . '&fields=id,metacategoryId,categoryId,caption,latitude,longitude,zoom,basemap,credit,additionalMetadata,hasPhoto,thumbnailUrl' . '&thumbnailsize=400';
		
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
		
		# Assign the virtual action (e.g. if the data's metacategory is 'bad', then the action is 'current')
		$action = $this->metacategories[$data['metacategoryId']];
		
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
			$editlink = "\n<p id=\"editlink\"><a href=\"{$this->baseUrl}/location/{$id}/edit/\"><img src=\"{$this->baseUrl}/images/pencil.png\" alt=\"\" width=\"16\" height=\"16\" border=\"0\" /> Edit</a></p>";
		}
		
		# Register HTML components
		$this->template['id'] = $this->actions[$action]['description'] . ' &mdash; #' . $id;
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
	private function submissionPage ($action, $existingData = array ())
	{
		# Start the HTML
		$html = '';
		
		# Create the form and process the data
		if (!$data = $this->locationSubmissionForm ($action, $existingData, $html)) {		// &html written into by reference
			return $html;
		}
		
		# Send the data (including any image) to the API
		if (!$result = $this->postSubmission ($data, $action, $this->tmpDirectory, $existingData, $errorText)) {
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
		if ($data['mailinglist'] == 'Yes') {
			$file = $_SERVER['DOCUMENT_ROOT'] . '/db/mailinglist.csv';
			$string = $data['email'] . ',' . $data['name'] . ',' . $result['id'] . "\n";
			file_put_contents ($file, $string, FILE_APPEND);
		}
		
		# Determine the redirection target, namely the location page
		$redirectToPath = $this->baseUrl . "/location/{$result['id']}/";
		
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
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// http://www.fileformat.info/info/unicode/char/2714/
		$html  = "\n<p>{$unicodeTick}" . ($isUpdate ? '<strong> Thank you for your update</strong>.' : "<strong> Thank you for your submission</strong>, which is number {$id}.") . '</p>';
		$html .= "\n<p><a href=\"{$this->actions[$action]['url']}\">Add another?</a></p>";
		return $html;
	}
	
	
	# Function to post submissions to the API
	private function postSubmission ($rawdata, $action, $filePath, $existingData, &$errorText = '')
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
			'license'				=> 'publicdomain',
			'additionalMetadata'	=> json_encode ($additionalMetadata),
		);
		
		#!# Currently no support for deleting an existing image when doing an update
		
		# Add the mediaupload field if a file has been submitted
		$file = false;
		if (isSet ($rawdata['filename'])) {		// If there is an existing photo, this field will not be present
			if ($rawdata['filename']) {
				$file = $filePath . $rawdata['filename'];
				if (function_exists ('curl_file_create')) {
					$mediaupload = curl_file_create ($file);	// Modern method, avoids CURL deprecation warnings from PHP 5.5+
				} else {
					$mediaupload = '@' . $file;	// Deprecated method using @ symbol - see: http://stackoverflow.com/a/4270282/180733
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
	
	
	# Map of locations
	private function locationsMap ($showLayer, $selectedIdData = false, $markerSetInitiallyIsDraggable = false, $viewOnlyMode = false, $initialLocation = array (), $disableGeolocation = false)
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
		$browsingApiUrl = $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl'] . '&key=' . $this->settings['apiKey'] . ($selectedIdData ? "&selectedid={$selectedIdData['id']}" : '');
		
		# Define a second browsing layer if required
		$browsingApiUrl2 = (isSet ($this->actions[$showLayer]['apiUrl2']) ? "'" . $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl2'] . '&key=' . $this->settings['apiKey'] . "'" : 'false');
		
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
			p.problem {text-align: right; margin: 4px 0 0; padding: 0; font-size: 0.92em;}
			p.problem a {color: #898989;}
			.leaflet-popup-content form#problem p {margin-bottom: 5px; padding-bottom: 0;}
			.leaflet-popup-content form#problem input, .leaflet-popup-content form#problem textarea {margin-top: 0; padding-top: 0;}
			p#formwarning {color: red;}
			table.metadatatable td.value, p.metadata {font-weight: bold;}
			p.metadata {margin-bottom: 2em;}
			
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
		$html .= "\n<script type=\"text/javascript\" src=\"/js/jquery.exif.js\"></script>";
		
		# Load the map application Javascript and run it
		$setMarkerInitiallyJs = ($setMarkerInitially ? 'true' : 'false');
		$markerSetInitiallyIsDraggableJs = ($markerSetInitiallyIsDraggable ? 'true' : 'false');
		$selectedIdJs = ($selectedIdData ? $selectedIdData['id'] : 'false');
		$viewOnlyModeJs = ($viewOnlyMode ? 'true' : 'false');
		$disableGeolocationJs = ($disableGeolocation ? 'true' : 'false');
		$html .= "\n<script type=\"text/javascript\" src=\"/js/telluswhere.js?9\"></script>";
		$html .= "\n<script type=\"text/javascript\">
			var map = telluswhere.createMap('{$this->baseUrl}', {$mapLocation['latitude']}, {$mapLocation['longitude']}, {$mapLocation['zoom']}, '{$browsingApiUrl}', '{$showLayer}', {$setMarkerInitiallyJs}, {$markerSetInitiallyIsDraggableJs}, {$selectedIdJs}, {$browsingApiUrl2}, {$viewOnlyModeJs}, {$disableGeolocationJs});
		</script>
		";
		
		# Add autocomplete name search
		$geocoderApiUrl = $this->settings['apiBase'] . '/v2/geocoder' . '?key=' . $this->settings['apiKey'];
		// Libraries available at: http://cdnjs.com/libraries/jqueryui/
		$html .= "\n" . '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery-ui.css" />';
		$html .= "\n" . '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/css/base/jquery.ui.autocomplete.css" />';
		$html .= "\n" . '<script type="text/javascript" src="/js/autocomplete.js"></script>';
		$html .= "\n" . "<script type=\"text/javascript\">
		// Function to determine requirement for IE<=9 to use JSONP instead of JSON; see: http://stackoverflow.com/a/19562445/180733
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
				sourceUrl: '{$geocoderApiUrl}&bounded=1&viewbox=-6.6577,57.6924,1.7797,49.9370',
				select: function (event, ui) {
					var result = ui.item;
					map.setView (L.latLng (result.lat, result.lon));
					event.preventDefault();
				}
			});
		</script>";
		
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
			$data = array_merge ($data, $existingData);
			if ($existingData['additionalMetadata']) {
				foreach ($existingData['additionalMetadata'] as $field => $value) {
					$data[$field] = $value;
				}
				unset ($existingData['additionalMetadata']);
			}
		}
		
		# Determine the form template
		$displayTemplate = $this->placeholderHtmlToFormTemplate ('form', $action, $data);
		
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
		if ($action == 'current') {
			$form->select (array (
				'name'			=> 'type',
				'title'			=> $this->metadataFieldLabels['type'],
				'required'		=> true,
				'values'		=> $this->parkingTypes,
				'default'		=> (isSet ($data['type']) ? $data['type'] : false),
			));
		}
		$form->number (array (
			'name'			=> 'capacity',
			'title'			=> $this->metadataFieldLabels['capacity'],
			'required'		=> true,
			'default'		=> (isSet ($data['capacity']) ? $data['capacity'] : false),
		));
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
			'richtextEditorBasePath'	=> $this->baseUrl . '/js/ckeditor/',
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
			'data' => $data,
			'attributes' => array (
				'url'				=> array ('heading' => array (3 => 'Core settings'), 'default' => $_SERVER['_SITE_URL'], 'editable' => false, ),
				'aboutPageHtml'		=> array ('heading' => array (3 => 'Page texts'), ),
				'style'				=> array ('type' => 'select', 'values' => $this->getStyles (), ),
				'administrators'	=> array ('heading' => array (3 => 'Privileged users'), 'description' => 'One e-mail address per line', ),
				'downloaders'		=> array ('description' => 'One e-mail address per line', ),
				'batchUploaders'		=> array ('type' => 'textarea', ),
				#!# Add max/min/step/pattern for defaultLatitude/defaultLongitude when ultimateForm has support; see: http://stackoverflow.com/questions/15303940/
				'defaultLatitude'	=> array ('heading' => array (3 => 'Initial map location'), ),
				'earliestDate'		=> array ('heading' => array (3 => 'Export parameters'), ),
				'bbox'				=> array ('description' => 'W,S,E,N; data from: http://wiki.openstreetmap.org/wiki/Bounding_Box', ),
				'trackingCode'		=> array ('heading' => array (3 => 'Analytics'), 'rows' => 11, ),
				'password'			=> array ('type' => 'input', 'confirmation' => false, 'editable' => true, ),	// Override intelligence=true for field named 'password'
				'areas'				=> array ('heading' => array (3 => 'Areas'), 'rows' => 12, ),
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
		
		# Get initial data or end
		if (!$data = $this->batchInitialDataForm ()) {return;}
		
		# Confirm data
		if (!$data = $this->batchConfirmDataForm ($data)) {return;}
		
		# Start the HTML
		$html = '';
		
		# Add each entry via the API, reporting any error
		foreach ($data as $location) {
			$action = $this->metacategories[$location['metacategory']];
			if (!$result = $this->postSubmission ($location, $action, $this->imagesDirectory, false, $errorText)) {
				$html .= "\n<p class=\"warning\">Error: " . htmlspecialchars ($errorText) . '</p>';
			}
		}
		
		# Remove any existing images directory if present
		$this->rrmdir ($this->imagesDirectory);
		
		# Confirm success
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// http://www.fileformat.info/info/unicode/char/2714/
		$html .= "\n<p>{$unicodeTick} The data has been imported. Many thanks.</p>";
		$html .= "\n<p>You can view these on the <a href=\"{$this->baseUrl}/{$action}/\">" . lcfirst ($this->actions[$action]['descriptionMultiple']) . "</a> page.</p>";
		
		# Find the bounding box containing all the points
		$locationsCentrepoint = $this->locationsCentrepoint ($data);
		
		# Create the map HTML
		$html .= $this->locationsMap ($action, false, false, $viewOnlyMode = true, $locationsCentrepoint, $disableGeolocation = true);
		
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
	private function batchInitialDataForm ()
	{
		# Retrieve and return session data, if it exists
		$sessionName = 'batch';
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
		$metacategories = array ();
		foreach ($this->metacategories as $metacategory => $action) {
			$metacategories[$metacategory] = $this->actions[$action]['descriptionMultiple'];
		}
		
		#!# Fix styles in london
		$html .= "\n<style type=\"text/css\">
			input[type=checkbox] {width: auto; margin-right: 10px;}
			label {display: inline;}
		</style>";
		
		# Instruction text
		$instructionBoxHtml  = "\n<style type=\"text/css\">
			div.graybox {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc; overflow: hidden; /* overflow prevents floats not being enclosed - see http://gtwebdev.com/workshop/floats/enclosing-floats.php */}
			div.graybox:hover {background-color: #fafafa; border-color: #aaa;}
			div.graybox p {text-align: left; margin-top: 10px;}
		</style>";
		$instructionBoxHtml .= "\n<div class=\"graybox\">";
		$instructionBoxHtml .= "\n\t<p>To add multiple locations, firstly assemble a spreadsheet containing the locations in a spreadsheet.</p>";
		$instructionBoxHtml .= "\n\t<p>The spreadsheet file must have a header row, as shown in this example:</p>";
		$instructionBoxHtml .= "\n\t<p><img src=\"{$this->baseUrl}/images/multipleupload.png\" alt=\"Multiple upload example\" width=\"606\" height=\"172\" /></p>";
		$instructionBoxHtml .= "\n\t<p><strong>Required fields</strong> are: " . $requiredLocationFieldsHtml . "<br /><strong>Optional fields</strong> are: " . implode (', ', $optionalFields);
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
		$form->checkboxes (array (
			'name'				=> 'confirmation',
			'title'				=> 'Data entered must be public domain',
			'values'			=> array ("Yes, I confirm the data is licensed as public domain"),
			'required'			=> true,	// Ensures that a submission must be ticked for the form to be processed
		));
		$form->select (array (
			'name'			=> 'metacategory',
			'title'			=> 'Type',
			'required'		=> true,
			'values'		=> $metacategories,
		));
		$form->textarea (array (
			'name' => 'metadata',
			'title' => 'Paste in the box copied from your spreadsheet - see notes above',
			'required' => true,
			'rows' => 12,
			'cols' => 60,
		));
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
		$defaultCaption = $this->actions[$action]['description'];
		foreach ($data as $index => $location) {
			$data[$index]['caption'] = (isSet ($location['caption']) ? $location['caption'] : $defaultCaption);
			$data[$index]['metacategory'] = $metacategory;
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
	private function batchConfirmDataForm ($stage1Data)
	{
		# Start the HTML
		$html = '';
		
		# Define standard map JS
		$html .= "
			<link rel=\"stylesheet\" href=\"http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.css\" />
			<script src=\"http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.js\"></script>
			<script type=\"text/javascript\">
				var osmLayer = 'http://{s}.tile.osm.org/{z}/{x}/{y}.png';
				var osmAttribution = '&copy; <a href=\"http://osm.org/copyright\">OpenStreetMap</a> contributors'
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
			if ($_POST[$formName]) {	// If form posted
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
			div.graybox {border: 1px solid #ddd; padding: 10px 15px; margin: 0 10px 10px 0; background-color: #fcfcfc; overflow: hidden; /* overflow prevents floats not being enclosed - see http://gtwebdev.com/workshop/floats/enclosing-floats.php */}
			div.graybox:hover {background-color: #fafafa; border-color: #aaa;}
			div.graybox p {text-align: left; margin-top: 10px;}
		</style>";
		$instructionBoxHtml .= "\n<div class=\"graybox\">";
		$instructionBoxHtml .= "\n<p>Please now <strong>check the locations</strong>, adjusting them on the map if necessary.</p>";
		$instructionBoxHtml .= "\n<p><strong>Then press the submit button</strong> at the end.</p>";
		$instructionBoxHtml .= "\n</div>";
		
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
		$this->sessionDestroy ('batch');
		
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
	
	
	# Function to convert OS co-ordinates to lat/lon
	private function convertCoordinatesOs ($data)
	{
		# Load required library
		require_once ('libraries/osLonLat.class.class.php');
		
		# Convert each set, and remove the original values
		foreach ($data as $index => $location) {
			list ($data[$index]['latitude'], $data[$index]['longitude']) = osLonLat::EastingNorthingToLatLong ($location['eastings'], $location['northings']);
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
			'since'		=> ($this->settings['earliestDate'] ? strtotime ($this->settings['earliestDate'] . ' 00:00:00') : 0),
			'thumbnailsize'	=> '640',
			'limit'			=> '0',
			'format'		=> 'csv',
			'fields'		=> "id,latitude,longitude,areaName,caption,additionalMetadata[{$this->actions[$dataset]['additionalMetadata']}],datetime,hasPhoto,shortlink,license",
			'datetime'		=> 'sqldatetime',
		);
		
		# Assemble the API call URL
		$apiUrl = $this->settings['apiBase'] . '/v2/photomap.locations' . '?key=' . $this->settings['apiKey'] . '&' . http_build_query ($parameters);
		
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
			'identifier'	=> $email,
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
		
		# Return false if no user
		$user = $this->sessionGet ('user');
		if (!$user) {return false;}
		
		# Determine privileges
		$this->userIsAdministrator = $this->userIs ('administrators', $user['email'], NULL);
		$this->userIsDownloader = $this->userIs ('downloaders', $user['email'], $this->userIsAdministrator);
		$this->userIsBatchUploader = $this->userIs ('batchUploaders', $user['email'], $this->userIsAdministrator);
		$this->userIsNewsEditor = $this->userIs ('newsEditors', $user['email'], $this->userIsAdministrator);
		
		# Write the login status in the top-right
		$loginStatusHtml  = "\n<p style=\"text-align: right\"><span style=\"color: #ccc;\">Logged in as: </span>" . htmlspecialchars ($user['email']);
		if ($this->userIsAdministrator) {
			$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/admin/\">Admin</a>";
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
			header ('Location: http://' . $_SERVER['SERVER_NAME'] . $this->baseUrl . $loginLocation);
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
}

?>
