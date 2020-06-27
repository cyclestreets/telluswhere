<?php

/**
 * Helper class to handle batch import functionality
 */
class batchImport
{
	# Class properties
	private $html = '';
	private $headContent = array ();
	
	
	# Constructor
	public function __construct ($telluswhere, $tmpDirectory, $tmpFolder, $categoryLabels)
	{
		# Create handles to the main class properties
		$this->telluswhere = $telluswhere;
		$this->baseUrl = $telluswhere->baseUrl;
		$this->databaseConnection = $telluswhere->databaseConnection;
		$this->settings = $telluswhere->settings;
		$this->actions = $telluswhere->actions;
		$this->metacategories = $telluswhere->metacategories;
		$this->categories = $telluswhere->categories;
		$this->user = $telluswhere->user;
		
		# Other properties
		$this->tmpDirectory = $tmpDirectory;
		$this->tmpFolder = $tmpFolder;
		$this->categoryLabels = $categoryLabels;
	}
	
	
	# Function to get the HTML
	public function getHtml ()
	{
		return $this->html;
	}
	
	
	# Function to get the head content
	public function getHeadContent ()
	{
		return $this->headContent;
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
			$this->telluswhere->sessionDestroy ($sessionName);
			$redirectTo = $_SERVER['_SITE_URL'] . $this->baseUrl . $this->actions[__FUNCTION__]['url'];
			$html .= application::sendHeader (302, $redirectTo, true);
			$this->html = $html;
			return;
		}
		
		# Get initial data or end
		if (!$data = $this->initialDataForm ($sessionName)) {return;}
		
		# Confirm data
		if (!$data = $this->confirmDataForm ($sessionName, $data)) {return;}
		
