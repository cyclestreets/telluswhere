<?php

# Class to implement a website asking visitors to say where public infrastructure changes are needed and to report on existing infrastructure
class telluswhere
{
	# Settings
	private function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'hostname'				=> 'localhost',
			'database'				=> 'telluswhere',
			'username'				=> 'telluswhere',
			'password'				=> NULL,
			'style'					=> 'default',
			'apiBase'				=> 'https://api.cyclestreets.net',
			// NB: Obtain your own CycleStreets API key from: https://www.cyclestreets.net/api/apply/
			'apiKey'				=> false,
			'submissionsUsername'	=> false,
			'submissionsPassword'	=> false,
			'cssFileLocation'		=> NULL,
			'administratorEmail'	=> (isSet ($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : NULL),
			'flashMessageName'		=> 'confirmation',
			'editabilityPeriod'		=> 7 * 24 * 60 * 60,		// In seconds
			'trackingCode'			=> false,
			'dataset'				=> false,	// For audit
			#!# Needs to be added to database settings
			'geocoderBboxBounded'	=> '-6.6577,49.9370,1.7797,57.6924',	// English mainland
			'authNamespace'			=> 'telluswhere\\',
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
				'apiUrl' => '/v2/photomap.locations?category=%category&metacategory=bad&limit=150&thumbnailsize=200&fields=id,caption,likes,hasPhoto,thumbnailUrl,metacategoryId,iconUrl,additionalMetadata',
				'metacategory' => 'bad',
				'additionalMetadata' => array (
					'cycleparking' => 'landtype,capacity',
					'bikeshare' => 'schemes',
				),
			),
			'current' => array (
				'description' => 'Current %categoryLabel',
				'url' => '/current/',
				'apiUrl' => '/v2/photomap.locations?category=%category&metacategory=other&limit=150&thumbnailsize=200&fields=id,caption,likes,hasPhoto,thumbnailUrl,iconUrl,additionalMetadata',
				// 'apiUrl2' => '/v2/pois.locations?type=cycleparking&limit=40&fields=id,latitude,longitude,name,nodeId,osmTags',
				'metacategory' => 'other',
				'additionalMetadata' => array (
					'cycleparking' => 'landtype,type,capacity',
					'bikeshare' => 'schemes',
				),
			),
			'areas' => array (		// Basically a wrapper to current
				'description' => 'Homepages for every area',
				'url' => '/areas/',
			),
			'city' => array (		// Basically a wrapper to current
				'description' => 'Homepage for city',
				'url' => '/suggest/',	// Template location; URL will be /%id
				'template' => false,
			),
			'audit' => array (
				'description' => 'Audit %categoryLabel',
				'url' => '/audit/',
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&limit=400&simplify=1&latest=1',
				'apiUrl2' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
			),
			'auditadd' => array (
				'description' => 'Audit %categoryLabel',
				'url' => '/audit/add/',
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&limit=400&simplify=1&latest=1',
				'apiUrl2' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
			),
			'auditaddlocation' => array (
				'description' => 'Audit %categoryLabel',
				'url' => '/audit/add/location/',	// Template location; URL will be /audit/add/%category/
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&type=%type&limit=400&simplify=1&latest=1',
				'authentication' => true,
			),
			'auditlocation' => array (
				'description' => 'Audit location',
				'url' => '/audit/location/',	// Will be /audit/location/%id/
				'apiUrl' => '/v2/infrastructure.location&dataset=%dataset&id=%id&latest=1',
				'authentication' => true,
			),
			'priorityareas' => array (
				'description' => 'Priority areas',
				'url' => '/audit/priorityareas/',
				'apiUrl' => '/v2/infrastructure.priorityareas.locations&dataset=%dataset',
				'authentication' => true,
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
			'privacy' => array (
				'description' => false,
				'url' => '/privacy/',
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
			'password' => array (
				'description' => false,
				'url' => '/password/',
			),
			'profile' => array (
				'description' => false,
				'url' => '/profile/',
				'authentication' => true,
			),
			'adminlogin' => array (
				'description' => false,
				'url' => '/admin/login/',
				'apiUrl' => '/v2/user.authenticate',
			),
			'adminreview' => array (
				'description' => 'Review submissions',
				'url' => '/admin/review/',
				'administrator' => true,
				'apiUrl' => '/v2/infrastructure.locations?dataset=%dataset&review=1',
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
			'adminpriorityareas' => array (
				'description' => 'Progress by priority area',
				'url' => '/admin/priorityareas/',
				'authentication' => true,
			),
			'ajax' => array (
				'description' => false,
				'url' => '/ajax',
				'export' => true,
				'authentication' => true,
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
	private $headContent = array ();
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
			'title'				=> false,
		),
		'bikeshare'		=> array (
			'plural'			=> 'Bikeshare locations',
			'singular'			=> 'Bikeshare location',
			'title'				=> false,
		),
		'obstructions'		=> array (
			'plural'			=> 'Obstructions',
			'singular'			=> 'Obstruction',
			'title'				=> false,
		),
		'cycleways'		=> array (
			'plural'			=> 'Cycleways',
			'singular'			=> 'Cycleway',
			'title'				=> 'Space on roads and junctions, separate from cars/pedestrians, makes cycling safe and pleasant.',
		),
		'dutchcycleways'	=> array (
			'plural'			=> 'Dutch-style cycleways',
			'singular'			=> 'Dutch-style cycleway',
			'title'				=> false,
		),
		'track'		=> array (
			'plural'			=> 'Pavements',
			'singular'			=> 'Pavement',
			'title'				=> 'Wider footpaths and pavements.',
		),
		'closure'		=> array (
			'plural'			=> 'Closures to through-traffic',
			'singular'			=> 'Closure',
			'title'				=> 'A filter prevents through-traffic, enabling walking and cycling.',
		),
	);
	
	# Labels for metadata fields
	private $metadataFieldLabels = array (
		'type'		=> 'Type of parking',
		'capacity'	=> 'How many cycles can be parked?',
		'landtype'	=> 'Land type',
		'caption'	=> 'Comments',
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
	
	# Internal city IDs, taken from CycleStreets map_city table
	private $cityIds = array (
		37	=> 'Barking and Dagenham',
		43	=> 'Barnet',
		35	=> 'Bexley',
		24	=> 'Brent',
		32	=> 'Bromley',
		4	=> 'Camden',
		1677	=> 'City of London',
		15	=> 'City of Westminster',
		31	=> 'Croydon',
		25	=> 'Ealing',
		42	=> 'Enfield',
		34	=> 'Greenwich',
		22	=> 'Hackney',
		17	=> 'Hammersmith and Fulham',
		41	=> 'Haringey',
		44	=> 'Harrow',
		36	=> 'Havering',
		45	=> 'Hillingdon',
		26	=> 'Hounslow',
		23	=> 'Islington',
		16	=> 'Kensington and Chelsea',
		11	=> 'Kingston upon Thames',
		19	=> 'Lambeth',
		33	=> 'Lewisham',
		29	=> 'Merton',
		39	=> 'Newham',
		38	=> 'Redbridge',
		1692	=> 'Richmond upon Thames',
		20	=> 'Southwark',
		30	=> 'Sutton',
		21	=> 'Tower Hamlets',
		40	=> 'Waltham Forest',
		18	=> 'Wandsworth',
	);
	
	# Gamification points
	private $gamificationPoints = array (
		'TELLUSWHERE_REGISTER'	=> 10,
		'AUDIT_ADD'				=> 5,	// Added new infrastructure
		'AUDIT_UPDATE'			=> 1,	// Updated existing asset
		'AUDIT_CONFIRM'			=> 1,	// Unchanged/gone
		'AUDIT_PASSED_CHECK'	=> 2,
		'AUDIT_SECOND_REVIEW'	=> 2,
		'PROBLEM_REPORT'		=> 1,
		'TELLUSWHERE_REFERAL'	=> 10,
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
		
		# Add support for hiding a properties header
		$this->template['_noproperties'] = ((isSet ($_GET['properties']) && $_GET['properties'] == 'false') ? ' noproperties' : '');
		
		# Register standard placeholder substitutions
		$this->template['date'] = date ('Y');
		
		# Set asset revision
		$this->template['revision'] = date ('ymd');		// Force asset update each day
		
		# Create the map application CSS
		$this->headContent['telluswhere-css'] = '<link rel="stylesheet" href="/css/telluswhere.css?' . $this->template['revision'] . '" />';
		
		# If a file is requested, serve the file directly, then end
		if (isSet ($_GET['file'])) {
			if ($this->serveFile ($_GET['file'])) {
				return;		// End all processing as the content has now been delivered
			}
		}
		
		# Get the user's details, if authenticated
		$this->user = $this->getUser ();
		
		# Get the points of the user
		$this->gamificationActivities = $this->getGamificationActivities ();
		$this->template['points'] = ($this->gamificationActivities ? $this->gamificationActivities['total'] : NULL);
		
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
		$this->template['_action'] = $this->action;
		
		# Set the login link for the template
		$this->template['loginLink'] = $this->baseUrl . '/login/';
		if ($_SERVER['REQUEST_URI'] != '/login/' && !substr_count ($_SERVER['REQUEST_URI'], '/login/?')) {
			$this->template['loginLink'] .= '?' . $_SERVER['REQUEST_URI'];
		}
$this->template['loginLink'] = ltrim ($this->template['loginLink'], '/');
		
		# Require authentication if specified
		if (isSet ($this->actions[$this->action]['authentication']) || isSet ($this->actions[$this->action]['administrator']) || isSet ($this->actions[$this->action]['rightRequired'])) {
			if (!$this->user) {
				if (isSet ($this->actions[$this->action]['export'])) {
					header ('Content-type:application/json');
					echo $this->jsonError ('Please firstly log in to perform this action.');
					exit;
				}
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
		
		# If the user is an administrator, set the Admin links
		$this->template['adminMenuLink'] = '';
		$this->template['adminLink'] = '';
		if ($this->userIsAdministrator) {
			$this->template['adminMenuLink'] = '<li><a href="/admin/">Admin</a></li>';
			$this->template['adminLink'] = '<a href="/admin/">Admin area</a>';
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
		
		# Enable feedback handler
		$this->feedbackHandler ();
		
		# Render the page
		$html = templating::doTemplateSubstitution ($this->templateHtml, $this->template, $this->styleDirectory);
		
		# Always load jQuery
		$this->headContent['jquery'] = '<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>';
		
		# Inject assets into the head, ensuring jQuery is at the start, and the application at the end
		if (isSet ($this->headContent['jquery'])) {
			$this->headContent = application::array_move_to_start ($this->headContent, 'jquery');
		}
		if (isSet ($this->headContent['application'])) {
			$applicationJs = $this->headContent['application'];
			unset ($this->headContent['application']);
			$this->headContent['application'] = $applicationJs;
		}
		$html = str_replace ('</head>', str_replace ("\n", "\n\t", "\n\n" . implode ("\n\n", $this->headContent)) . "\n\n</head>", $html);
		
		# Add stats tracking code if required
		$html = $this->analyticsTrackingCode ($html);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to add additional settings from the database, ensuring the database is set up
	private function getSettings ($settings)
	{
		# Connect to the database
		require_once ('database.php');
		$this->databaseConnection = new database ($settings['hostname'], $settings['username'], $settings['password'], $settings['database']);
		
		# Create the database structure if it does not already exist
		if (!$tables = $this->databaseConnection->getTables ($settings['database'])) {
			$this->createDatabaseStructure ();
		}
		
		# Set a flag to indicate first-run mode
		$this->isFirstRun = false;
		
		# Obtain the settings
		if (!$databaseSettings = $this->databaseConnection->selectOne ($settings['database'], 'settings', array ('url' => $_SERVER['_SITE_URL']))) {
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
	
	
	# Function to bootstrap the database structure
	private function createDatabaseStructure ()
	{
		# Settings table
		$query = "
			CREATE TABLE `settings` (
			  `id` int(11) NOT NULL PRIMARY KEY COMMENT 'Site number',
			  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL of site (match)',
			  `applicationName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Site name',
			  `apiKey` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'API key',
			  `tileUrl` VARCHAR(255) NOT NULL COMMENT 'Tileserver URL',
			  `tileOpacity` DECIMAL(3,1) NOT NULL COMMENT 'Tile opacity',
			  `submissionsUsername` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username for submissions',
			  `submissionsPassword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Password for submissions',
			  `feedbackRecipient` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contact page form recipient',
			  `categories` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Categories',
			  `multiCategoryMode` INT(1) NULL COMMENT 'Multi-category mode? (Has multiple categories rather than up-front selection list)',
			  `limitToTag` VARCHAR(255) NULL COMMENT 'Limit to tag',
			  `submitTag` VARCHAR(255) NULL COMMENT 'Submit tag',
			  `since` DATE NULL DEFAULT NULL COMMENT 'Limit to locations since time',
			  `showOthers` int(1) DEFAULT NULL COMMENT 'Show submissions by others?',
			  `privateSubmissions` int(1) DEFAULT NULL COMMENT 'Make submissions private?',
			  `overlayUrl` VARCHAR(255) DEFAULT NULL COMMENT 'Overlay URL',
			  `overlayButtonHtml` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Overlay button HTML (will be added inside a paragraph)',
			  `aboutPageHtml` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'About page text',
			  `contactsPageHtml` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contact page text',
			  `termsPageHtml` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Terms page text',
			  `administrators` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'E-mail logins of administrators',
			  `downloaders` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'E-mail logins for access to downloads',
			  `batchUploaders` text COLLATE utf8mb4_unicode_ci COMMENT 'E-mail logins for access to batch upload section',
			  `newsEditors` text COLLATE utf8mb4_unicode_ci COMMENT 'E-mail logins for access to news editors',
			  `defaultLatitude` float NOT NULL COMMENT 'Default latitude',
			  `defaultLongitude` float NOT NULL COMMENT 'Default longitude',
			  `defaultZoom` float NOT NULL COMMENT 'Default zoom',
			  `earliestDate` date DEFAULT NULL COMMENT 'Earliest date to appear in export',
			  `bbox` varchar(225) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Bounding box for export',
			  `shareTwitterText` VARCHAR(255) NULL COMMENT 'Sharing text for Twitter',
			  `shareWhatsappText` VARCHAR(255) NULL COMMENT 'Sharing text for Whatsapp',
			  `trackingCode` text COLLATE utf8mb4_unicode_ci COMMENT 'Analytics tracking code',
			  `areas` text COLLATE utf8mb4_unicode_ci COMMENT 'Area names',
			  `auditDataset` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Audit dataset moniker'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		";
		$this->databaseConnection->query ($query);
		
		# News table
		$query = "
			CREATE TABLE `news` (
			  `id` int(11) NOT NULL PRIMARY KEY,
			  `area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `urlMoniker` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `articleRichtext` text COLLATE utf8mb4_unicode_ci NOT NULL,
			  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `date` date DEFAULT NULL,
			  UNIQUE KEY `urlMoniker` (`urlMoniker`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
	
	
	# Function to send a JSON error
	private function jsonError ($error)
	{
		header ('HTTP/1.1 400 Bad Request');
		return json_encode (array ('error' => $error));
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
		$template = templating::commentsToPlaceholders ($htmlBlock, $replacedPlaceholders /* returned by reference */);
		
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
				$replacements[$placeholder] = $this->mapPanel ($action, $mapLocation, true);
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
			$this->template['form']  = "<p>You are logged in.</p>";
			$this->template['form'] .= "\n<p>You have <a href=\"{$this->baseUrl}/profile/\">{$this->gamificationActivities['total']} points</a>.</p>";
		} else {
			$formHtml = '';
			if ($result = $this->loginForm ($formHtml, false)) {
				$this->doLogin ($result);	// $result now contains the user details (username, email, name, privileges)
			}
			$this->template['form'] = $formHtml;
		}
		
		# Add areas drop-down if supported
		$this->template['areas'] = $this->areasDropdown ();
		
		# Initialise the Javascript application
		$this->initJsGeneral ('home');
		
		# Add geocoder
		$html .= $this->geocoder ($withGeolocation = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to initialise the Javascript application, as required for e.g. feedback
	private function initJsGeneral ($run = false)
	{
		# Load the application, to enable the geocoder
		$userJs = ($this->user ? 'true' : 'false');
		$this->headContent['application']  = "<script src=\"/js/telluswhere.js?{$this->template['revision']}\"></script>";
		$this->headContent['application'] .= "\n" . "<script>
		$(function() {
			var config = {
				baseUrl: '{$this->baseUrl}',
				apiKey: '{$this->settings['apiKey']}',
				apiBaseUrl: '{$this->settings['apiBase']}',
				geocoderBboxBounded: '{$this->settings['geocoderBboxBounded']}'
			};
			
			telluswhere.initialise (config, '{$run}', '{$this->action}', {$userJs});
		});
		</script>
		";
	}
	
	
	# Function to create an areas drop-down
	private function areasDropdown ($asList = false)
	{
		# Determine the file or end if not supported
		$file = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . '/areas.csv';
		if (!file_exists ($file)) {return;}
		
		# Convert to CSV
		require_once ('libraries/csv.php');
		$areas = csv::getData ($file);
		
		# Construct the HTML
		if ($asList) {
			$list = array ();
			foreach ($areas as $area) {
				$list[] = "<a href=\"#16/{$area['longitude']}/{$area['latitude']}\">" . htmlspecialchars ($area['name']) . '</a>';
			}
			$html = application::htmlUl ($list, 0, 'areaslist');
		} else {
			$html  = "\n<select id=\"regionswitcher\">";
			$html .= "\n<option value=\"\">Go to borough:</option>";
			foreach ($areas as $area) {
				$html .= "\n\t<option value=\"{$this->baseUrl}/audit/#16/{$area['longitude']}/{$area['latitude']}\">" . htmlspecialchars ($area['name']) . '</option>';
			}
			$html .= "\n</select>";
			$this->headContent['telluswhere-regionswitcher'] = "<script>
			$(function() {
				$('#regionswitcher').change (function () {
					if (this.value) {
						window.location = $(this).val();
					}
				});
			});
			</script>
			";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Areas listing page
	private function areas ()
	{
		# Register assets, needed for the place search
		$this->headContent['jquery-ui']  = '<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>';
		$this->headContent['jquery-ui'] .= "\n" . '<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" />';
		
		# Initialise the Javascript application
		$this->initJsGeneral (__FUNCTION__);
		
		# Get the areas list from the API
		$apiUrl = '/v2/localareas.list';
		$apiUrl = $this->settings['apiBase'] . $apiUrl . '&key=' . $this->settings['apiKey'];
		$data = file_get_contents ($apiUrl);
		$data = json_decode ($data, true);
		
		# Convert from GeoJSON to a flat array
		$areas = array ();
		foreach ($data['features'] as $area) {
			$areas[] = array (
				'country'	=> $area['properties']['country'],
				'name'		=> $area['properties']['name'],
				'url'		=> $_SERVER['_SITE_URL'] . '/' . $area['properties']['id'] . '/',
				'link'		=> $_SERVER['SERVER_NAME'] . '/' . $area['properties']['id'],
			);
		}
		
		# Regroup by region
		$areasByRegion = application::regroup ($areas, 'country');
		
		# Filter to wanted regions only
		$filterToRegions = array (
			'England: London Boroughs',
			'England',
			'Wales',
			'Scotland',
			'Northern Ireland',
		);
		$areasByRegion = application::arrayFields ($areasByRegion, $filterToRegions);
		
		# Create a listing
		$html = '';
		$regionsList = array ('Jump to:');
		foreach ($areasByRegion as $region => $areas) {
			
			# Register the jump link
			$region = str_replace ('England: ', '', $region);
			$regionId = strtolower (str_replace (array (' ', ':'), '', $region));
			$regionsList[] = '<a href="#' . htmlspecialchars ($regionId) . '">' . htmlspecialchars ($region) . '</a>';
			
			# Add the heading
			$html .= "\n\n<h3 id=\"{$regionId}\">" . htmlspecialchars ($region) . '</h3>';
			
			# Add each region
			$list = array ();
			foreach ($areas as $area) {
				$list[] = "<li><a href=\"{$area['url']}\">" . htmlspecialchars ($area['name']) . '</a></li>';
			}
			$html .= "\n" . application::splitListItems ($list, $columns = 3);
		}
		
		# Add the regions jumplist
		$html = application::htmlUl ($regionsList, 0, 'inline') . $html;
		
		# Register the list
		$this->template['list'] = $html;
	}
	
	
	# City page, e.g. /cambridge, basically a wrapper to suggest
	private function city ()
	{
		# Ensure there is a city moniker
		if (!isSet ($_GET['id'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$id = $_GET['id'];
		
		# Request the data from the API
		$apiUrl = '/v2/localareas.show&id=' . $id;
		$apiUrl = $this->settings['apiBase'] . $apiUrl . '&key=' . $this->settings['apiKey'];
		$data = file_get_contents ($apiUrl);
		$data = json_decode ($data, true);
		
		# End if no match
		if (isSet ($data['error'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Assign the start point
		$feature = $data['features'][0];
		$this->settings['defaultLatitude'] = $feature['geometry']['coordinates'][1];
		$this->settings['defaultLongitude'] = $feature['geometry']['coordinates'][0];
		$this->settings['defaultZoom'] = ($feature['properties']['radius'] > 5 ? 12 : 14);
		
		# Register the city name for the template
		$city = $feature['properties']['name'];
		
		# Run suggest
		$this->action = 'suggest';
		$this->template['_action'] = $this->action;		// Re-register
		$this->suggest (array (), $enableInitialCookieLocation = false, $city);
	}
	
	
	# Suggest a location page
	private function suggest ($existingData = array (), $enableInitialCookieLocation = true, $city = false)
	{
		# Start the HTML
		$html = '';
		
		# Register the city name to the template, if present
		$this->template['city'] = ($city ? $city : '');
		
		# If there are multiple categories, force selection, unless in multi-category mode
		if ($this->settings['multiCategoryMode']) {
			$category = implode (',', $this->categories);
		} else {
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
		}
		
		# Finalise the API URL
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%category', $category, $this->actions[__FUNCTION__]['apiUrl']);
		
		# Show the submission page
		$html = $this->submissionPage (__FUNCTION__, $category, $existingData, array (), $enableInitialCookieLocation);
		
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
		$this->template['areasList'] = $this->areasDropdown ($asList = true);
		
		# Add jump to ID form
		$this->template['idForm'] = $this->auditIdForm ();
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
		$html .= $this->mapPanel ($this->action, false, false, $viewOnlyMode = true);
		
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
		$popupLabels['_type'] = 'Type';
		$popupLabels['surveyDate'] = 'Survey date';
		
		# Hide internal fields
		$popupLabels['_status'] = NULL;
		$popupLabels['iconUrl'] = NULL;
		$popupLabels['road_name'] = NULL;
		$popupLabels['osm_id'] = NULL;
		
		# Set the labels
		$this->popupLabels = $popupLabels;
		#!# Not yet working
		$this->popupLabelSubsetField = false;
	}
	
	
	# Function to add Jump to audit ID form
	private function auditIdForm ()
	{
		# Only appears for administrators
		if (!$this->userIsAdministrator) {return false;}
		
		# Start the HTML
		$html = '<h3><img src="/images/icons/shield.png" class="icon" /> Jump to asset ID</h3>';
		
		# Create the form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'name' => 'idform',
			'requiredFieldIndicator' => false,
			'formCompleteText' => false,
		));
		$form->input (array (
			'name'		=> 'id',
			'title'		=> 'Jump to asset ID',
			'required'	=> true,
		));
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['id']) {
				
				# Obtain the data
				$apiUrl = '/v2/infrastructure.location&dataset=%dataset&id=%id&latest=1';
				$apiUrl = str_replace ('%dataset', $this->settings['auditDataset'], $apiUrl);
				$apiUrl = str_replace ('%id', $unfinalisedData['id'], $apiUrl);
				$apiUrl = $this->settings['apiBase'] . $apiUrl . '&key=' . $this->settings['apiKey'];
				
				# Obtain the data
				$data = file_get_contents ($apiUrl);
				$data = json_decode ($data, true);
				
				# If error, throw error
				if (isSet ($data['error'])) {
					$form->registerProblem ('id', 'No such ID was found.');
				}
			}
		}
		
		# Redirect on success
		if ($result = $form->process ($html)) {
			$redirectTo = $_SERVER['_SITE_URL'] . $this->baseUrl . '/audit/location/' . $result['id'] . '/';
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Page for adding a location, as stage 1 to select the type; stage 2 is auditaddlocation
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
	
	
	# Function to get the gamification data for the user
	private function getGamificationActivities ()
	{
		# End if not signed in
		if (!$this->user) {return array ();}
		
		# Obtain the data for this user from the API
		$apiUrl = $this->settings['apiBase'] . '/v2/gamification.activities&key=' . $this->settings['apiKey'] . '&email=' . $this->user['email'];
		$data = file_get_contents ($apiUrl);
		$data = json_decode ($data, true);
		
		# End if error
		if (isSet ($data['error'])) {
			return array ();
		}
		
		# Return the activity data
		return $data;
	}
	
	
	# Function to get the gamification data for the user
	private function addGamificationPoints ($activity, $data = false)
	{
		# Assemble the data
		$data = array (
			'email'		=> $this->user['email'],
			'points'	=> $this->gamificationPoints[$activity],
			'activity'	=> $activity,
			'data'		=> $data,
		);
		
		# Obtain the data for this user from the API
		$apiUrl = $this->settings['apiBase'] . '/v2/gamification.addactivity&key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($apiUrl, $data);
		$result = json_decode ($result, true);
		
		# End if error
		if (isSet ($result['error'])) {
			return array ();
		}
		
		# Update the points count in the template with the new value
		$this->template['points'] = $result['total'];
		
		# Return the points value, for use when running in an AJAX context
		return $result['total'];
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
		$this->actions[$this->action]['apiUrl'] = str_replace ('%type', $category, $this->actions[$this->action]['apiUrl']);
		
		# Assign the popup labels
		$this->auditSetPopupLabels ($schema[$category], $flatten = false);
		
		# Create the audit form (with map)
		if ($result = $this->auditFormPresent ($schema[$category]['fields'], $category, $schema[$category]['geometrytype'], array ())) {
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
		$this->headContent['tabs-js'] = '<script src="https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js"></script>';
		$this->headContent['tabs-js'] = "
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
		
		# Disable the creation of a dynamic map icon layer, as the icon will be explicitly supplied in auditFormPresent
		$this->actions[__FUNCTION__]['apiUrl'] = false;
		
		# Create the audit location present form (with map)
		if ($result = $this->auditFormPresent ($schema['fields'], $category, $schema['geometrytype'], $data)) {
			$this->template['presentForm'] = $this->auditPresentCommit ($result, $id, false);
		}
		
		# Create the unchanged form
		if ($result = $this->auditStatusChangeForm ('unchanged', 'unchangedForm')) {
			$this->auditStatusCommit ('infrastructure.unchanged', $id, $result['surveyDate']);
			$this->template['unchangedForm'] = $this->auditConfirmation ('marked as unchanged', false);
		}
		
		# Create the gone form
		if ($result = $this->auditStatusChangeForm ('no longer present', 'deleteForm')) {
			$this->auditStatusCommit ('infrastructure.delete', $id, $result['surveyDate']);
			$this->template['deleteForm'] = $this->auditConfirmation ('marked as no longer present', false);
		}
		
		# Create the problem form (e.g. needing maintenance)
		if ($result = $this->auditStatusChangeForm ('having a problem', 'problemForm')) {
			$this->template['problemForm'] = $this->auditConfirmation ('marked as having a problem', false);
		}
	}
	
	
	# Function to create the audit form for infrastructure present, which includes the map
	private function auditFormPresent ($fields /* for the current category */, $category, $geometryType, $data = array () /* or GeoJSON feature */)
	{
		# Extract the properties for dataBinding and the map popup
		$locationData = ($data ? $data['properties'] : array ());
		
		# Determine whether to enable drawing
		$enableDrawing = ($geometryType == 'LineString' ? 'LineString' : false);
		
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
			
			# Handle combined fields as required or not required; the schema emits combineOptionalField which states whether the field is optional
			if (isSet ($field['required'])) {
				$attributes[$fieldname]['required'] = $field['required'];
			}
		}
		
		# Assemble selected ID data
		$selectedIdData = array ();
		if ($data) {
			$centre = $this->getCentre ($data['geometry']);
			$selectedIdData = array (
				'id' => $data['properties']['id'],
				'latitude' => $centre['lat'],
				'longitude' => $centre['lon'],
				'geometry' => $data['geometry'],
				'zoom' => 16,
			);
		}
		
		# Assemble data-icon images for form
		foreach ($schemaDatabinding as $fieldname => $field) {
			$folder = '/images/dropdowns/' . $fieldname . '/';
			$directory = $_SERVER['DOCUMENT_ROOT'] . $this->styleDirectory . $folder;
			if (is_dir ($directory)) {
				$attributes[$fieldname]['data'] = array ();
				foreach ($field['_values'] as $value) {
					$file = str_replace ('/', '_', strtolower ($value)) . '.jpg';
					if (is_file ($directory . $file)) {
						$attributes[$fieldname]['data'][$value]['icon'] = $folder . $file;
					}
				}
			}
		}
		
		# Create the map HTML
		$mapHtml = $this->mapPanel ($this->action, $selectedIdData, $markerSetInitiallyIsDraggable = true, false, $selectedIdData, $enableDrawing, $locationDataOriginal);
		$this->template['map'] = $mapHtml;
		
		# Disable intelligence for colour fields, forcing ENUM
		foreach ($schemaDatabinding as $fieldname => $field) {
			if (substr_count ($fieldname, 'colour')) {
				$attributes[$fieldname]['type'] = 'select';
			}
		}
		
		# Create a new form
		$formHtml = '';
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'requiredFieldIndicator'	=> false,
			'submitButtonText'		=> 'Save changes &nbsp; &gt;',
			'submitButtonAccesskey'		=> false,
			'unsavedDataProtection'		=> true,
			'nullText'			=> false,
			'div'				=> 'auditform',
			'labelsSurround'		=> true,
			'uploadThumbnailWidth'		=> 160,
			'uploadThumbnailHeight'		=> 120,
			'jQuery'					=> false,	// Already loaded
			'jQueryUi'					=> false,	// Already loaded
		));
		if ($data) {
			$form->heading ('p', 'Please check the map location to ensure it is correct. If not, you can ' . ($enableDrawing ? 'redraw the location' : 'drag the marker') . ' to give an accurate location.');
		} else {
			if ($enableDrawing) {
				$form->heading ('p', 'Firstly, draw on the map to set the location.');
			} else {
				$form->heading ('p', 'Firstly, click on the map to set the location. You can then drag the marker to get an accurate location.');
			}
		}
		
		# Add drawing instructions
		if ($enableDrawing) {
			$form->heading ('', '
				<div id="formdrawing">
					<p class="edit-instructions">Click on the map to draw the line.</p>
					<p class="edit-clear"><strong class="success">✓ Line set.</strong><br /><a href="#">Re-draw</a> if you made a mistake.</p>
				</div>
			');
		}
		
		# Main form
		$form->dataBinding (array (
			'schema' => $schemaDatabinding,
			'intelligence' => true,
			'int1ToCheckbox' => true,
			'data' => $locationData,
			'attributes' => $attributes,
		));
		
		# Add photo upload
		#!# Need client-size resize before upload: https://stackoverflow.com/questions/49759386/resize-image-in-the-client-side-before-upload
		$tempDir = sys_get_temp_dir () . '/';
		$photos = 2;
		$form->upload (array (
			'name' => 'photos',
			'title' => 'Two photos',
			'required' => true,
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
		
		# Remove legacy lat/lon/zoom fields, which are unused
		unset ($result['latitude'], $result['longitude'], $result['zoom']);
		
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
					'required'	=> (!$field['combineOptionalField']),
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
		$fields[] = 'location';
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
			'email'		=> $this->user['email'],
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
		#!# Failure detection needed
		$result = application::file_post_contents ($schemaUrl, $data, $multipart = true);
		$result = json_decode ($result, true);
		//application::dumpData ($result);
		
		# Add gamification points
		$activity = ($updateId ? 'AUDIT_UPDATE' : 'AUDIT_ADD');
		$this->addGamificationPoints ($activity, $result['id']);
		
		# Construct the URL of the new location
		$url = "/audit/location/{$result['id']}/";
		
		# Confirm outcome
		$action = ($updateId ? 'updated' : 'added');
		return $this->auditConfirmation ($action, $url);
	}
	
	
	# Function to confirm the outcome of the audit form change
	private function auditConfirmation ($action /* added/updated */, $urlLink)
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
			'jQuery'					=> false,	// Already loaded
			'jQueryUi'					=> false,	// Already loaded
		));
		$form->heading ('p', "Or you can mark this location as {$label}:");
		
		# Add textarea for problem (e.g. needing maintenance)
		if (substr_count ($label, 'problem')) {
			$form->textarea (array (
				'name'	=> 'comments',
				'title'	=> 'Add any comments',
				'rows'	=> 4,
				'cols'	=> 50,
			));
		}
		
		# Add survey date
		$form->datetime (array (
			'name' => 'surveyDate',
			'title' => 'Survey date',
			'description' => 'Date when this location was surveyed on-street',
			'required' => true,
			'picker' => true,
			'default' => date ('Y-m-d'),
		));
		
		# For maintenance form, report directly via e-mail, pending API support
		if (substr_count ($label, 'problem')) {
			$form->setOutputEmail ($this->settings['feedbackRecipient'], $this->settings['administratorEmail'], $this->settings['applicationName'] . ' contact form: problem');
		}
		
		# Process the form, and send to the template
		$result = $form->process ($formHtml);
		$this->template[$placeholder] = $formHtml;
		if (!$result) {return false;}
		
		# Return the result
		return $result;
	}
	
	
	# AJAX endpoint
	private function ajax ()
	{
		# Send JSON header
		header ('Content-type:application/json');
		
		# Ensure a call is specified
		if (!isSet ($_GET['call']) || !strlen ($_GET['call']) || !preg_match ('/^[a-z]+$/', $_GET['call'])) {
			echo $this->jsonError ('Error: No valid call supplied.');
			return;
		}
		
		# Ensure function exists
		$method = 'ajax' . ucfirst ($_GET['call']);
		if (!method_exists ($this, $method)) {
			echo $this->jsonError ('Error: No valid call supplied.');
			return;
		}
		
		# Return the call result
		echo $this->{$method} ();
	}
	
	
	# AJAX call to receive commit changes via AJAX, for an unchanged ID
	private function ajaxAuditunchanged ()
	{
		# Ensure ID supplied
		if (!isSet ($_POST['id'])) {
			return $this->jsonError ('Error: No ID supplied.');
		}
		
		# Assemble the data
		$id = $_POST['id'];
		$surveyDate = date ('Y-m-d');	// Assume the survey date to be today
		
		# Commit the change
		$points = $this->auditStatusCommit ('infrastructure.unchanged', $id, $surveyDate);
		
		# Return a success response
		$result = array (
			'success'	=> true,
			'points'	=> $points,
		);
		echo json_encode ($result);
	}
	
	
	# AJAX call for priority area deletion
	private function ajaxPriorityareasdelete ()
	{
		# Ensure ID supplied
		if (!isSet ($_POST['id'])) {
			return $this->jsonError ('Error: No ID supplied.');
		}
		
		# Assemble the data
		$data = array (
			'dataset'		=> $this->settings['auditDataset'],
			'id'			=> $_POST['id'],
		);
		
		# Post the response to the API; see: https://www.cyclestreets.net/api/v2/infrastructure.priorityareas.delete/
		$schemaUrl = $this->settings['apiBase'] . '/v2/' . 'infrastructure.priorityareas.delete' . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($schemaUrl, $data, $multipart = true);
		$result = json_decode ($result, true);
		//application::dumpData ($result);
		
		# If an error occured, send error header
		if (isSet ($result['error'])) {
			return $this->jsonError ('Error: ' . $result['error']);
		}
		
		# Return the result
		return json_encode ($result);
	}
	
	
	# Function to commit the results of an audit form for infrastructure unchanged/gone
	private function auditStatusCommit ($apiMethod, $id, $surveyDate)
	{
		# Assemble the update
		$data = array (
			'dataset'		=> $this->settings['auditDataset'],
			'id'			=> $id,
			'surveydate'	=> $surveyDate,
			'email'			=> $this->user['email'],
		);
		
		# Perform the commit; see: https://www.cyclestreets.net/api/v2/infrastructure.update/
		$schemaUrl = $this->settings['apiBase'] . '/v2/' . $apiMethod . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($schemaUrl, $data, $multipart = true);
		$result = json_decode ($result, true);
		//application::dumpData ($result);
		
		# Add gamification points
		$points = $this->addGamificationPoints ('AUDIT_CONFIRM', $id);
		
		# Return the points value, for use when running in an AJAX context
		return $points;
	}
	
	
	# Page to set priority areas
	private function priorityareas ()
	{
		# Finalise the API URL
		$this->actions[__FUNCTION__]['apiUrl'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[__FUNCTION__]['apiUrl']);
		
		# Create the map, in drawing mode
		$mapHtml = $this->mapPanel (__FUNCTION__, false, false, $viewOnlyMode = true, array (), $enableDrawing = 'Polygon');
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
		preg_match ('/^(VARCHAR|varchar|INTEGER|integer|ENUM|enum)\((.+)\)$/', $sqlFieldname, $matches);
		
		# Assemble the field
		$field = array (
			'Type' => $sqlFieldname,
			'Null' => true,
			'Key' => false,
			'Default' => NULL,
			'Extra' => false,
			'Comment' => $comment,
			'_values' => (strtoupper ($matches[1]) == 'ENUM' ? str_getcsv ($matches[2], ',', "'"): NULL),
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
		$mapHtml = $this->mapPanel ($type, false, false, $viewOnlyMode = true, $_GET);
		
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
		$this->template['map'] = $this->mapPanel ($action, $data, false);
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
	private function submissionPage ($action, $category, $existingData = array (), $schema = array (), $enableInitialCookieLocation = true)
	{
		# Start the HTML
		$html = '';
		
		# Create the form and process the data
		if (!$data = $this->locationSubmissionForm ($action, $existingData, $schema, $enableInitialCookieLocation, $category /* written into by reference */, $html /* &html written into by reference */)) {
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
		
		# Allocate zoom/lat/lon for location hash setting, shortening decimal places
		$zoom		= $data['zoom'];
		$latitude	= round ($data['latitude'], 6);
		$longitude	= round ($data['longitude'], 6);
		
		# Thank the user, resetting the HTML
		$html = $this->confirmationMessage ($result['id'], $existingData, $action, $zoom, $latitude, $longitude);

		# Determine the redirection path
		$mapLocationHash = '#' . ($data['zoom'] - 1) . '/' . $latitude . '/' . $longitude;
		$redirectToPath = $this->baseUrl . "/location/{$result['id']}/" . $this->iframeSuffix . $mapLocationHash;
		
		# Redirect the user
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
	private function confirmationMessage ($id, $isUpdate, $action, $zoom = false, $latitude = false, $longitude = false)
	{
		# Determine the map location hash
		$mapLocationHash = '#' . ($zoom - 1) . '/' . $latitude . '/' . $longitude;
		
		# Show confirmation
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
		$html  = "\n<p id=\"thankyou\">{$unicodeTick}" . ($isUpdate ? '<strong> Thank you for your update</strong>.' : "<strong> Thank you for your submission</strong>, which is number " . number_format ($id) . '.') . '</p>';
		$html .= "\n<p><a href=\"{$this->actions[$action]['url']}{$this->iframeSuffix}{$mapLocationHash}\">Add another?</a></p>";
		
		# Include social media sharing buttons if enabled in the settings
		$html .= $this->shareButtons ($action);
		
		# If the site is embedded and an 'onward' URL parameter is provided, provide an onward link
		if (isSet ($_GET['onward'])) {
			$mapLocationHash = '#' . '14' . '/' . $latitude . '/' . $longitude;		// Fixed zoom level, to show a general area, a little more than a Ward
			$currentMapUrl = $_SERVER['_SITE_URL'] . $this->actions[$action]['url'] . $mapLocationHash;
			$onwardUrl = $_GET['onward'] . (substr_count ($_GET['onward'], '?') ? '&' : '?') . 'map=' . urlencode ($currentMapUrl);
			$html .= "\n<div id=\"lobby\">";
			$html .= "\n\t<h3>Help lobby local decision-makers</h3>";
			$html .= "\n\t<p>Now that you have added a location to the map, please consider writing to your council, using our simple action form:</p>";
			$html .= "\n\t<p><strong><a target=\"_parent\" href=\"" . htmlspecialchars ($onwardUrl) . "\">Contact my local councillor &raquo;</strong></p>";
			if (isSet ($_GET['onwardimage'])) {
				$html .= "\n\t<p><a target=\"_parent\" href=\"" . htmlspecialchars ($onwardUrl) . '"><img src="' . htmlspecialchars ($_GET['onwardimage']) . '" /></p>';
			}
			$html .= "\n</div>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to post submissions to the API
	private function postSubmission ($rawdata, $action, $category, $license, $filePath, $existingData, &$errorText = '')
	{
		# Define the API URL; note this uses a POST operation due to the presence of a username and password
		$apiCall = ($existingData ? 'photomap.update' : 'photomap.add');
		$apiUrl = $this->settings['apiBase'] . '/v2/' . $apiCall . '?key=' . $this->settings['apiKey'];
		
		# Map the fields to the API
		$data = array (
			'username'				=> $this->settings['submissionsUsername'],
			'password'				=> $this->settings['submissionsPassword'],
			'metacategory'			=> $this->actions[$action]['metacategory'],
			'category'				=> $category,
			'caption'				=> mb_ucfirst ($rawdata['caption']),	// Provided by the application.php library
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
		
		# Include tag if required
		if ($this->settings['submitTag']) {
			$data['tags'] = $this->settings['submitTag'];
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
		# If the file is a URL, i.e. is the original file, mark as unchanged
		#!# This scenario should be handled as a native ultimateForm higher up in the code
		if (preg_match ('|https?://|', $file)) {return 'unchanged';}
		
		# Create and return the file handle
		if (function_exists ('curl_file_create')) {
			$mediaupload = curl_file_create ($file);	// Modern method, avoids CURL deprecation warnings from PHP 5.5+
		} else {
			$mediaupload = '@' . $file;	// Deprecated method using @ symbol - see: https://stackoverflow.com/a/4270282/180733
		}
		return $mediaupload;
	}
	
	
	# Function to provide social media links upon submission
	private function shareButtons ($action)
	{
		# Enable only the suggest page
		if ($action != 'suggest') {return false;}
		
		# Ensure one or more share settings are enabled
		if (!$this->settings['shareTwitterText'] && !$this->settings['shareWhatsappText']) {return false;}
		
		# Start the HTML
		$html = '';
		
		# Determine any explicit share URL if supplied by an iframe
		$iframeUrl = (isSet ($_GET['twitter']) ? $_GET['twitter'] : false);
		
		# Twitter sharing; see: https://developer.twitter.com/en/docs/twitter-for-websites/tweet-button/overview
		if ($this->settings['shareTwitterText']) {
			$dataUrl = ($iframeUrl ? $iframeUrl : false);		// URL is added implicitly if not specified; this will result in the map has being included
			$message = $this->settings['shareTwitterText'];
			$html .= "\n" . "<br />";
			$html .= "\n" . '<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
			$html .= "\n" . '<p><a class="twitter-share-button" data-size="large"' . ($dataUrl ? ' data-url="' . htmlspecialchars ($dataUrl) . '"' : '') . ' href="https://twitter.com/intent/tweet?text=' . htmlspecialchars (rawurlencode ($message)) . '">Tweet</a></p>';
		}
		
		# Whatsapp sharing; see: https://faq.whatsapp.com/general/chats/how-to-use-click-to-chat
		if ($this->settings['shareWhatsappText']) {
			$dataUrl = ($iframeUrl ? $iframeUrl : $_SERVER['_PAGE_URL']);
			$message = $this->settings['shareWhatsappText'] . ' ' . $dataUrl;
			$html .= "\n" . '<p class="whatsapp"><a href="https://wa.me/?text=' . htmlspecialchars (rawurlencode ($message)) . '"><img src="/images/whatsapp.png" /> WhatsApp this!</a></p>';
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Map panel, for setting a location and/or showing others
	private function mapPanel ($showLayer, $selectedIdData = array (), $markerSetInitiallyIsDraggable = false, $viewOnlyMode = false, $initialLocation = array (), $enableDrawing = false, $markerData = array (), $enableInitialCookieLocation = true)
	{
		# By default, no marker is shown
		$setMarkerInitially = false;
		
		# Set default map location
		$mapLocation = array (
			'latitude'	=> $this->settings['defaultLatitude'],
			'longitude'	=> $this->settings['defaultLongitude'],
			'zoom'		=> (int) $this->settings['defaultZoom'],
		);
		
		# If a selected ID was supplied, use that data
		if ($selectedIdData) {
			$mapLocation = array (
				'latitude'	=> $selectedIdData['latitude'],
				'longitude'	=> $selectedIdData['longitude'],
				'geometry'	=> (isSet ($selectedIdData['geometry']) ? $selectedIdData['geometry'] : NULL),
				'zoom'		=> $selectedIdData['zoom'],
			);
			$setMarkerInitially = true;
		}
		
		# If the form is posted, and a map location was set, extract the map location
		#!# This hack is only necessary until ultimateForm has built-in support for a native map widget, which means this whole method can then be replaced
		if (isSet ($_POST['form'])) {
			
			# Lat/lon/zoom values implementation
			if (isSet ($_POST['form']['latitude']) && isSet ($_POST['form']['longitude']) && isSet ($_POST['form']['zoom']) && preg_match ('/^[0-9-.]+$/', $_POST['form']['latitude']) && preg_match ('/^[0-9-.]+$/', $_POST['form']['longitude']) && preg_match ('/^[0-9]{1,2}$/', $_POST['form']['zoom'])) {
				$mapLocation = array (
					'latitude'	=> $_POST['form']['latitude'],
					'longitude'	=> $_POST['form']['longitude'],
					'zoom'		=> $_POST['form']['zoom'],
					'location'	=> $_POST['form']['location'],
				);
			}
			
			# Drawing mode implementation, which receives a posted GeoJSON location
			if ($enableDrawing && isSet ($_POST['form']['location']) && isSet ($_POST['form']['zoom'])) {
				$geometry = json_decode ($_POST['form']['location'], true);
				$centre = $this->getCentre ($geometry);
				$mapLocation = array (
					'latitude'	=> $centre['lat'],
					'longitude'	=> $centre['lon'],
					'zoom'		=> $_POST['form']['zoom'],
					'geometry'	=> $geometry,
				);
			}
			
			# Flag to set the marker
			$setMarkerInitially = true;
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
		
		# Overlay support
		$overlayHtml = $this->overlay ();
		
		# Determine whether a browsing API is to be shown
		$useBrowsingApi = false;
		if (in_array ($this->action, array ('suggest', 'current')) && $this->settings['showOthers']) {$useBrowsingApi = true;}
		if (in_array ($this->action, array ('audit', 'auditadd', 'priorityareas'))) {$useBrowsingApi = true;}
		
		# Determine the URL for the browsing API; if a selected ID is requested, request that this always be included in the returned data
		$browsingApiUrl = false;
		if ($useBrowsingApi) {
			$browsingApiUrl = $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl'] . '&key=' . $this->settings['apiKey'] . ($selectedIdData ? "&selectedid={$selectedIdData['id']}" : '');
			if ($this->settings['privateSubmissions']) {
				$browsingApiUrl .= '&private=1';
			}
		}
		$browsingApiUrlJs = ($browsingApiUrl ? "'" . $browsingApiUrl . "'" : 'false');
		
		# Define a second browsing layer if required
		if (isSet ($this->actions[$showLayer]['apiUrl2'])) {
			if (preg_match ('|^https?://|', $this->actions[$showLayer]['apiUrl2'])) {
				$browsingApiUrl2 = $this->actions[$showLayer]['apiUrl2'];
			} else {
				$browsingApiUrl2 = $this->settings['apiBase'] . $this->actions[$showLayer]['apiUrl2'] . '&key=' . $this->settings['apiKey'];
			}
			$browsingApiUrl2 = "'" . $browsingApiUrl2 . "'";
		} else {
			$browsingApiUrl2 = 'false';
		}
		
		# Load Leaflet.js
		$this->headContent['leaflet']  = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.6.0/dist/leaflet.css" />';
		$this->headContent['leaflet'] .= "\n" . '<script src="https://unpkg.com/leaflet@1.6.0/dist/leaflet.js"></script>';
		
		# Load leaflet-hash
		$this->headContent['leaflet-hash'] = '<script src="/js/lib/leaflet-hash/leaflet-hash.js"></script>';
		
		# Load Leaflet-active-area, in case this is activated by the style
		$this->headContent['leaflet-activearea'] = '<script src="/js/lib/Leaflet-active-area/src/leaflet.activearea.js"></script>';
		
		# Load Geolocation control; see: https://github.com/domoritz/leaflet-locatecontrol
		$this->headContent['leaflet-locatecontrol']  = '<script src="/js/lib/leaflet-locatecontrol/dist/L.Control.Locate.min.js"></script>';
		$this->headContent['leaflet-locatecontrol'] .= "\n" . '<link rel="stylesheet" href="/js/lib/leaflet-locatecontrol/dist/L.Control.Locate.min.css" />';
		$this->headContent['leaflet-locatecontrol'] .= "\n" . '<link rel="stylesheet" href="/js/lib/font-awesome/4.7.0/css/font-awesome.min.css" />';
		
		# Drawing mode
		if ($enableDrawing) {
			$this->headContent['leaflet-draw']  = '<script src="/js/lib/Leaflet.draw-0.4.14/dist/leaflet.draw.js"></script>';
			$this->headContent['leaflet-draw'] .= "\n" . '<link rel="stylesheet" href="/js/lib/Leaflet.draw-0.4.14/dist/leaflet.draw.css" rel="stylesheet" />';
		}
		
		# Load EXIF Filereader support
		$this->headContent['jquery-exif'] = '<script src="/js/lib/jquery.exif.js"></script>';
		
		# Load the map application Javascript and run it
		$userJs = ($this->user ? 'true' : 'false');
		$initialGeometryJs = (isSet ($mapLocation['geometry']) && $mapLocation['geometry'] ? json_encode ($mapLocation['geometry']) : 'false');
		$setMarkerInitiallyJs = ($setMarkerInitially ? 'true' : 'false');
		$markerSetInitiallyIsDraggableJs = ($markerSetInitiallyIsDraggable ? 'true' : 'false');
		$selectedIdJs = ($selectedIdData ? (ctype_digit ($selectedIdData['id']) ? $selectedIdData['id'] : "'{$selectedIdData['id']}'") : 'false');
		$viewOnlyModeJs = ($viewOnlyMode ? 'true' : 'false');
		$enableDrawingJs = ($enableDrawing ? "'{$enableDrawing}'" : 'false');	// Will be type, e.g. Polygon or LineString
		$popupLabelsJs = ($this->popupLabels ? json_encode ($this->popupLabels, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'false');
		$popupLabelSubsetFieldJs = ($this->popupLabelSubsetField ? "'{$this->popupLabelSubsetField}'" : 'false');
		$markerDataJs = ($markerData ? json_encode ($markerData) : 'false');
		$enableInitialCookieLocationJs = ($enableInitialCookieLocation ? 'true' : 'false');
		$multiCategoryModeJs = ($this->settings['multiCategoryMode'] ? 'true' : 'false');
		$enableOverlayJs = ($this->settings['overlayUrl'] ? 'true' : 'false');
		$this->headContent['application']  = "<script src=\"/js/telluswhere.js?{$this->template['revision']}\"></script>";
		$this->headContent['application'] .= "\n" . "<script>
		$(function() {
			var config = {
				baseUrl: '{$this->baseUrl}',
				initialLatitude: {$mapLocation['latitude']},
				initialLongitude: {$mapLocation['longitude']},
				initialZoom: {$mapLocation['zoom']},
				initialGeometry: {$initialGeometryJs},
				browsingApiUrl: {$browsingApiUrlJs},
				useIcon: '{$showLayer}',
				setMarkerInitially: {$setMarkerInitiallyJs},
				markerSetInitiallyIsDraggable: {$markerSetInitiallyIsDraggableJs},
				selectedId: {$selectedIdJs},
				browsingApiUrl2: {$browsingApiUrl2},
				viewOnlyMode: {$viewOnlyModeJs},
				enableDrawing: {$enableDrawingJs},
				multiCategoryMode: {$multiCategoryModeJs},
				enableOverlay: {$enableOverlayJs},
				popupLabels: {$popupLabelsJs},
				popupLabelSubsetField: {$popupLabelSubsetFieldJs},
				markerData: {$markerDataJs},
				limitToTag: '{$this->settings['limitToTag']}',
				since: '{$this->settings['since']}',
				tileUrl: '{$this->settings['tileUrl']}',
				tileOpacity: {$this->settings['tileOpacity']},
				apiBaseUrl: '{$this->settings['apiBase']}',
				apiKey: '{$this->settings['apiKey']}',
				geocoderBboxBounded: '{$this->settings['geocoderBboxBounded']}',
				enableInitialCookieLocation: {$enableInitialCookieLocationJs}
			};
			
			telluswhere.initialise (config, 'createMap', '{$this->action}', {$userJs});
		});
		</script>
		";
		
		# Start the HTML
		$html = '';
		
		# Start a container
		$html .= "\n\n" . '<div id="mapcontainer">';
		
		# Add geocoder
		$html .= $this->geocoder ();
		
		# Create the map itself
		$html .= "\n\n\t" . '<div id="map"></div>';
		
		# Zoom warning
		#!# This is a poor UI and should be replaced in older UI designs
		if (!$viewOnlyMode) {
			if (!$selectedIdData) {
				$html .= "\n\n\t" . '<p id="helptext">Zoom in further, then click to set location.</p>';
			}
		}
		
		# Category filtering, in multi-category mode
		$html .= $this->categoryFilters ();
		
		# Overlay support
		$html .= $overlayHtml;
		
		# Add a container that can be used flexibly for attribution
		$html .= "\n\n\t" . '<div id="attribution"></div>';
		
		# End the container
		$html .= "\n\n" . '</div>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a geocoder
	private function geocoder ($withGeolocation = false)
	{
		# Register assets
		$this->headContent['jquery-ui']  = '<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>';
		$this->headContent['jquery-ui'] .= "\n" . '<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" />';
		$this->headContent['cyclestreets-autocomplete']  = '<script src="/js/autocomplete.js?4"></script>';
		
		# Create the HTML
		$html  = "\n\n\t" . '<div id="geocoder">';
		if ($withGeolocation) {
			$html .= "\n\t\t" . '<img class="geolocation" src="/images/gps.png" />';
		}
		$html .= "\n\t\t" . '<input type="search" name="location" autocomplete="off" placeholder="Search locations" spellcheck="false" />';
		$html .= "\n\t" . '</div>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Category filtering
	private function categoryFilters ()
	{
		# Enable only in multi-category mode
		if (!$this->settings['multiCategoryMode']) {return;}
		
		# Get the available categories
		$categories = preg_split ('/[\s,]+/', trim ($this->settings['categories']));
		
		# Determine if a default has been set via the URL
		$defaultCategory = (isSet ($_GET['category']) && in_array ($_GET['category'], $categories) ? $_GET['category'] : false);
		
		# Create the HTML
		$html  = "\n\n\t" . '<div id="filters">';
		$html .= "\n\t\t" . '<select name="category" id="category">';
		$html .= "\n\t\t\t" . '<option value="">Show only category:</option>';
		foreach ($categories as $category) {
			$html .= "\n\t\t\t" . '<option value="' . $category . '"' . ($category == $defaultCategory ? ' selected="selected"' : '') . '>' . $this->categoryLabels[$category]['plural'] . '</option>';
		}
		$html .= "\n\t\t" . '</select>';
		$html .= "\n\t" . '</div>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Overlay support
	private function overlay ()
	{
		# End if not enabled
		if (!$this->settings['overlayUrl']) {return false;}
		
		# Register the overlay as URL 2, performing string replacement
		$this->actions['suggest']['apiUrl2'] = $this->settings['overlayUrl'];
		
		# Create the HTML
		$overlayButtonHtml = ($this->settings['overlayButtonHtml'] ? $this->settings['overlayButtonHtml'] : 'Show overlay?');
		$html  = "\n\n\t" . '<div id="overlay">';
		$html .= "\n\t\t" . '<label><input type="checkbox" name="overlay" value="true" /> ' . $overlayButtonHtml . '</label>';
		$html .= "\n\t" . '</div>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Location submission form
	private function locationSubmissionForm ($action, $existingData, $schema = array (), $enableInitialCookieLocation = true, &$category, &$html = '')
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
		
		# Create the map; alternatively, placeholderHtmlToFormTemplate may have already done this if the <!-- {$map} --> placeholder is within the form layout
		$mapLocation = (isSet ($data['latitude']) ? $data : array ());
		$this->template['map'] = $this->mapPanel ($action, $mapLocation, true, false, false, array (), false, array (), $enableInitialCookieLocation);
		
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
			'displayTemplate'			=> '{[[PROBLEMS]]}' . "\n{latitude}\n{longitude}\n{zoom}\n{location}" . $displayTemplate,
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> ($action == 'suggest' ? 'Add my idea' : 'Submit'),
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
			if ($this->settings['multiCategoryMode']) {
				if (in_array ('category', $formFieldsInTemplate)) {
					$categoriesList = preg_split ('/[\s,]+/', trim ($this->settings['categories']));
					$categories = array ();
					$titles = array ();
					foreach ($categoriesList as $category) {
						$categories[$category] = $this->categoryLabels[$category]['singular'];
						$titles[$category] = $this->categoryLabels[$category]['title'];
					}
					$form->radiobuttons (array (
						'name'			=> 'category',
						'title'			=> 'Which type of change is needed?',
						'required'		=> true,
						'values'		=> $categories,
						'titles'		=> $titles,
					));
				}
			}
			$form->textarea (array (
				'name'			=> 'caption',
				'title'			=> $this->metadataFieldLabels['caption'],
				'required'		=> true,
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
			if (in_array ('name', $formFieldsInTemplate)) {
				$form->input (array (
					'name'			=> 'name',
					'title'			=> 'Your name',
					'placeholder'	=> 'Your name',
					'required'		=> true,
					'default'		=> (isSet ($data['name']) ? $data['name'] : false),
				));
			}
		}
		if (in_array ('email', $formFieldsInTemplate)) {
			$form->email (array (
				'name'			=> 'email',
				'title'			=> 'Your e-mail address',
				'placeholder'	=> 'Your e-mail address',
				'required'		=> true,
				'default'		=> (isSet ($data['email']) ? $data['email'] : false),
			));
		}
		if (in_array ('mailinglist', $formFieldsInTemplate)) {
			$form->select (array (
				'name'			=> 'mailinglist',
				'title'			=> 'Would you like to be kept up-to-date via e-mail?',
				'required'		=> true,
				'values'		=> array ('Yes', 'No'),
				'default'		=> 'Yes',
			));
		}
		if (in_array ('terms', $formFieldsInTemplate)) {
			$form->checkboxes (array (
				'name'			=> 'terms',
				'required'		=> true,
				'values'		=> array ('Yes' => "I accept the <a target=\"_blank\" href=\"{$this->baseUrl}/terms/{$this->iframeSuffix}\">terms &amp; conditions</a>."),
				'entities'		=> false,
				'default'		=> 'Yes',
				'discard'		=> true,
			));
		}
		
		# Location (hidden)
		$this->addHiddenLocationFields ($form /* modified by reference */, $html /* modified by reference */);
		
		# Process the form
		$result = $form->process ($html);
		
		# Upon a successful submission, save the name and e-mail in a cookie for a short period to save the user having to re-type these
		if ($result) {
			$name = (isSet ($result['name']) ? $result['name'] : false);
			$this->setCourtesyUserdetails ($name, $result['email']);
			if ($this->settings['multiCategoryMode']) {
				$category = $result['category'];
			}
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to provide hidden location fields in a form
	private function addHiddenLocationFields (&$form, &$html, $initialValue = array ())
	{
		#!# ultimateForm has multiple bugs for hidden fields when using templating; for now, standard input widgets are used and then hidden using CSS
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
		$form->input (array (
			'name'			=> 'location',
			'title'			=> 'Location (set by clicking on map)',
			'required'		=> false,	// Handled using unfinalisedData method instead, so that these can be treated as a collection
			'default'		=> ($initialValue && array_key_exists ('location', $initialValue) ? $initialValue['location'] : false),
		));
		
		# Validate
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			$hasManualLocation = (strlen ($unfinalisedData['latitude']) && strlen ($unfinalisedData['longitude']) && strlen ($unfinalisedData['zoom']) && preg_match ('/^[0-9-.]+$/', $unfinalisedData['latitude']) && preg_match ('/^[0-9-.]+$/', $unfinalisedData['longitude']) && preg_match ('/^[0-9]{1,2}$/', $unfinalisedData['zoom']));
			$hasGeometryLocation = strlen ($unfinalisedData['location']);
			if (!$hasManualLocation && !$hasGeometryLocation) {
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
		
		# Initialise the Javascript application
		$this->initJsGeneral ();
		
		# Text of page
		$this->template['text'] = $this->settings['aboutPageHtml'];
		
		# Return the HTML
		return $html;
	}
	
	
	# Privacy page
	private function privacy ()
	{
		// No action - template contains everything
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
		
		# Pre-fill name from the user if logged in, or if not, unpack user details cookie if present from a previous submission
		$data = ($this->user ? $this->user : $this->getCourtesyUserdetails ());
		
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
			'default'	=> ($data ? str_replace ($this->settings['authNamespace'], '', $data['email']) : false),
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
		$this->headContent['generic-css'] = '<link rel="stylesheet" href="/css/generic.css" />';
		
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
			'database' => $this->settings['database'],
			'table' => 'settings',
			'intelligence' => true,
			'int1ToCheckbox' => true,
			'data' => $data,
			'attributes' => array (
				'url'				=> array ('heading' => array (3 => 'Core settings'), 'default' => $_SERVER['_SITE_URL'], 'editable' => false, ),
				'categories'	=> array ('description' => 'One category ID per line', ),
				'overlayUrl'		=> array ('heading' => array (3 => 'Overlay'), ),
				'aboutPageHtml'		=> array ('heading' => array (3 => 'Page texts'), ),
				'administrators'	=> array ('heading' => array (3 => 'Privileged users'), 'description' => 'One e-mail address per line', ),
				'downloaders'		=> array ('description' => 'One e-mail address per line', ),
				'batchUploaders'		=> array ('type' => 'textarea', ),
				#!# Add max/min/step/pattern for defaultLatitude/defaultLongitude when ultimateForm has support; see: https://stackoverflow.com/questions/15303940/
				'defaultLatitude'	=> array ('heading' => array (3 => 'Initial map location'), ),
				'earliestDate'		=> array ('heading' => array (3 => 'Export parameters'), ),
				'bbox'				=> array ('description' => 'W,S,E,N; data from: https://wiki.openstreetmap.org/wiki/Bounding_Box', ),
				'shareTwitterText'	=> array ('heading' => array (3 => 'Social media', 'p' => 'Sharing text will have the URL added automatically.'), ),
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
			$this->databaseConnection->insert ($this->settings['database'], 'settings', $result);
		} else {
			$this->databaseConnection->update ($this->settings['database'], 'settings', $result, array ('id' => $this->settings['id']));
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
		$html .= $this->mapPanel ($action, false, false, $viewOnlyMode = true, $locationsCentrepoint);
		
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
		
		#!# Fix styles in UCP
		$this->headContent['fix-ucp'] = '<style type="text/css">
			input[type=checkbox] {width: auto; margin-right: 10px;}
			label {display: inline;}
		</style>';
		
		# Add instructions
		$this->headContent['generic-css'] = '<link rel="stylesheet" href="/css/generic.css" />';
		$instructionBoxHtml  = "\n<div class=\"graybox\">";
		$instructionBoxHtml .= "\n\t<p>To add multiple locations, firstly assemble a spreadsheet containing the locations (either {$requiredLocationFieldsHtml}) in a spreadsheet.</p>";
		$instructionBoxHtml .= "\n\t<p>The spreadsheet file must have a header row, as shown in this example:</p>";
		$instructionBoxHtml .= "\n\t<p><img src=\"{$this->baseUrl}/images/multipleupload.png\" alt=\"Multiple upload example\" width=\"606\" height=\"172\" /></p>";
		$instructionBoxHtml .= "\n\t<p><strong>Required fields</strong> are: {$requiredLocationFieldsHtml}<br /><strong>Optional fields</strong> are: " . implode (', ', $optionalFields);
		$instructionBoxHtml .= "\n\t<p>Lat/lon pairs are assumed to be supplied in WGS84 (Web Mercator) projection.<br />If supplying northings/eastings pairs instead, these must be in OSGB36 projection; they will be converted to WGS84.</p>";
		$instructionBoxHtml .= "\n\t<p>If you have <strong>images</strong> of the locations, you will need to create a zip file of all the files. If these have been taken on a phone which captures the location automatically, that will be used in preference to the given latitude/longitudes.</p>";
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
		$form->input (array (
			'name'			=> 'extracredit',
			'title'			=> 'Optional credit line which will be appended to each caption',
			'required'		=> false,
			'size'			=> 80,
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
			$data[$index]['caption'] = trim (isSet ($location['caption']) ? $location['caption'] : $defaultCaption) . ($result['extracredit'] ? "\n\n" . $result['extracredit'] : '');
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
		
		# Load Leaflet.js
		$this->headContent['leaflet']  = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css" />';
		$this->headContent['leaflet'] .= "\n" . '<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"></script>';
		
		# Define standard map JS
		$this->headContent['leaflet-html'] = "
		<script>
			var osmLayer = 'https://{s}.tile.osm.org/{z}/{x}/{y}.png';
			var osmAttribution = '&copy; <a href=\"https://osm.org/copyright\">OpenStreetMap</a> contributors'
		</script>
		";
		
		# Add form and graybox styles
		$this->headContent['generic-css'] = '<link rel="stylesheet" href="/css/generic.css" />';
		
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
				<script>
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
		$instructionBoxHtml  = "\n<div class=\"graybox\">";
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
				'rows'			=> 4,
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
		
		# If using a dataset, show the unified download for this and end
		if ($this->settings['auditDataset']) {
			$html = "<p><a href=\"/data/audit.csv\">Audit data &mdash; CSV export</a></p>";
			$this->template['links'] = $html;
			return $html;
		}
		
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
		if ($this->settings['auditDataset']) {
			$datasets[] = 'audit';
		}
		if (!isSet ($_GET['dataset']) || !in_array ($_GET['dataset'], $datasets)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$dataset = $_GET['dataset'];
		
		# Get the data
		switch ($dataset) {
			
			# Suggest/current
			case 'sugggest':
			case 'current':
				
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
				
				break;
				
			# Audit
			case 'audit':
				
				# Define the parameters for the API call
				$parameters = array (
					'dataset'	=> $this->settings['auditDataset'],
					'approved'	=> '1',
					'format'	=> 'csv',
				);
				
				# Assemble the API call URL
				$apiUrl = $this->settings['apiBase'] . '/v2/infrastructure.locations' . '?key=' . $this->settings['apiKey'] . '&' . http_build_query ($parameters);
				
				break;
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
			$html .= "\n<p><strong>You are logged in</strong>, as " . str_replace ($this->settings['authNamespace'], '', $this->user['email']) . " .</p>";
			$html .= "\n<p>You can <a href=\"{$this->baseUrl}/logout/\">log out</a> if you wish.</p>";
			$this->template['text'] = $html;
			$this->template['form'] = false;
			if ($returnPath = preg_replace ('|/login/\??|', '', $_SERVER['REQUEST_URI'])) {
				$redirectTo = $_SERVER['_SITE_URL'] . $returnPath;
				application::sendHeader (302, $redirectTo, true);
			}
		} else {
			
			# Login form; if successful, log the user in
			$html .= "\n<p><strong>Please log in (or first create an account) below to access this section:</strong></p>";
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
			'identifier'	=> $this->settings['authNamespace'] . $email,
			'password'	=> $password,
		);
		
		# Post to the user authentication API
		$apiUrl = $this->settings['apiBase'] . $this->actions['login']['apiUrl'] . '?key=' . $this->settings['apiKey'];
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
				nav li.login, span.login {display: none;}
				nav li.register {display: none;}
			</style>
			';
		} else {
			$this->template['css'] = '
			<style type="text/css">
				nav li.profile, span.profile {display: none;}
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
		
		# Remove namespacing
		$email = str_replace ($this->settings['authNamespace'], '', $email);
		
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
			$this->user = false;
			$this->user = $this->getUser ();	// Hides the profile link
			$this->template['adminMenuLink'] = '';
			$this->template['adminLink'] = '';
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
		
		# Extend session time from 24 minutes
		ini_set ('session.gc_maxlifetime', 60*60*24*7);
		
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
		# If the user is already logged in, end
		if ($this->user) {
			$this->template['form'] = '<p>You are already logged in.</p>';
			return;
		}
		
		# If a token is specified, trigger this upstream
		if (isSet ($_GET['token']) && preg_match ('/^[a-f0-9]{24}$/', $_GET['token'])) {
			#!# Should be a proper API call upstream
			$url = 'https://www.cyclestreets.net/signin/register/' . $_GET['token'] . '/';
			$webpage = file_get_contents ($url);
			if (substr_count ($webpage, 'has now been validated')) {
				$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
				$this->template['form'] = "<p>{$unicodeTick} Thank you for validating the account. Please <a href=\"/login/?/audit/\">log in</a> to continue.</p>";
			}
			return;
		}
		
		# Create the form
		$formHtml = '';
		if (!$data = $this->profileForm ($formHtml)) {
			$this->template['form'] = $formHtml;
			return;
		}
		
		# Namespace the fields
		$data['email'] = $this->settings['authNamespace'] . $data['email'];
		$data['username'] = $this->settings['authNamespace'] . $data['username'];
		
		# Create the account, which will use the name,email,password fields
		$apiUrl = $this->settings['apiBase'] . '/v2/user.create' . '?key=' . $this->settings['apiKey'] . "&urlprefix={$_SERVER['_SITE_URL']}";
		$result = application::file_post_contents ($apiUrl, $data);
		$result = json_decode ($result, true);
		if (isSet ($result['error'])) {
			$this->template['form'] = "\n<p>Error: " . htmlspecialchars ($result['error'])  . '</p>';
			return false;
		}
		
		# Add the user profile settings
		$result = $this->setUserSettings ($data['email'], $data['city']);
		if (isSet ($result['error'])) {
			$this->template['form'] = "\n<p>Error: " . htmlspecialchars ($result['error'])  . '</p>';
			return false;
		}
		
		# Add gamification points for registering
		$this->addGamificationPoints ('TELLUSWHERE_REGISTER');
		
		# Confirm that the user should check their inbox
		#!# Link needs to be local
		$this->template['form'] = "\n<p>Many thanks. Please check your e-mail and click on the confirmation link we have sent you.</p>";
		return true;
	}
	
	
	# Function to set the user profile settings
	private function setUserSettings ($email, $city)
	{
		# Assemble the data
		$data = array (
			'email'	=> $email,
			'city'	=> $city,
		);
		
		# Add the user profile settings which will use the email,city fields
		$apiUrl = $this->settings['apiBase'] . '/v2/user.settings.set' . '?key=' . $this->settings['apiKey'];
		$result = application::file_post_contents ($apiUrl, $data);
		$result = json_decode ($result, true);
		return $result;
	}
	
	
	# Profile page
	private function profile ()
	{
		# Obtain the group of this user from the API
		$apiUrl = $this->settings['apiBase'] . '/v2/user.settings.get&key=' . $this->settings['apiKey'] . '&email=' . $this->user['email'];
		$userSettings = file_get_contents ($apiUrl);
		$userSettings = json_decode ($userSettings, true);
		$groupText = "Your score will also accrue to the <strong>%groupName</strong> group.";
		if ($cityId = $userSettings['city']) {
			$groupName = $this->cityIds[$cityId];
			$this->template['group'] = str_replace ('%groupName', $groupName, $groupText);
		} else {
			$this->template['group'] = 'If you wish, you can associate your scores with a group, by setting this below.';
		}
		
		# Calculate the number of edited locations
		$locationsEdited = 0;
		$editTypes = array ('AUDIT_UPDATE', 'AUDIT_CONFIRM');
		foreach ($editTypes as $editType) {
			if (isSet ($this->gamificationActivities['instances'][$editType])) {
				$locationsEdited += $this->gamificationActivities['instances'][$editType];
			}
		}
		
		# Send the group and gamification scores to the template
		$this->template['total'] = $this->gamificationActivities['total'];
		$this->template['groupScore'] = ($cityId ? "Your group's score is {$this->gamificationActivities['groupTotal']}." : 'Your scores are not currently accruing to a group.');
		$this->template['locationsEdited'] = $locationsEdited;
		$this->template['locationsAdded']  = (isSet ($this->gamificationActivities['instances']['AUDIT_ADD']) ?  $this->gamificationActivities['instances']['AUDIT_ADD']  : 0);
		
		# Create the profile update form
		#!# Update mode currently only supports setting of city, due to API restrictions
		$formHtml = '';
		if (!$data = $this->profileForm ($formHtml, $update = true, $data = array ('city' => $cityId))) {
			$this->template['form'] = $formHtml;
			return;
		}
		
		# Add the user profile settings
		$result = $this->setUserSettings ($this->user['email'], $data['city']);
		if (isSet ($result['error'])) {
			$this->template['form'] = "\n<p>Error: " . htmlspecialchars ($result['error'])  . '</p>';
			return false;
		}
		
		# Confirm update, and update the template
		$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
		$this->template['form'] = "<p>{$unicodeTick} Your profile has been updated.</p>";
		$this->template['group'] = str_replace ('%groupName', $this->cityIds[$data['city']], $groupText);
	}
	
	
	# Profile form
	private function profileForm (&$html, $update = false, $data = array ())
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'requiredFieldIndicator'	=> false,
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $this->placeholderHtmlToFormTemplate ('form', $this->action),
			'submitButtonText'			=> ($update ? 'Update' : 'Register'),
			'submitButtonAccesskey'		=> false,
			'autofocus'					=> true,
		));
		
		# Widgets
		if (!$update) {
			$form->input (array (
				'name'		=> 'name',
				'title'		=> 'Your name',
				'required'	=> true,
			));
		}
		$form->select (array (
			'name'		=> 'city',
			'title'		=> 'Borough (optional)',
			'values'	=> $this->cityIds,
			'default'	=> (isSet ($data['city']) ? $data['city'] : false),
		));
		if (!$update) {
			$form->input (array (
				'name'		=> 'username',
				'title'		=> 'Create a username',
				'required'	=> true,
				'description'	=> 'Lower-case letters and numbers only, no spaces',
				'placeholder'	=> 'Lower-case a-z, 0-9 only',
			));
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
				'confirmation'	=> true,
			));
		}
		
		# Process the form
		if (!$result = $form->process ($html)) {return false;}
		
		# Return the result
		return $result;
	}
	
	
	# Password reset page
	private function password ()
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'requiredFieldIndicator'	=> false,
			'display'					=> 'template',
			'displayTemplate'			=> '{[[PROBLEMS]]}' . $this->placeholderHtmlToFormTemplate ('form', $this->action),
			'submitButtonText'			=> 'Send me a reset link',
			'submitButtonAccesskey'		=> false,
			'autofocus'					=> true,
		));
		$form->email (array (
			'name'		=> 'email',
			'title'		=> 'E-mail address',
			'required'	=> true,
		));
		
		# Process the form
		if (!$result = $form->process ($html)) {
			$this->template['form'] = $html;
			return;
		}
	}
	
	
	# Login page
	private function adminlogin ()
	{
		$this->login ();
	}
	
	
	# Admin review submissions page
	private function adminreview ()
	{
		# Get the data
		$this->actions[$this->action]['apiUrl'] = str_replace ('%dataset', $this->settings['auditDataset'], $this->actions[$this->action]['apiUrl']);
		$apiUrl = $this->settings['apiBase'] . $this->actions[__FUNCTION__]['apiUrl'] . '&key=' . $this->settings['apiKey'];
		$data = file_get_contents ($apiUrl);
		$data = json_decode ($data, true);
		//application::dumpData ($data);
		
		# Slice to page
		$perPage = 25;
		$totalAssets = count ($data['features']);
		$totalPages = ceil ($totalAssets / $perPage);
		$page = 1;
		if (isSet ($_GET['page'])) {
			if (ctype_digit ($_GET['page']) && $_GET['page'] <= $totalPages) {
				$page = $_GET['page'];
			} else {	// Invalid page value, e.g. out of range
				$html = $this->page404 ();
				echo $html;
				return false;
			}
		}
		$startAt = (($page - 1) * $perPage);	// E.g. page 1 starts at 0 (first item), page 2 starts at 25 (i.e. 26th item)
		$data['features'] = array_slice ($data['features'], $startAt, $perPage);
		
		# Set the pagination values for the template
		$this->template['count'] = number_format ($totalAssets);
		$this->template['page'] = $page;
		$this->template['totalPages'] = $totalPages;
		$this->template['perPage'] = $perPage;
		
		# Create a pagination list
		$paginationLinks = array ();
		for ($i = 1; $i <= $totalPages; $i++) {
			$paginationLinks[] = "<li" . ($i == $page ? ' class="active"' : '') . "><a href=\"page{$i}.html\">{$i}</a>";
		}
		$this->template['paginationLinks'] = "\n<ul class=\"pagination\">\n\t" . implode ("\n\t", $paginationLinks) . "\n</ul>";
		
		# Support form autofill values
		$this->template['setallJs'] = "
			<script>
				$(function () {
					$('#setall').change (function () {
						$('form input[value=\"' + $(this).val() + '\"]').prop ('checked', true);
					});
				});
			</script>
		";
		
		# Create the map HTML
		$this->template['map'] = $this->mapPanel ($this->action, false, false, $viewOnlyMode = true);
		
		# Obtain schema labels
		$schema = $this->getAuditSchema ();
		$this->auditSetPopupLabels ($schema, $flatten = true);
		$labels = $this->popupLabels;
		
		# Create the form handler, and manually create each widget, which will be added to the template; the native ultimateForm HTML will be ignored, but the form processor will give the result as usual
		require_once ('ultimateForm.php');
		$form = new form (array ());
		$widgetsHtml = array ();
		$ids = array ();
		$rows = array ();
		$versions = array ();
		foreach ($data['features'] as $index => $feature) {
			$widgetName = "review_{$index}";
			$ids[$widgetName] = $feature['properties']['id'];
			$rows[$widgetName] = $index;
			$versions[$widgetName] = $feature['properties']['_version'];
			$form->radiobuttons (array (
				'name'		=> $widgetName,
				'title'		=> false,
				'values'	=> array ('approved' => 'Accept', 'rejected' => 'Reject'),
				'nullText'	=> '[Leave for now]',
				'required'	=> false,
			));
			$widgetsHtml[$index] = '
				<p><label><input name="form[' . $widgetName . ']" type="radio" value="" checked="checked" /><span>[Leave]</span></label></p>
				<p><label><input name="form[' . $widgetName . ']" type="radio" value="approved" /><span>Accept</span></label></p>
				<p><label><input name="form[' . $widgetName . ']" type="radio" value="rejected" /><span>Reject</span></label></p>
			';
		}
		$formHtml = '';
		$result = $form->process ($formHtml);	// Result is used below
		
		# Add each row of data
		$template = templating::commentsToPlaceholders ($htmlBlock, $replacedPlaceholders /* returned by reference */);
		$rowTemplate = $this->placeholderHtmlToFormTemplate ('tableRows', $this->action, false, array (), $innerPlaceholders /* returned by reference */);
		$replacements = array ();	// Replacements for each row
		foreach ($data['features'] as $row => $feature) {
			
			# Prepare the properties table
			$properties = $feature['properties'];
			unset ($properties['id']);
			unset ($properties['_type']);
			unset ($properties['_status']);
			unset ($properties['_version']);
			unset ($properties['_username']);
			unset ($properties['surveyDate']);
			unset ($properties['images']);
			unset ($properties['iconUrl']);
			
			# Extract data from the GeoJSON for this feature
			$substitutions = array (
				'featureId'		=> $feature['properties']['id'],
				'type'			=> $feature['properties']['_type'],
				'status'		=> $feature['properties']['_status'],
				'version'		=> $feature['properties']['_version'],
				'username'		=> str_replace ($this->settings['authNamespace'], '', $feature['properties']['_username']),
				'borough'		=> $feature['properties']['_borough'],
				'smallMap'		=> $this->smallMap ($feature['geometry'], $feature['properties']['iconUrl'], $row),
				'photo1'		=> $feature['properties']['images'][0],
				'photo2'		=> $feature['properties']['images'][1],
				'metadata'		=> application::htmlTableKeyed ($properties, $labels, true, 'lines compressed reviewmetadata'),
				'surveyDate'	=> $feature['properties']['surveyDate'],
				'review'		=> $widgetsHtml[$row],
			);
			
			# Perform substitution
			$replacements[$row] = array ();
			foreach ($substitutions as $placeholder => $substitution) {
				$key = '{' . $placeholder . '}';
				$replacements[$row][$key] = $substitution;
			}
		}
		
		# Process the form
		$formHtml = '';
		$result = $form->process ($formHtml);
		
		# Submit to the API
		if ($result) {
			foreach ($result as $widgetName => $status) {
				
				# Skip if no approval action set, i.e. leave for now
				if (!$status) {continue;}
				
				# Assemble the data
				$data = array (
					'dataset'	=> $this->settings['auditDataset'],
					'id'		=> $ids[$widgetName],
					'version'	=> $versions[$widgetName],
					'status'	=> $status,
					'email'		=> $this->user['email'],
				);
				
				# Perform the commit; see: https://www.cyclestreets.net/api/v2/infrastructure.moderate/
				$schemaUrl = $this->settings['apiBase'] . '/v2/' . 'infrastructure.moderate' . '?key=' . $this->settings['apiKey'];
				#!# Failure detection needed
				$result = application::file_post_contents ($schemaUrl, $data);
				$result = json_decode ($result, true);
				//application::dumpData ($result);
				
				# Update the template value for this row, replacing the widget rendering with the new status
				$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
				$row = $rows[$widgetName];
				$replacements[$row]['{review}'] = '<p class="success">' . $unicodeTick . ' ' . ucfirst ($status) . '</p>';
			}
		}
		
		# Construct the table for the template
		$table = array ();
		foreach ($replacements as $row => $values) {
			$table[$row] = strtr ($rowTemplate, $values);
		}
		$table = implode ("\n", $table);
		$this->template['tableRows'] = $table;
	}
	
	
	# Function to create a simple small map using Leaflet
	private function smallMap ($geometry, $iconUrl, $index = 0)
	{
		# Get the centre
		$centre = $this->getCentre ($geometry);
		
		# Determine the map geometry JS
		$mapId = 'smallmap' . $index;
		switch ($geometry['type']) {
			case 'Point':
				$geometryJs = "
				var icon = L.icon({iconUrl: '{$iconUrl}', shadowUrl: 'https://www.cyclestreets.net/images/categories/iconsets/cyclestreets/svg/shadow.svg', iconSize: [24, 40]});
				L.marker([{$centre['lat']}, {$centre['lon']}], {icon: icon}).addTo({$mapId});
				";
				break;
			case 'LineString':
				$geojson = json_encode ($geometry);
				$geometryJs = "
				var geojson{$index} = {$geojson};
				L.geoJSON (geojson{$index}, {
					style: {color: 'red', weight: 8}
				}).addTo({$mapId});
				";
				break;
		}
		
		# Create the HTML; see: https://leafletjs.com/examples/quick-start/example.html
		$html  = "
			<div id=\"{$mapId}\" class=\"smallmap\"></div>
			<script>
				var {$mapId} = L.map('{$mapId}').setView([{$centre['lat']}, {$centre['lon']}], 15);
				L.tileLayer('https://{s}.tile.cyclestreets.net/opencyclemap/{z}/{x}/{y}@2x.png', {opacity: 0.7}).addTo({$mapId});
				{$geometryJs}
			</script>
		";
		
		# Return the HTML
		return $html;
	}
	
	
	# Helper function to get the centre-point of a geometry
	private function getCentre ($geometry)
	{
		# Determine the centre point
		switch ($geometry['type']) {
			
			case 'Point':
				$centre = array (
					'lat'	=> $geometry['coordinates'][1],
					'lon'	=> $geometry['coordinates'][0]
				);
				break;
				
			case 'LineString':
				$longitudes = array ();
				$latitudes = array ();
				foreach ($geometry['coordinates'] as $lonLat) {
					$longitudes[] = $lonLat[0];
					$latitudes[] = $lonLat[1];
				}
				$centre = array (
					'lat'	=> ((max ($latitudes) + min ($latitudes)) / 2),
					'lon'	=> ((max ($longitudes) + min ($longitudes)) / 2)
				);
				break;
				
			case 'MultiLineString':
				$longitudes = array ();
				$latitudes = array ();
				foreach ($geometry['coordinates'] as $line) {
					foreach ($line as $lonLat) {
						$longitudes[] = $lonLat[0];
						$latitudes[] = $lonLat[1];
					}
				}
				$centre = array (
					'lat'	=> ((max ($latitudes) + min ($latitudes)) / 2),
					'lon'	=> ((max ($longitudes) + min ($longitudes)) / 2)
				);
				break;
		}
		
		# Return the centre
		return $centre;
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
		# Obtain the gamification data
		$apiUrl = $this->settings['apiBase'] . '/v2/gamification.cities?key=' . $this->settings['apiKey'];
		$data = file_get_contents ($apiUrl);
		$scores = json_decode ($data, true);
		if (isSet ($scores['error'])) {
			return array ();
		}
		
		# Obtain the progress review data for each boundary area
		$apiUrl = $this->settings['apiBase'] . '/v2/infrastructure.reviewprogress?key=' . $this->settings['apiKey'] . '&dataset=' . $this->settings['auditDataset'] . '&aspect=boundaries';
		$data = file_get_contents ($apiUrl);
		$progress = json_decode ($data, true);
		if (isSet ($progress['error'])) {
			return array ();
		}
		
		# Assemble the table data, seeding from the city list
		#!# Need to put highest first
		$table = array ();
		foreach ($this->cityIds as $id => $name) {
			$table[] = array (
				'borough'	=> $name,
				'progress'	=> (isSet ($progress[$id]) ? $progress[$id]['completionPercentage'] : '0') . ' %',
				'score'		=> (isSet ($scores[$id]) ? number_format ($scores[$id]['score']) : '0'),
			);
		}
		
		# Send to the template
		$this->template['table'] = application::htmlTable ($table, array (), $class = 'responsive-table', $keyAsFirstColumn = false, $uppercaseHeadings = true);
	}
	
	
	# Admin progress by priority areas
	private function adminpriorityareas ()
	{
		# Obtain the progress review data for each priority area
		$apiUrl = $this->settings['apiBase'] . '/v2/infrastructure.reviewprogress?key=' . $this->settings['apiKey'] . '&dataset=' . $this->settings['auditDataset'] . '&aspect=priorityareas';
		$data = file_get_contents ($apiUrl);
		$progress = json_decode ($data, true);
		if (isSet ($progress['error'])) {
			return array ();
		}
		
		# End if error
		if (isSet ($data['error'])) {
			return array ();
		}
		
		# Assemble the table data
		$table = array ();
		foreach ($progress['features'] as $index => $area) {
			unset ($area['properties']['id']);
			$table[$index] = $area['properties'];
			$table[$index]['name'] = "<a href=\"{$this->baseUrl}/audit/#17/{$area['geometry']['coordinates'][1]}/{$area['geometry']['coordinates'][0]}\">" . htmlspecialchars ($area['properties']['name']) . '</a>';
			$table[$index]['completionPercentage'] .= ' %';
		}
		
		# Send to the template
		$this->template['table'] = application::htmlTable ($table, array (), $class = 'responsive-table', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = array ('name'));
	}
	
	
	# Function to support feedback handler
	private function feedbackHandler ()
	{
		# Register assets
		$this->headContent['vex']  = '<script src="/js/lib/vex-4.1.0/dist/js/vex.combined.min.js"></script>';
		$this->headContent['vex'] .= "\n" . '<link rel="stylesheet" href="/js/lib/vex-4.1.0/dist/css/vex.css" />';
		$this->headContent['vex'] .= "\n" . '<link rel="stylesheet" href="/js/lib/vex-4.1.0/dist/css/vex-theme-plain.css" />';
		
		# Create the HTML
		$this->template['feedback'] = '
			<div id="feedback">
				
				<h2>Give feedback</h2>
				<form method="post" id="feedbackform" name="feedbackform" action="https://www.cyclestreets.net/feedback/" enctype="application/x-www-form-urlencoded" accept-charset="UTF-8">
					<table>
						<tr>
							<td colspan="2">
								<p>We welcome your feedback!</p>
							</td>
						</tr>
						<tr>
							<td>Comments:</td>
							<td><textarea name="comments" cols="60" rows="5" required="required"></textarea></td>
						</tr>
						<tr>
							<td>Your name:</td>
							<td><input type="text" name="name" size="40" maxlength="255" required="required" /></td>
						</tr>
						<tr>
							<td>E-mail:</td>
							<td><input type="email" name="email" size="40" maxlength="255" required="required" /></td>
						</tr>
						<tr>
							<td></td>
							<td><input type="submit" value="Submit!" class="button" /></td>
						</tr>
					</table>
					<input type="hidden" name="type" value="other" />
				</form>
				
			</div>
		';
	}
}

?>
