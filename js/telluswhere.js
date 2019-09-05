// Telluswhere javascript module
var telluswhere = (function ($) {
	'use strict';
	
	
	/* Class properties */
	
	// Internal class properties
	var map;	// Should be _map but makes pasting from leaflet examples (which always use map) easier
	var _marker;
	var _icons;
	var _useJsonpTransport;
	var _currentDataLayers = {};
	var _geolocationData;
	var _maxZoom;
	var _minZoom;
	var _viewOnlyMode;
	var _enableDrawing;
	
	// baseUrl of application
	var _baseUrl;
	
	// GUI action
	var _action;
	
	// Initial map location
	var _initialLatitude;
	var _initialLongitude;
	var _initialZoom;
	
	// The API endpoint(s) to use for browsing
	var _browsingApiUrls = {};
	
	// The icon to use
	var _useIcon;
	
	// Whether to set a marker initially
	var _setMarkerInitially;
	var _markerSettingZoom = 19;
	
	// Selected ID, if any, and whether it is moveable
	var _selectedId;
	
	// Popup labelling
	var _popupLabels = {};
	var _popupLabelSubsetField;
	
	
	
	return {
		
// Public functions
		
		// Main function
		createMap: function (settings)
		{
			// Set class properties
			_baseUrl = settings.baseUrl;
			_action = settings.action;
			_initialLatitude = settings.initialLatitude;
			_initialLongitude = settings.initialLongitude;
			_initialZoom = settings.initialZoom;
			_maxZoom = settings.maxZoom || 21;
			_minZoom = settings.minZoom || 7;
			_browsingApiUrls[0] = settings.browsingApiUrl;
			if (settings.browsingApiUrl2) {
				_browsingApiUrls[1] = settings.browsingApiUrl2;
			}
			_useIcon = settings.useIcon;
			_setMarkerInitially = settings.setMarkerInitially;
			_selectedId = settings.selectedId;	// ID of selected item
			_viewOnlyMode = settings.viewOnlyMode;
			_enableDrawing = settings.enableDrawing;
			_popupLabels = settings.popupLabels;
			_popupLabelSubsetField = settings.popupLabelSubsetField;
			
			// Use cookie location if present
			telluswhere.readMapLocationCookie ();
			
			// Set map centre location
			map = L.map('map').setView([_initialLatitude, _initialLongitude], _initialZoom);
			
			// Set tile layer
			var tileUrl = 'https://{s}.tile.cyclestreets.net/opencyclemap/{z}/{x}/{y}.png';
			var tileAttribution = 'Map data &copy; <a href=\"https://www.openstreetmap.org/\">OpenStreetMap</a> contributors (<a href=\"https://www.openstreetmap.org/copyright\">ODbL</a>)';
			L.tileLayer(tileUrl, {
				attribution: tileAttribution,
				maxZoom: _maxZoom,
				minZoom: _minZoom,
				opacity: 0.8
			}).addTo(map);
			
			// Add hash
			new L.Hash (map);
			
			// Add geolocation control; see: https://github.com/domoritz/leaflet-locatecontrol
			map.addControl(L.control.locate({
				icon: 'fa fa-location-arrow',
				setView: 'once',	// The default, 'untilPanOrZoom', can reduce battery heavily
				locateOptions: {maxZoom: 17}
			}));
			
			// Set cookie on map move
			map.on ('moveend', function (e) {
				telluswhere.setMapLocationCookie ();
			});
			
			// Transmit current location
			telluswhere.transmitCurrentLocation();
			
			// Define the icon set; see: http://leafletjs.com/examples/custom-icons.html
			_icons = telluswhere.getIcons();
			
			// Determine whether to set the marker initially
			if(_setMarkerInitially){
				var latlng = L.latLng(_initialLatitude, _initialLongitude);
				telluswhere.setMarker(latlng, _useIcon, settings.markerSetInitiallyIsDraggable, settings.markerData);
				map.setView(latlng, _markerSettingZoom);
			}
			
			// Add drawing support if enabled
			if (_enableDrawing) {
				telluswhere.drawing ('#geometry', true, '');
			}
			
			// Register click handler
			map.on('click', telluswhere.onMapClick);
			
			// Add each data layer to the map, if enabled
			$.each (_browsingApiUrls, function (index, url) {
				_currentDataLayers[index] = L.geoJson (null /* added later instead, using .addData */ , {
					
					// Filter, to skip existing selected
					filter: telluswhere.setIconFilter,
					
					// Style points - create a marker
					pointToLayer: function (feature,latlng) {
						var icon = _icons['already'];
						if (feature.properties.iconUrl) {
							icon = _icons['_dynamic'];
							icon.options.iconUrl = feature.properties.iconUrl;
						}
						return L.marker (latlng, {icon: icon});
					},
					
					// Add interactions
					onEachFeature: function (feature, layer) {
						
						// Remove internal colour field if present
						if (feature.properties.hasOwnProperty ('_colour')) {
							delete feature.properties._colour;
						}
						
						// Add popups
						layer.bindPopup (telluswhere.popupHtml (feature.properties), {autoPanPaddingTopLeft: [0, 70]});
						
						// Add hover styles; see: https://leafletjs.com/examples/choropleth/
						layer.on ({
							mouseover: function (e) {
								if (e.target.feature.geometry.type == 'LineString' || e.target.feature.geometry.type == 'MultiLineString') {
									var layer = e.target;
									layer.setStyle({
										color: 'red',
										weight: 10
									});
								}
							},
							mouseout: function (e) {
								if (e.target.feature.geometry.type == 'LineString' || e.target.feature.geometry.type == 'MultiLineString') {
									var layer = e.target;
									layer.setStyle({
										color: 'red',
										weight: 5	// i.e. reset to style below
									});
								}
							}
						});
					},
					
					// Polygon styling
					style: function (feature) {
						var styles = {};
						
						// Lines
						if (feature.geometry.type == 'LineString' || feature.geometry.type == 'MultiLineString') {
							styles.color = 'red';
							styles.weight = 5;
						}
						
						// Polygons
						if (feature.geometry.type == 'Polygon') {
							if (feature.properties.hasOwnProperty ('_colour')) {
								styles.color = feature.properties._colour;
								styles.fillColor = feature.properties._colour;
							} else {
								styles.color = 'red';
								styles.fillColor = 'red';
							}
						}
						
						// Return the styles
						return styles;
					}
				});
				_currentDataLayers[index].addTo(map);
			});
			
			// Add support for Like clicks
			$('body').on('click','#likes a', function(event){	// https://stackoverflow.com/a/19133666/180733
				event.preventDefault();
				$.ajax({
					url: $(this).attr('href'),
					success: function(data) {
						var totalLikes = (data.total > 0 ? data.total : '');
						$('#likescurrent').text(totalLikes);
						if(data.liked) {
							$('#likes').addClass('liked');
							$('#likestext').text('Agreed!');
						} else {
							$('#likes').removeClass('liked');
							$('#likestext').text('Agree?');
						}
						$('#likes').removeClass('changed');
						$('#likes').addClass('changed');
					},
					error: function (xhr, status, error) {
						var data = $.parseJSON(xhr.responseText);
						alert(data.error);
					}
				});
			});
			
			// Determine whether to use JSONP transport instead of JSON for the marker layer calls (for older browsers)
			_useJsonpTransport = telluswhere.useJsonpTransport();
			
			// Register moveend
			map.on('moveend', telluswhere.whenMapMoves);
			
			// Get the data on initial view
			telluswhere.getData();
			
			// Register reporting link function
			if (_useIcon == 'current') {
				map.on('popupopen', telluswhere.problemForm);
			}
			
			// Show the help text also if the user zooms
			map.on('zoomstart', function() {
				$('#helptext').addClass('display');
			});
			
			// EXIF callback for file upload
			try {
				$('#form_filename_0').change(function() {
					$(this).fileExif(telluswhere.exifCallback);
				});
			}
			catch (e) {
				alert(e);
			}
			
			// Return map
			return map;
		},
		
		
// Private functions

		/* Core map functions */
		
		
		// Icon definition
		getIcons: function ()
		{
			// Define basic large and small icons
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
			
			// Assemble the icons list
			var icons = {
				suggest: new largeIcon({iconUrl: '/images/markers/suggest.png'}),
				current: new largeIcon({iconUrl: '/images/markers/current.png'}),
				already: new smallIcon({iconUrl: '/images/markers/already.png'}),
				auditlocation: new smallIcon({iconUrl: '/images/markers/auditlocation.png'}),
				auditaddlocation: new smallIcon({iconUrl: '/images/markers/auditaddlocation.png'}),
				_dynamic: new smallIcon({iconUrl: null})
			};
			
			// Return the icons
			return icons;
		},
		
		
		// Create marker and popup when clicking on the map
		onMapClick: function (e)
		{
			// Show the help text
			$('#helptext').addClass('display');
			
			// Remove any marker present
			if(telluswhere._marker){
				map.removeLayer(telluswhere._marker);
			}
			
			// Define minimum zoom level to set
			var minZoomLevelToSet = 18;
			
			// If drawing is enabled, reduce the zoom level
			if (_enableDrawing) {
				minZoomLevelToSet = 14;
			}
			
			// For the audit layer, require a close zoom before loading due to the volume of data
			// #!# Needs turning into a database setting if future datasets
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation') {
				minZoomLevelToSet = 16;
			}
			
			// Zoom if too far out and end
			if(map.getZoom() < minZoomLevelToSet){
				telluswhere.setFormValues (null, null, null);	// Clear any saved values
				var currentZoomLevel = map.getZoom();
				var zoomBy = (((minZoomLevelToSet - currentZoomLevel) <= 2) ? 1 : 2);	// When very zoomed in, zoom in less far, to avoid disorientation
				var newZoomLevel = currentZoomLevel + zoomBy;
				// alert('Current zoom: ' + currentZoomLevel + '; zooming by: ' + zoomBy + ' to: ' + newZoomLevel);
				map.setZoomAround(e.latlng, newZoomLevel);
				return;
			}
			
			// Set the marker
			telluswhere.setMarker(e.latlng, _useIcon, true);
			
			// Remove the help text
			$('#helptext').removeClass('display').addClass('hide');
		},
		
		
		// Wrapper function to set the marker by supplying raw latitude and longitude markers
		setMarkerLatitudeLongitude: function (latitude, longitude)
		{
			var latlng = L.latLng(latitude, longitude);
			map.setView(latlng, _maxZoom);
			telluswhere.setMarker(latlng, _useIcon, true);
		},
		
		
		// Function to set the marker
		setMarker: function (latlng, useIcon, markerIsDraggable, data)
		{
			// In view-only mode, disable marker setting functionality
			if (_viewOnlyMode) {return;}
			
			// Clear any previously-set marker
			if(_marker){
				map.removeLayer(_marker);
			}
			
			// Set marker position
			_marker = new L.Marker(latlng, {icon: _icons[useIcon], draggable: markerIsDraggable, zIndexOffset: 1000});
			map.addLayer(_marker);
			// #!# Need to show the category name
			if (data) {
				var markerHtml = telluswhere.popupHtmlDynamic (data);
			} else {
				var markerHtml = (useIcon == 'suggest' ? 'Needed' : 'Present') + ' here';
			}
			_marker.bindPopup(markerHtml).openPopup();
			
			// Register dragend processing function
			_marker.on('dragend', telluswhere.markerDrag);
			
			// Transmit the value to the form
			telluswhere.setFormValues (latlng.lat, latlng.lng, map.getZoom());
		},
		
		
		// After dragging, transmit the value to the form, and reopen the popup
		markerDrag: function (e)
		{
			telluswhere.setFormValues (e.target._latlng.lat, e.target._latlng.lng, map.getZoom());
			_marker.openPopup();
		},
		
		
		// Function to transmit the location values to the form
		setFormValues: function (lat, lng, zoom)
		{
			if ($('#form_latitude').length > 0) {
				$('#form_latitude').val(lat);
				$('#form_longitude').val(lng);
				$('#form_zoom').val(zoom);
			}
		},
		
		
		// Function to transmit the current location to IDs for external use
		transmitCurrentLocation: function ()
		{
			if ($('#currentMapLocationUrl').length > 0) {
				
				// Determine the map location parameters
				var center = map.getCenter();
				var mapLocationParams = 'latitude=' + center.lat.toFixed(6) + '&longitude=' + center.lng.toFixed(6) + '&zoom=' + map.getZoom();
				
				// Replace the query string entirely
				// #!# Rather crude way of updating the query string; currently assumes no fragment for instance
				var hrefComponents = $('#currentMapLocationUrl').text().split('?', 2);
				var path = hrefComponents[0];	// Part before ?
				var newHref = path + '?' + mapLocationParams;
				
				// Set the new location
				$('#currentMapLocationUrl').text(newHref);
			}
		},
		
		
		
		/* EXIF image marker setting functions */
		
		// Register function for adding to map
		exifCallback: function (exifObject)
		{
			if(_marker){
				map.removeLayer(_marker);
			}
			_geolocationData = telluswhere.extractGeolocationData(exifObject);
			if(_geolocationData) {
				telluswhere.setMarkerLatitudeLongitude(_geolocationData.latitude, _geolocationData.longitude);
			} else {
				alert ('Please now set the location on the map.');
			}
			//console.log(exifObject);
		},
		
		
		// Function to convert the complex EXIF geolocation data structure into standard lat,lon,bearing; see: https://confluence.videoplaza.org/display/BLOG/2012/07/22/Geolocation+data+from+Images
		extractGeolocationData: function (exifObject)
		{
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
		},
		
		
		
		/* Existing locations browsing functions; see: https://chris-osm.blogspot.co.uk/2013/11/using-leaflet-with-database.html */
		
		
		// Newline-to-breaks helper function
		nl2br: function (str, is_xhtml)
		{
			var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
			return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
		},
		
		
		// String truncate function to avoid over-long caption texts causing large bubbles
		truncateString: function (str, length)
		{
			return (str.length > length ? str.substring(0, length - 3) + '...' : str);
		},
		
		
		// Define HTML to be used in the popup
		popupHtml: function (properties)
		{
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation' || _action == 'priorityareas') {
				return telluswhere.popupHtmlDynamic (properties);
			} else {
				return telluswhere.popupHtmlFixed (properties);
			}
		},
		
		
		// Popup which creates a table with images (and images) dynamically
		popupHtmlDynamic: function (properties)
		{
			// Create a variable to hold the editing URL
			var editUrl = null;
			
			// Create a simple key/value pair HTML table dynamically
			// Code based on Leaflet.LayerViewer.js
			var html = '<table>';
			var fieldLabel;
			$.each (properties, function (key, value) {
				
				// Skip if value is an array/object
				if ($.type (value) === 'array')  {return; /* i.e. continue */}
				if ($.type (value) === 'object') {return; /* i.e. continue */}
				
				// If the label is null, hide the row
				if (_popupLabels) {
					if (key in _popupLabels) {
						if (_popupLabels[key] == null) {
							return;	/* i.e. continue */
						}
					}
				}
				
				// Key
				fieldLabel = key;
				if (_popupLabels) {
					if (_popupLabels[key]) {
						fieldLabel = _popupLabels[key];
					}
				}
				fieldLabel = telluswhere.htmlspecialchars (fieldLabel);
				
				// Value
				if (value === null) {
					value = '[null]';
				}
				if (typeof value == 'string') {
					value = value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
				}
				
				// Link to ID
				if (key == 'id') {
					if (_action == 'auditlocation') {	// Do not link on edit page itself
						value = telluswhere.htmlspecialchars (value);
					} else {
						editUrl = _baseUrl + '/audit/location/' + telluswhere.htmlspecialchars (value) + '/';
						value = '<a href="' + editUrl + '">' + telluswhere.htmlspecialchars (value) + '</a>';
					}
				}
				
				// Value conversions
				if (value == 'TRUE') {
					value = '&#10004;';
				}
				
				// Compile the HTML
				html += '<tr><td>' + fieldLabel + ':</td><td><strong>' + value + '</strong></td></tr>';
			});
			html += '</table>';
			
			// Add images if enabled
			if (properties.images) {
				$.each (properties.images, function (index, imageUrl) {
					html += '<div class="imagecontainer">';
					html += '<a href="' + imageUrl + '" target="_blank"><img src="' + imageUrl + '&size=140" width="140" /></a> ';
					html += '</div>';
				});
			}
			
			// For audit location, add link to editing page
			if (_action == 'audit' || _action == 'auditadd') {
				if (editUrl) {
					html += '<p><a href="' + editUrl + '" name="action">Edit</a></p>';
				}
			}
			
			// Return HTML
			return html;
		},
		
		
		// Popup with fixed fields
		popupHtmlFixed: function (properties)
		{
			// Determine whether to show the Like facility
			var enableLike = (properties.metacategoryId == 'bad');
			
			// Determine if the user has Liked the location, by reading the cookie
			if (enableLike) {
				var isLiked = telluswhere.isLiked (properties.id);
			}
			
			var html = ''
			+ '<div class="bubble">'
			
			// Caption and ID; if nodeId exists then this is a location from OSM and so is fixed, read-only data
			+ '<p class="id">'
			+ (properties.nodeId
				? '<a href="' + 'https://www.openstreetmap.org' + '/node/' + properties.nodeId + '/" target="_blank">' + '(From OpenStreetMap)' + '</a>'
				: '<a href="' + _baseUrl + '/location/' + properties.id + '/">#' + properties.id + '</a>'
			)
			+ '</p>'
			
			// Like button
			+ (enableLike ? 
				  '<div id="likes"' + (isLiked ? ' class="liked"' : '') + '><p>'
				+ '	<a href="' + _baseUrl + '/location/' + properties.id + '/like/"><img src="/images/icons/thumb_up.png" class="icon" />'
				+ ' <span id="likestext">' + (isLiked ? 'Agreed!' : 'Agree?') + '</span>'
				+ '</a>'
				+ '	<span id="likescurrent">' + (properties.likes > 0 ? properties.likes : '') + '</span>'
				+ '</p></div>'
			: '')
			
			//  Caption
			+ (properties.caption ? '<p class="caption">' + (properties.nodeId ? 'Cycle parking is present here.' : telluswhere.nl2br (telluswhere.truncateString (properties.caption, 200),true)) + '</p>' : '')
			
			// Image
			+ (properties.hasPhoto ? '<img src="' + properties.thumbnailUrl + '" alt="Image" />' : '')
			
			// #!# Currently hardcoded field lists and labels:
			
			// Internal data (packed as JSON)
			+ (properties.additionalMetadata ? 
				  '<table class="lines compressed">'
				+ (typeof properties.additionalMetadata.landtype !== 'undefined' ? '<tr><td>Land type:</td><td>' + telluswhere.nl2br(properties.additionalMetadata.landtype) + '</td></tr>' : '')
				+ (typeof properties.additionalMetadata.type !== 'undefined' ? '<tr><td>Type:</td><td>' + telluswhere.nl2br(properties.additionalMetadata.type) + '</td></tr>' : '')
				+ (typeof properties.additionalMetadata.capacity !== 'undefined' ? '<tr><td>Capacity:</td><td>' + properties.additionalMetadata.capacity + '</td></tr>' : '')
				+ '</table>'
			  : '')
			
			// OSM tags
			+ (typeof properties.osmTags !== 'undefined' ? 
				  '<p>Current cycle parking:</p>'
				+ '<table class="lines compressed">'
				+ (typeof properties.osmTags.bicycle_parking !== 'undefined' ? '<tr><td>Type:</td><td>' + telluswhere.nl2br(properties.osmTags.bicycle_parking) + '</td></tr>' : '')
				+ (typeof properties.osmTags.capacity !== 'undefined' ? '<tr><td>Capacity:</td><td>' + properties.osmTags.capacity + '</td></tr>' : '')
				+ (typeof properties.osmTags.covered !== 'undefined' ? '<tr><td>Covered:</td><td>' + properties.osmTags.covered + '</td></tr>' : '')
				+ '</table>'
			  : '')
			
			+ '</div>';
			
			// If on the current page, provide a link to report problems
			if (!_viewOnlyMode) {
				if(_useIcon == 'current') {
					html += '<p class="problem"><a href="#" data-id="' + (properties.nodeId ? properties.nodeId : properties.id) + '">Updates or repairs required?</a></p>';
				}
			}
			
			// Return HTML
			return html;
		},
		
		
		// Function to determine whether a location is liked
		isLiked: function (id)
		{
			// Read the cookie
			var cookieValue = telluswhere.readCookie('photomap-like');
			if (cookieValue) {
				cookieValue = decodeURIComponent(cookieValue);
				
				// Split as token, IDs
				var cookieValueComponents = cookieValue.split(':');
				if (cookieValueComponents.length == 2) {
					
					// Split IDs to array
					var cookieValueLiked = cookieValueComponents[1].split(',');
					if (cookieValueLiked.length > 0) {
						
						// See if the the supplied ID (as string) is in the list
						if ($.inArray (id.toString(), cookieValueLiked) > -1) {	// https://api.jquery.com/jQuery.inArray/
							return true;
						}
					}
				}
			}
			
			// Not found or not liked
			return false;
		},
		
		
		// Filter to control visibility of items set with setIcon
		setIconFilter: function (feature, layer)
		{
			// If an item is selected, skip, as this will already be on the map
			if (_selectedId) {
				var id = parseInt(feature.properties.id, 10);	// base 10
				if (id == _selectedId) {
					return false;
				}
			}
			
			// Show icon by default
			return true;
		},
		
		
		// Inner function to fetch current marker data
		showCurrentDataLayer: function (ajaxResponse, layerIndex)
		{
			// Remove all markers, except those with open popups
			var popup;
			_currentDataLayers[layerIndex].eachLayer (function (layer) {
				popup = layer.getPopup ();
				if (!popup.isOpen ()) {
					_currentDataLayers[layerIndex].removeLayer (layer);
				}
			});
			
			// Add the data
			_currentDataLayers[layerIndex].addData (ajaxResponse);
			
			// Markers with opened popups remain - this brings the old ones back on top
			// Note: the previous markers are still there underneath - put are probably benign
			_currentDataLayers[layerIndex].eachLayer (function (layer) {
				popup = layer.getPopup ();
				if (!popup.isOpen ()) {
					_currentDataLayers[layerIndex].bringToFront (layer);
				}
			});
		},
		
		
		// Function to determine requirement for IE<=9 to use JSONP instead of JSON; see: https://stackoverflow.com/a/19562445/180733
		useJsonpTransport: function ()
		{
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
		},
		
		
		// Wrapper function to fetch current marker data layer/layers
		getData: function ()
		{
			// For the audit layer, require a close zoom before loading due to the volume of data
			// #!# Needs turning into a database setting if future datasets
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation') {
				if (map.getZoom() < 16) {
					return;
				}
			}
			
			// Get each data layer
			$.each (_browsingApiUrls, function (index, url) {
				telluswhere.getDataLayer (url, index);
			});
		},
		
		
		// Inner function to fetch current marker data
		getDataLayer: function (url, layerIndex)
		{
			// Start spinner, initially adding it to the page
			if (layerIndex == 0) {	// main
				if (!$('#map #loading').length) {
					$('#map').append('<img id="loading" src="' + _baseUrl + '/images/spinner.svg" />');
				}
				$('#map #loading').show();
			}
			
			// Get the data
			var data = 'bbox=' + map.getBounds().toBBoxString();
			$.ajax ({
				url: url,
				dataType: (_useJsonpTransport ? 'jsonp' : 'json'),
				crossDomain: true,	// Needed for IE<=9; see: https://stackoverflow.com/a/12644252/180733
				data: data,
				success: function (ajaxResponse) {
					telluswhere.showCurrentDataLayer (ajaxResponse, layerIndex);
					
					// Remove spinner
					if (layerIndex == 0) {	// main
						$('#map #loading').hide();
					}
				}
			});
		},
		
		
		// Define mapmove action
		whenMapMoves: function (e)
		{
			// Transmit current location
			telluswhere.transmitCurrentLocation();
			
			// Get data
			telluswhere.getData();
		},
		
		
		// Function run when clicking on the problem link to provide a mini correction updates form
		problemForm: function ()
		{
			// If the link is clicked, replace the popup content
			$('p.problem a').click(function(e){
				
				// Create a form
				var formHtml = $("<form />", {name: 'problem', id: 'problem', method: 'POST', action: _baseUrl + '/location/' + $('p.problem a').data('id') + '/problem/'});
				
				// Add input fields to the form
				var formContentHtml = '';
				formContentHtml += '<input type="hidden" name="id" value="' + $('p.problem a').data('id') + '" autofocus="autofocus" />';
				formContentHtml += '<p>What is the issue with this entry?</p>';
				formContentHtml += '<textarea name="message" required="required"></textarea>';
				formContentHtml += '<p>In case we need to contact you for more info, what is your e-mail address?</p>';
				formContentHtml += '<input type="email" name="email" required="required" />';
				formContentHtml += '<p><input type="submit" id="submit" value="Submit" /></p>';
				formHtml.append(formContentHtml);
				
				// Replace the popup content with the form
				$('.leaflet-popup-content').html(formHtml);
				
				// Submit the form via AJAX
				var ajaxform = $('#problem');
				ajaxform.submit(function (e) {
					
					// Determine if form not complete, showing any error
					var thisFormOk = telluswhere.formOk('#problem', e);
					
					// Submit the form if no problem detected; based on: https://stackoverflow.com/questions/1960240/jquery-ajax-submit-form
					if (thisFormOk) {
						$.ajax({
							type: ajaxform.attr('method'),
							url: ajaxform.attr('action'),
							data: ajaxform.serialize(),
							success: function (data) {
								$('.leaflet-popup-content').html('<p>' + data.response + '</p>');
							},
							error: function (xhr, status, error) {
								var data = JSON.parse(xhr.responseText);
								$('.leaflet-popup-content').html('<p>' + data.response + '</p>');
							}
						});
						e.preventDefault();
					}
				});
				
				// Prevent link click taking effect
				e.preventDefault();
			});
		},
		
		
		// Function to check the form is complete; based on: http://toddmotto.com/progressively-enhancing-html5-forms-creating-a-required-attribute-fallback-with-jquery/
		formOk: function (formId, e)
		{
			// Do feature detection of 'required' support
			var supportsRequired = 'required' in document.createElement('input');
			
			// Swap 'required' attribute with a class 'required', as non-HTML5 browsers do not see the required attribute
			$(formId + ' [required]').each(function () {
				if (!supportsRequired) {
					var self = $(this);
					self.removeAttr('required').addClass('required');
					//self.parent().append('<span class="form-error">Required</span>');
				}
			});
			
			// Loop through class name required
			var formOk = true;	// No problems at the start
			$(formId + ' .required').each(function () {
				var self = $(this);
				
				// Check shorthand if statement for input[type] detection
				var checked = ((self.is(':checkbox') || self.is(':radio')) 
					? self.is(':not(:checked)') && $('input[name=' + self.attr('name') + ']:checked').length === 0 
					: false);
				
				// Run the empty/not:checked test
				if (self.val() === '' || checked) {
					
					// Show error if the values are empty still (or re-emptied); this will fire after it's already been checked once
					//self.siblings('.form-error').show();
					//self.addClass('required');
					
					// Stop form submitting
					e.preventDefault();
					
					// Register problem
					formOk = false;
					
				// Hide error if passed the check
				} else {
					//self.siblings('.form-error').hide();
				}
			});
			
			// State form problem if not complete
			if (!formOk) {
				if (!$("#formwarning").length){
					$(formId).prepend('<p id="formwarning"></p>');
				}
				$('#formwarning').html('The form is not complete so has not yet been submitted:');
			}
			
			// Return the status
			return formOk;
		},
		
		
		// Function to read map location cookie
		readMapLocationCookie: function ()
		{
			// Read the cookie location
			var location = telluswhere.readCookie ('location');
			
			// If set, parse out the location
			if (location) {
				var locationComponents = location.split ('/');
				_initialZoom = locationComponents[0];
				_initialLatitude = locationComponents[1];
				_initialLongitude = locationComponents[2];
			}
		},
		
		
		// Function to set map location cookie
		setMapLocationCookie: function ()
		{
			// Build the location string, e.g. 17/51.51178/-0.10137
			var zoom = map.getZoom ();
			var centre = map.getCenter ();
			var location = zoom + '/' + centre.lat + '/' + centre.lng;
			
			// Set the cookie
			telluswhere.setCookie ('location', location);
		},
		
		
		// Cookie setting function
		setCookie: function (name, value)
		{
			// Determine the time
			var days = 7;
			var date = new Date();
			date.setTime (date.getTime () + (days * 24 * 60 * 60 * 1000) );
			var expires = '; expires=' + date.toGMTString();
			
			// Set the path component
			var path = '; path=/';
			
			// Compile the cookie string
			document.cookie = name + '=' + value + expires + path;
		},
		
		
		// Cookie reading function; see: https://www.quirksmode.org/js/cookies.html
		readCookie: function (name)
		{
		    var nameEQ = name + "=";
		    var ca = document.cookie.split(';');
		    for(var i=0;i < ca.length;i++) {
		        var c = ca[i];
		        while (c.charAt(0)==' ') c = c.substring(1,c.length);
		        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		    }
		    return null;
		},
		
		
		// Drawing functionality, wrapping Leaflet.draw
		drawing: function (targetField, fragmentOnly, defaultValueString)
		{
			// Options for polygon drawing
			var polygon_options = {
				showArea: false,
				shapeOptions: {
					stroke: true,
					color: 'blue',
					weight: 4,
					opacity: 0.5,
					fill: true,
					fillColor: null, //same as color by default
					fillOpacity: 0.2,
					clickable: true
				}
			};
			
			// Create a map drawing layer
			var drawnItems = new L.FeatureGroup();
			
			// Add default value if supplied; currently only polygon type supplied
			if (defaultValueString) {
				
				// Convert the string to an array of L.latLng(lat,lon) values
				var polygonPoints = JSON.parse(defaultValueString);
				var defaultPolygon = [];
				if (polygonPoints) {
					var i;
					var point;
					for (i = 0; i < polygonPoints.length; i++) {
						point = polygonPoints[i];
						defaultPolygon.push (L.latLng(point[1], point[0]));
					}
				}
				
				// Create the polygon and style it
				var defaultPolygonFeature = L.polygon(defaultPolygon, polygon_options.shapeOptions);
				
				// Create the layer and add the polygon to the layer
				var defaultLayer = new L.layerGroup();
				defaultLayer.addLayer(defaultPolygonFeature);
				
				// Add the layer to the drawing canvas
				drawnItems.addLayer(defaultLayer);
			}
			
			// Add the drawing layer to the map
			map.addLayer(drawnItems);
			
			// Enable the polygon drawing when the button is clicked
			var drawControl = new L.Draw.Polygon(map, polygon_options);
			$('.draw.area').click(function() {
				drawControl.enable();
				
				// Allow only a single polygon at present
				// #!# Remove this when the server-side allows multiple polygons
				drawnItems.clearLayers();
			});
			
			// Handle created polygons
			map.on('draw:created', function (e) {
				var layer = e.layer;
				drawnItems.addLayer(layer);
				
				// Convert to GeoJSON value
				var geojsonValue = drawnItems.toGeoJSON();
				
				// Reduce coordinate accuracy to 6dp (c. 1m) to avoid over-long URLs
				// #!# Ideally this would be native within Leaflet.draw: https://github.com/Leaflet/Leaflet.draw/issues/581
				var coordinates = geojsonValue.features[0].geometry.coordinates[0];
				var accuracy = 6;	// Decimal points; gives 0.1m accuracy; see: https://en.wikipedia.org/wiki/Decimal_degrees
				var i;
				var j;
				for (i = 0; i < coordinates.length; i++) {
					for (j = 0; j < coordinates[i].length; j++) {
						coordinates[i][j] = +coordinates[i][j].toFixed(accuracy);
					}
				}
				geojsonValue.features[0].geometry.coordinates[0] = coordinates;
				
				// If required, send only the coordinates fragment
				if (fragmentOnly) {
					geojsonValue = coordinates;
				}
				
				// Send to receiving input form
				$(targetField).val(JSON.stringify(geojsonValue));
				
				// Trigger jQuery change event, so that .change() behaves as expected for the hidden field; see: https://stackoverflow.com/a/8965804
				// #!# Note that this fires twice for some reason - see notes to the answer in the above URL
				$(targetField).trigger('change');
			});
			
			// Cancel button clears drawn polygon and clears the form value
			$('.edit-clear').click(function() {
				drawnItems.clearLayers();
				$(targetField).val('');
			
				// Trigger jQuery change event, so that .change() behaves as expected for the hidden field; see: https://stackoverflow.com/a/8965804
				$(targetField).trigger('change');
			});
			
			// Undo button
			$('.edit-undo').click(function() {
				drawnItems.revertLayers();
			});
		},
		
		
		// Function to make data entity-safe
		htmlspecialchars: function (string)
		{
			if (typeof string !== 'string') {return string;}
			return string.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		}
	};
	
})(jQuery);