		# Add each entry via the API, reporting any error
		foreach ($data as $location) {
			$action = $this->metacategories[$location['metacategory']];
			if (!$result = $this->telluswhere->postSubmission ($location, $action, $location['category'], $location['license'], $this->imagesDirectory, false, $errorText)) {
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
		$html .= $this->telluswhere->mapPanel ($action, false, false, $viewOnlyMode = true, $locationsCentrepoint);
		
		# Register the HTML
		$this->html = $html;
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
	private function initialDataForm ($sessionName)
	{
		# Retrieve and return session data, if it exists
		if ($data = $this->telluswhere->sessionGet ($sessionName)) {
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
			'category',
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
			if (count ($this->categories) > 1) {
				$metacategories[$metacategory] = str_replace ('%categoryLabel', 'infrastructure', $this->actions[$action]['description']);
			} else {
				$metacategories[$metacategory] = str_replace ('%categoryLabel', lcfirst ($this->categoryLabels[$category]['singular']), $this->actions[$action]['description']);
			}
		}
		
		#!# Fix styles in UCP
		$this->headContent['fix-ucp'] = '<style type="text/css">
			input[type=checkbox] {width: auto; margin-right: 10px;}
			label {display: inline;}
		</style>';
		
		# Add instructions
		$this->headContent['generic-css'] = '<link rel="stylesheet" href="/css/generic.css" />';
		$instructionBoxHtml  = "\n<div class=\"graybox\">";
		$instructionBoxHtml .= "\n\t<p>To add multiple locations, firstly assemble a spreadsheet containing the locations (either {$requiredLocationFieldsHtml}) in either a GeoJSON file or a spreadsheet.</p>";
		$instructionBoxHtml .= "\n\t<p>If using a spreadsheet, the file must have a header row, as shown in this example:</p>";
		$instructionBoxHtml .= "\n\t<p><img src=\"{$this->baseUrl}/images/multipleupload.png\" alt=\"Multiple upload example\" width=\"606\" height=\"172\" /></p>";
		$instructionBoxHtml .= "\n\t<p><strong>Required fields</strong> are: {$requiredLocationFieldsHtml}<br /><strong>Optional fields</strong> are: " . implode (', ', $optionalFields);
		$instructionBoxHtml .= "\n\t<p>Lat/lon locations are assumed to be supplied in WGS84 (Web Mercator) projection.<br />If supplying northings/eastings pairs instead, these must be in OSGB36 projection; they will be converted to WGS84.</p>";
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
		$form->textarea (array (
			'name'			=> 'metadata',
			'title'			=> 'Paste in the data copied from your spreadsheet or GeoJSON file - see notes above',
			'required'		=> true,
			'rows'			=> 12,
			'cols'			=> 60,
		));
		$form->input (array (
			'name'			=> 'categoryfieldname',
			'title'			=> 'If using GeoJSON, the fieldname that contains the category',
			'required'		=> false,
		));
		$form->textarea (array (
			'name'			=> 'categorymapping',
			'title'			=> 'Category mapping - one per line, as two tab-separated columns, current then new',
			'required'		=> false,
			'rows'			=> 4,
			'cols'			=> 60,
		));
		$form->input (array (
			'name'			=> 'captionfieldname',
			'title'			=> 'If using GeoJSON, the fieldname that contains the caption',
			'required'		=> false,
		));
		$form->input (array (
			'name'			=> 'extracredit',
			'title'			=> 'Optional credit line which will be appended to each caption',
			'required'		=> false,
			'size'			=> 80,
		));
		$form->input (array (
			'name'			=> 'tags',
			'title'			=> 'Tag(s)',
			'required'		=> false,
			'default'		=> $this->settings['submitTag'],
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
			'name'			=> 'images',
			'title'			=> '(Optional) Images - zipped as single file (maximum size: ' . ini_get ('upload_max_filesize') . ')',
			'directory'		=> $this->imagesDirectory,
			'required'		=> false,
			'allowedExtensions'		=> array ('zip'),
			'enableVersionControl'	=> false,
			'flatten'		=> true,
			'unzip'			=> true,
		));
		$form->select (array (
			'name'			=> 'license',
			'title'			=> 'License',
			'values'		=> array ('publicdomain' => 'Public domain (preferred)', 'ogl' => 'Open Government Licence'),
			'required'		=> true,
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
				if (!$data = $this->getBatchData ($unfinalisedData['metadata'], $unfinalisedData['captionfieldname'], $unfinalisedData['categoryfieldname'], $unfinalisedData['categorymapping'], $optionalFields, $locationFields, $requiredLocationFieldsHtml, $errorMessage)) {
					$form->registerProblem ('tsvinvalid', $errorMessage);
				}
			}
		}
		
		# Process the form
		if (!$result = $form->process ($html)) {
			$this->html = $html;
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
			$this->html = $html;
			return false;
		}
		
		# Add in a caption where not present
		$metacategory = $result['metacategory'];
		$action = $this->metacategories[$metacategory];
		$defaultCaption = str_replace ('%categoryLabel', lcfirst ($this->categoryLabels[$category]['singular']), $this->actions[$action]['description']);
		foreach ($data as $index => $location) {
			$data[$index]['caption'] = trim (isSet ($location['caption']) ? $location['caption'] : $defaultCaption) . ($result['extracredit'] ? "\n\n" . $result['extracredit'] : '');
			$data[$index]['category'] = (isSet ($location['category']) ? $location['category'] : $this->categories[0]);
			$data[$index]['metacategory'] = $metacategory;
			$data[$index]['license'] = $result['license'];
			$data[$index]['tags'] = $result['tags'];
			$data[$index]['name'] = $result['name'];
			$data[$index]['email'] = $result['email'];
		}
		
		# Register the HTML
		$this->html = $html;
		
		# Create the session entry
		$this->telluswhere->sessionWrite ($sessionName, $data);
		
		# Return the data
		return $data;
	}
	
	
	# Batch form stage 2
	private function confirmDataForm ($sessionName, $stage1Data)
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
				'No.'		=> ($index + 1),
				'caption'	=> "{caption_{$index}}",
				'category'	=> $stage1Data[$index]['category'],
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
			$this->html = $html;
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
				'category'		=> $stage1Data[$i]['category'],
				'metacategory'	=> $stage1Data[$i]['metacategory'],
				'license'		=> $stage1Data[$i]['license'],
				'name'			=> $stage1Data[$i]['name'],
				'email'			=> $stage1Data[$i]['email'],
			);
			if (strlen ($stage1Data[$i]['tags'])) {
				$data[$i]['tags'] = $stage1Data[$i]['tags'];
			}
			if (isSet ($stage1Data[$i]['filename'])) {
				$data[$i]['filename'] = $stage1Data[$i]['filename'];
			}
		}
		
		# Register the HTML
		$this->html = $html;
		
		# Destroy the session data from the first stage
		$this->telluswhere->sessionDestroy ($sessionName);
		
		# Return the finalised data
		return $data;
	}
	
	
	# Function to process submitted batch metadata string (TSV or GeoJSON) and assemble the data from it
	private function getBatchData ($metadata, $captionFieldname, $categoryFieldname, $categoryMapping, $optionalFields, $locationFields, $requiredLocationFieldsHtml, &$errorMessage = '')
	{
		# Determine the format, either TSV or GeoJSON
		$isGeoJson = (preg_match ('/^{/', trim ($metadata)) && preg_match ('/}$/', trim ($metadata)) && substr_count ($metadata, 'FeatureCollection'));
		if ($isGeoJson) {
			$data = $this->getBatchDataGeojson ($metadata, $captionFieldname, $categoryFieldname);
		} else {
			$data = $this->getBatchDataTsv ($metadata);
		}
		
		# Convert categories if required
		if ($categoryMapping) {
			
			# Parse out the TSV block
			$categoryMappingLines = explode ("\n", trim ($categoryMapping));
			$categoryMapping = array ();
			foreach ($categoryMappingLines as $line) {
				list ($current, $new) = explode ("\t", trim ($line));	// Trim necessary to ensure \r is removed
				$categoryMapping[$current] = $new;
			}
			
			# Substitute values
			foreach ($data as $index => $record) {
				$category = trim ($record['category']);
				$data[$index]['category'] = $categoryMapping[$category];
			}
		}
		
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
	
	
	# Function to convert the GeoJSON string to an array
	private function getBatchDataGeojson ($geojson, $captionFieldname = 'caption', $categoryFieldname = 'category')
	{
		# Decode the data to JSON
		$geojson = json_decode ($geojson, true);
		
		# Loop through each feature
		$data = array ();
		foreach ($geojson['features'] as $feature) {
			
			# If the feature is a LineString, convert to a Point, taking the middle point
			if ($feature['geometry']['type'] == 'LineString') {
				$totalCoordinates = count ($feature['geometry']['coordinates']);
				$middlePoint = ceil ($totalCoordinates / 2) - 1;	// E.g. 5 points gets the centre one; 2 points gets the end
				$feature['geometry'] = array (
					'type'			=> 'Point',
					'coordinates'	=> $feature['geometry']['coordinates'][$middlePoint],
				);
			}
			
			# If there is no caption, use the category
			if (!strlen ($feature['properties'][$captionFieldname])) {
				$feature['properties'][$captionFieldname] = $feature['properties'][$categoryFieldname];
			}
			
			# Register this location
			$data[] = array (
				#!# No support yet for LineString
				'latitude' => $feature['geometry']['coordinates'][1],
				'longitude' => $feature['geometry']['coordinates'][0],
				'caption' => $feature['properties'][$captionFieldname],
				'filename' => false,
				'category' => $feature['properties'][$categoryFieldname],
			);
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to convert the TSV string to an array
	private function getBatchDataTsv ($tsv)
	{
		# Parse the TSV string
		require_once ('csv.php');
		$data = csv::tsvToArray (trim ($tsv), $firstColumnIsId = false, $firstColumnIsIdIncludeInData = true);
		
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
}
