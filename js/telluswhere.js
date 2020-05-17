// Telluswhere javascript module

/*jslint browser: true, white: true, single: true, for: true */
/*global $, jQuery, L, autocomplete, alert, confirm, console, window */

var telluswhere = (function ($) {
	'use strict';
	
	
	// Settings defaults
	var _settings = {
		
		// baseUrl of application
		baseUrl: false,
		
		// Initial map location
		initialLatitude: false,
		initialLongitude: false,
		initialGeometry: false,
		initialZoom: false,
		enableInitialCookieLocation: true,
		
		// Max/min zoom
		maxZoom: 21,
		minZoom: 7,
		
		// Data API endpoint(s)
		browsingApiUrl: false,
		browsingApiUrl2: false,
		
		// The icon to use
		useIcon: false,
		
		// Whether to set a marker initially
		setMarkerInitially: false,
		markerSetInitiallyIsDraggable: false,
		markerData: false,
		markerSettingZoom: 19,
		
		// Selected ID, if any, and whether it is moveable
		selectedId: false,
		
		// View-only mode
		viewOnlyMode: false,
		
		// Drawing
		enableDrawing: false,
		
		// Popup labelling
		popupLabels: {},
		
		// Line styling
		lineSize: {initial: 8, hover: 15},
		lineColour: {initial: 'red', reviewed: 'green'},
		
		// Icon sizing based on Likes
		iconSizeLikesScaling: [
			[1, 1.2],
			[3, 1.5],
			[5, 2],
			[20, 3]
		],
		
		// Browse request limitations
		limitToTag: false,
		since: false,
		
		// Tiles
		tileUrl: false,
		tileOpacity: false,
		
		// CycleStreets API key
		apiKey: false,
		
		// Geocoder
		apiBaseUrl: false,
		geocoderBboxBounded: false
	};
	
	/* Class properties */
	
	// Internal class properties
	var map;	// Should be _map but makes pasting from leaflet examples (which always use map) easier
	var _marker;
	var _icons;
	var _useJsonpTransport;
	var _currentDataLayers = {};
	var _geolocationData;
	var _minZoomLevelToSet;
	
	
	// Login status
	var _user = false;
	
	// GUI action
	var _action;
	
	// The API endpoint(s) to use for browsing
	var _browsingApiUrls = {};
	
	
	return {
		
// Public functions
		
		// Main function
		initialise: function (config, run, action, user)
		{
			// Merge the configuration into the settings
			$.each (_settings, function (setting, value) {
				if (config.hasOwnProperty(setting)) {
					_settings[setting] = config[setting];
				}
			});
			
			// Set browsing API URLs
			if (_settings.browsingApiUrl) {
				_browsingApiUrls[0] = _settings.browsingApiUrl;
			}
			if (_settings.browsingApiUrl2) {
				_browsingApiUrls[1] = _settings.browsingApiUrl2;
			}
			
			// Set class properties
			_action = action;
			_user = user;
			
			// Start map creation
			telluswhere[run] (config, action, user);
		},
		
		
		// Main function
		createMap: function (config, action, user)
		{
			// Enable tooltips for titles
			if (jQuery.ui) {	// If jQuery UI loaded
				$('#selectcategory').tooltip ({
					track: true
				});
			}
			
			// Determine the initial map location, using cookie location if present
			var initialMapLocation = telluswhere.getInitialLocation ();
			
			// Get hash before map loading, which will adjust the hash with lat/lon/zoom
			_initialHash = window.location.hash.substr(1);	// substr(1) removes #
			
			// Set map centre location
			map = L.map('map', {maxBounds: [[61, 9],[49, -11]]});
			map.setView([initialMapLocation.latitude, initialMapLocation.longitude], initialMapLocation.zoom);
			
			// Set tile layer
			var tileAttribution = 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors (<a href="https://www.openstreetmap.org/copyright">ODbL</a>)';
			L.tileLayer (_settings.tileUrl, {
				attribution: tileAttribution,
				maxZoom: _settings.maxZoom,
				minZoom: _settings.minZoom,
				opacity: _settings.tileOpacity
			}).addTo(map);
			
			// Add hash
			new L.Hash (map);
			
			// Add geolocation control; see: https://github.com/domoritz/leaflet-locatecontrol
			map.addControl (L.control.locate ({
				icon: 'fa fa-location-arrow',
				setView: 'once',	// The default, 'untilPanOrZoom', can reduce battery heavily
				locateOptions: {maxZoom: 16}
			}));
			
			// Add geocoder
			telluswhere.geocoder ();
			
			// If there is a custom geolocate button, pass the click on to the main button; see: https://stackoverflow.com/questions/23016863/ and https://github.com/domoritz/leaflet-locatecontrol/issues/205#issuecomment-530096560
			if ($('div.geolocate-button').length) {
				// $('.leaflet-control-locate').hide();		// Done in the CSS instead, as enables mobile/desktop differences
				$('.geolocate-button a').click( function(e) {
					$('.fa-location-arrow').click();	// Simulate click of icon
					e.preventDefault();
				});
			}
			
			// Set cookie on map move
			map.on ('moveend', function (e) {
				telluswhere.setMapLocationCookie ();
			});
			
			// Transmit current location
			telluswhere.transmitCurrentLocation();
			
			// Define the icon set; see: https://leafletjs.com/examples/custom-icons/
			_icons = telluswhere.getIcons();
			
			// Determine whether to set the marker initially
			if (_settings.setMarkerInitially){
				
				var latlng = L.latLng (_settings.initialLatitude, _settings.initialLongitude);
				map.setView (latlng, _settings.markerSettingZoom);
				
				// If an initial geometry is set, prefer that
				if (_settings.initialGeometry && _settings.initialGeometry.type == 'LineString') {
					telluswhere.setGeometry (_settings.initialGeometry, _settings.markerSetInitiallyIsDraggable, _settings.markerData);
				} else {
					telluswhere.setMarker (latlng, _settings.markerSetInitiallyIsDraggable, _settings.markerData);
				}
			}
			
			// Determine the minimum zoom level for marker setting
			_minZoomLevelToSet = telluswhere.minZoomLevelToSet ();
			
			// If zoomed out, add a zoom-in cursor
			telluswhere.cursorZoomin ();
			map.on('zoomend', function() {
				telluswhere.cursorZoomin ();
			});
			
			// Enable either drawing or map point setting
			if (_settings.enableDrawing) {
				var formElement = (_action == 'priorityareas' ? '#geometry' : '#form_location');
				var fragmentOnly = (_action == 'priorityareas');	// #!# Aim to remove this legacy handling
				telluswhere.drawing (formElement, _settings.enableDrawing /* i.e. type */, _settings.initialGeometry, true, fragmentOnly);
			} else {
				map.on('click', telluswhere.onMapClick);
			}
			
			// Add each data layer to the map, if enabled
			$.each (_browsingApiUrls, function (index, url) {
				_currentDataLayers[index] = L.geoJson (null /* added later instead, using .addData */ , {
					
					// Filter, to skip existing selected
					filter: telluswhere.setIconFilter,
					
					// Style points - create a marker
					pointToLayer: function (feature, latlng) {
						var icon = _icons['already'];
						if (feature.properties.iconUrl) {
							icon = _icons['_dynamic'];
							icon.options.iconUrl = feature.properties.iconUrl;
						}
						
						// Special case for trf_cushi
						// #!# Need to be made generic
						if (feature.properties.trf_cushi && feature.properties.trf_cushi == 'TRUE') {
							icon.options.iconUrl = feature.properties.iconUrl.replace(/bad/g, 'good');
						}
						
						// Add class if required to enable opacity styling for deleted items
						icon.options.className = null;
						if (feature.properties._status == 'deleted') {
							icon.options.className = 'deleted';
						}
						
						// If there are likes, make the icon larger progressively
						icon = telluswhere.iconSizeLikes (icon, feature.properties.likes);
						
						// Return the marker
						return L.marker (latlng, {icon: icon});
					},
					
					// Add interactions
					onEachFeature: function (feature, layer) {
						
						// Remove internal colour field if present
						if (feature.properties.hasOwnProperty ('_colour')) {
							delete feature.properties._colour;
						}
						
						// For auditing, add class for reviewed/unreviewed
						var className = null;
						if (_action == 'audit') {
							className = (feature.properties._status == 'initial' ? 'unreviewed' : 'reviewed');
						}
						
						// Determine the latlng of the centre
						var centre = null;
						if (feature.geometry.type == 'Polygon') {
							centre = layer.getBounds().getCenter();
						}
						
						// Add popups
						layer.bindPopup (telluswhere.popupHtml (feature.properties, index, centre), {
							'className': className,
							autoPanPaddingTopLeft: [0, 50],			// 50px from top
							autoPanPaddingBottomRight: [55, 0]		// 55px from right
						});
						
						// Add hover styles; see: https://leafletjs.com/examples/choropleth/
						layer.on ({
							mouseover: function (e) {
								var layer = e.target;
								if (e.target.feature.geometry.type == 'Point') {
									// #!# Change to making icon larger instead
									layer.setOpacity (0.7);
								}
								if (e.target.feature.geometry.type == 'LineString' || e.target.feature.geometry.type == 'MultiLineString') {
									layer.setStyle({
										color: telluswhere.lineColour (e.target.feature.properties),
										weight: _settings.lineSize.hover
									});
								}
							},
							mouseout: function (e) {
								var layer = e.target;
								if (e.target.feature.geometry.type == 'Point') {
									// #!# Should actually reset rather than set to 1 - icons may already be grayed-out when gone
									layer.setOpacity (1);
								}
								if (e.target.feature.geometry.type == 'LineString' || e.target.feature.geometry.type == 'MultiLineString') {
									layer.setStyle({
										color: telluswhere.lineColour (e.target.feature.properties),
										weight: _settings.lineSize.initial
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
							styles.color = telluswhere.lineColour (feature.properties);
							styles.weight = _settings.lineSize.initial;
						}
						
						// Polygons
						if (feature.geometry.type == 'Polygon') {
							if (feature.properties.hasOwnProperty ('_colour')) {
								styles.color = feature.properties._colour;
								styles.fillColor = feature.properties._colour;
							} else {
								styles.color = _settings.lineColour.initial;
								styles.fillColor = _settings.lineColour.initial;
							}
						}
						
						// Return the styles
						return styles;
					}
				});
				
				_currentDataLayers[index].addTo(map);
			});
			
			// Add support for Like clicks
			telluswhere.liking ();
			
			// If a map setting indicator is present, on click, scroll to the map on mobile
			if ($('.mapsetting').length) {
				var browserWidth = $(window).width ();
				if (browserWidth < 768) {
					$('.mapsetting').on ('click', function () {
						$('html, body').animate ({
							scrollTop: 0
						}, 400);
					});
				}
			}
			
			// If setting a category is supported, move to the caption box on setting
			if ($('form input[name="form\\[category\\]"]').length && $('form #form_caption').length) {
				$('form input[name="form\\[category\\]"]').on ('click', function () {
					$('form #form_caption').focus ();
				});
			}
			
			// For audit location, add link to editing page
			if (_action == 'audit') {
				telluswhere.auditUnchanged ();
			}
			
			// For priority areas polygons browsing in zoomed-out mode, enable zoom in
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation') {
				$('#map').on ('click', 'a.priorityareaszoom', function (e) {
					var centre = e.target.dataset;
					map.setView([centre.lat, centre.lng], centre.zoom);
					map.closePopup ();
					e.preventDefault ();
				});
			}
			
			// On priority areas page, enable deletion
			if (_action == 'priorityareas') {
				telluswhere.priorityareasDeletion ();
			}
			
			// Determine whether to use JSONP transport instead of JSON for the marker layer calls (for older browsers)
			_useJsonpTransport = telluswhere.useJsonpTransport();
			
			// Register moveend
			map.on('moveend', telluswhere.whenMapMoves);
			
			// Get the data on initial view
			telluswhere.getData();
			
			// Register reporting link function
			if (_settings.useIcon == 'current') {
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
			
			// Export
			telluswhere.export ();
			
			// Return map
			return map;
		},
		
		
		// Liking
		liking: function ()
		{
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
		},
		
		
		// Audit page unchanged location handler
		auditUnchanged: function ()
		{
			$('#map').on('click', 'a#auditunchanged', function (e) {
				
				// Assemble the data, obtaining the ID
				var data = {
					id: $(this).data('id')
				};
				
				// If not logged in, convert the button to a login requirement
				if (!_user) {
					$('p.auditbuttons' + data.id).html ('<p style="color: red;">Please <a href="' + _settings.baseUrl + '/login/?/audit/">log in</a> or <a href="' + _settings.baseUrl + '/register/">register</a> first.</p>');
					e.preventDefault ();
					return;		// End
				}
				
				// Show confirmation first
				if (confirm ('Confirm - are all the details of this location, as shown above, correct?')) {
					
					// Send the AJAX request and handle the response
					$.ajax ({
						type: 'POST',
						url: _settings.baseUrl + '/ajax/auditunchanged',
						data: data,
						dataType: 'json',
						success: function (response) {
							
							// Hide the popup button for this ID
							$('p.auditbuttons' + data.id).html ('<p style="color: green;">✓ This location has now been reviewed - thank you!</p>');
							
							// Update the points
							$('span.badge, span.profile a').text (response.points + ' points');
							$('span.badge, span.profile a').fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100);	// https://stackoverflow.com/questions/275931/
							
							// Update the icon/line to green, by forcing map move of zero position change to result in new AJAX request
							map.panTo (map.getCenter());
						},
						error: function (xhr, status, error) {
							var data = $.parseJSON(xhr.responseText);
							/*vex.dialog.*/alert (data.error);
						}
					});
				}
				e.preventDefault ();	// Don't follow link
			});
		},
		
		
		// On priority areas page, enable deletion
		priorityareasDeletion: function ()
		{
			$('#map').on ('click', 'a.priorityareasdelete', function (e) {
				if (confirm ('Are you sure?')) {
					
					// Asssemble the data
					var data = {
						id: $(this).data('id')
					};
					
					// Send the AJAX request and handle the response
					$.ajax({
						type: 'POST',
						url: '/ajax/priorityareasdelete',
						data: data,
						dataType: 'json',
						success: function (response) {
							
							// Move the map to delete the polygon and refresh state
							map.closePopup();
							map.panTo (map.getCenter());
						},
						error: function (xhr, status, error) {
							var data = $.parseJSON(xhr.responseText);
							/*vex.dialog.*/alert (data.error);
						}
					});
					e.preventDefault ();
				}
			});
		},
		
		
		// Function to set the icon size, as adjusted by likes
		iconSizeLikes: function (icon, likes)
		{
			// Determine scale
			var scale = 1;
			if (likes) {
				$.each (_settings.iconSizeLikesScaling, function (index, scaleFactor) {
					if (likes >= scaleFactor[0]) {
						scale = scaleFactor[1];
					} // continue until end
				});
			}
			
			// Set the icon size; this must be done for every icon, even if not scaling
			icon.options.iconSize = [
				icon.options.__proto__.iconSize[0] * scale,
				icon.options.__proto__.iconSize[1] * scale
			];
			
			// Return the icon
			return icon;
		},
		
		
		lineColour: function (properties)
		{
			// If there is a properties status field, and it is not initial, return the reviewed style
			if (properties._status) {
				if (properties._status != 'initial') {
					return _settings.lineColour.reviewed;
				}
			}
			
			// Otherwise return the default style
			return _settings.lineColour.initial;
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
			
			// Zoom if too far out and end
			if (map.getZoom() < _minZoomLevelToSet) {
				telluswhere.setPointFormValues (null, null, null);	// Clear any saved values
				var currentZoomLevel = map.getZoom();
				var zoomBy = (((_minZoomLevelToSet - currentZoomLevel) <= 2) ? 1 : 2);	// When very zoomed in, zoom in less far, to avoid disorientation
				var newZoomLevel = currentZoomLevel + zoomBy;
				// alert('Current zoom: ' + currentZoomLevel + '; zooming by: ' + zoomBy + ' to: ' + newZoomLevel);
				map.setZoomAround(e.latlng, newZoomLevel);
				return;
			}
			
			// Set the marker
			telluswhere.setMarker (e.latlng, true);
			
			// Remove the help text
			$('#helptext').removeClass('display').addClass('hide');
		},
		
		
		// Function to determine the minimum zoom level for marker setting
		minZoomLevelToSet: function ()
		{
			// If drawing is enabled, reduce the zoom level
			if (_settings.enableDrawing) {
				return 14;
			}
			
			// For the audit layer, require a close zoom before loading due to the volume of data
			// #!# Needs turning into a database setting if future datasets
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation') {
				return 17;
			}
			
			// Default minimum zoom level to set
			return 16;
			
		},
		
		
		// Function to set the cursor to zoom-in; see: https://stackoverflow.com/questions/14106687/
		cursorZoomin: function ()
		{
			if (map.getZoom() < _minZoomLevelToSet) {
				L.DomUtil.addClass (map._container, 'cursorzoomin');
			} else {
				L.DomUtil.removeClass (map._container, 'cursorzoomin');
			}
		},
		
		
		// Wrapper function to set the marker by supplying raw latitude and longitude markers
		setMarkerLatitudeLongitude: function (latitude, longitude)
		{
			var latlng = L.latLng (latitude, longitude);
			map.setView(latlng, _settings.maxZoom);
			telluswhere.setMarker (latlng, true);
		},
		
		
		// Function to set geometry, i.e. line rather than marker
		setGeometry: function (initialGeometry, markerIsDraggable, data)
		{
			// Add the feature
			L.geoJSON (initialGeometry).addTo (map);
		},
		
		
		// Function to set the marker
		setMarker: function (latlng, markerIsDraggable, data)
		{
			// In view-only mode, disable marker setting functionality
			if (_settings.viewOnlyMode) {return;}
			
			// If the interface provides a space for a tick box, set this
			if ($('.mapsetting span').length) {
				$('.mapsetting span').text('✓');
			}
			
			// If there is already a marker set, treat the click as a move (as per a drag)
			if (_marker) {
				_marker.setLatLng (latlng);
				telluswhere.setPointFormValues (latlng.lat, latlng.lng, map.getZoom ());
				_marker.openPopup ();
				return;
			}
			
			// Set the icon to use; if an iconUrl is specified in data, use a dynamic icon instead
			var icon = _icons[_settings.useIcon];
			if (data && data.iconUrl) {
				icon = _icons['_dynamic'];
				icon.options.iconUrl = data.iconUrl;
			}
			
			// Set initial marker position
			_marker = new L.Marker (latlng, {icon: icon, draggable: markerIsDraggable, zIndexOffset: 1000});
			map.addLayer(_marker);
			
			// Set the popup content
			var popupHtml;
			if (data) {
				popupHtml = telluswhere.popupHtmlDynamic (data);
			} else {
				// #!# Need to show the category name
				popupHtml = (_action == 'suggest' ? 'Improvement needed' : 'Present') + ' here';
			}
			_marker.bindPopup (popupHtml, {className: _action}).openPopup ();
			
			// After dragging, transmit the value to the form, and reopen the popup
			_marker.on ('dragend', function (e) {
				telluswhere.setPointFormValues (e.target._latlng.lat, e.target._latlng.lng, map.getZoom ());
				_marker.openPopup();
			});
			
			// Transmit the value to the form
			telluswhere.setPointFormValues (latlng.lat, latlng.lng, map.getZoom ());
		},
		
		
		// Function to transmit the location values for a point location to the form
		// LineString features are handled by .drawing() with targetField also as #form_location
		setPointFormValues: function (lat, lng, zoom)
		{
			// Legacy separate lat/lon/zoom values
			if ($('#form_latitude').length > 0) {
				$('#form_latitude').val(lat);
				$('#form_longitude').val(lng);
				$('#form_zoom').val(zoom);
			}
			
			// Geometry value
			if ($('#form_location').length > 0) {
				if (lat == null && lng == null) {
					$('#form_location').val ('');
				} else {
					var geometry = {
						type: 'Point',
						coordinates: [parseFloat(lng.toFixed(6)), parseFloat(lat.toFixed(6))]
					};
					geometry = JSON.stringify (geometry);
					$('#form_location').val (geometry);
				}
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
			var geolocationData = [];
			geolocationData['latitude'] = latitude;
			geolocationData['longitude'] = longitude;
			
			// Return the object
			return geolocationData;
		},
		
		
		
		/* Existing locations browsing functions; see: https://chris-osm.blogspot.co.uk/2013/11/using-leaflet-with-database.html */
		
		
		// Newline-to-breaks helper function
		nl2br: function (str, is_xhtml)
		{
			var breakTag = ((is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>');
			return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
		},
		
		
		// String truncate function to avoid over-long caption texts causing large bubbles
		truncateString: function (str, length)
		{
			return (str.length > length ? str.substring(0, length - 3) + '...' : str);
		},
		
		
		// Define HTML to be used in the popup
		popupHtml: function (properties, layerIndex, centre)
		{
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation' || _action == 'priorityareas') {
				return telluswhere.popupHtmlDynamic (properties, layerIndex, centre);
			} else {
				return telluswhere.popupHtmlFixed (properties);
			}
		},
		
		
		// Popup which creates a table with images (and images) dynamically
		popupHtmlDynamic: function (properties, layerIndex, centre)
		{
			// Create a variable to hold the editing URL
			var editUrl = null;
			
			// Create a simple key/value pair HTML table dynamically
			// Code based on Leaflet.LayerViewer.js
			var html = '<table class="popupproperties">';
			var fieldLabel;
			$.each (properties, function (key, value) {
				
				// Skip if value is an array/object
				if ($.type (value) === 'array')  {return; /* i.e. continue */}
				if ($.type (value) === 'object') {return; /* i.e. continue */}
				
				// If the label is null, hide the row
				if (_settings.popupLabels) {
					if (_settings.popupLabels.hasOwnProperty ('key')) {
						if (_settings.popupLabels[key] == null) {
							return;	/* i.e. continue */
						}
					}
				}
				
				// Key
				fieldLabel = key;
				if (_settings.popupLabels) {
					if (_settings.popupLabels[key]) {
						fieldLabel = _settings.popupLabels[key];
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
					fieldLabel = 'Location #';
					if (_action == 'auditlocation') {	// Do not link on edit page itself
						value = telluswhere.htmlspecialchars (value);
					} else {
						editUrl = _settings.baseUrl + '/audit/location/' + telluswhere.htmlspecialchars (value) + '/';
						value = '<a href="' + editUrl + '">' + telluswhere.htmlspecialchars (value) + '</a>';
					}
				}
				
				// Value conversions
				if (value == 'TRUE') {
					value = '&#10004;';
				}
				
				// Clarifications
				if (fieldLabel == 'Provision') {value += ' stands';}
				if (fieldLabel == 'Capacity') {value += ' cycles';}
				
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
			if (_action == 'audit') {
				if (editUrl) {
					
					// Hard-coded overrides
					// #!# Need to be made generic
					if (properties.trf_cushi == 'TRUE') {
						html += '<p style="color: green;">✓ This location does not need to be reviewed.</p>';
					} else
					
					if (properties._status == 'initial') {
						html += '<p class="auditbuttons' + properties.id + '">';
						html += '<a id="auditunchanged" data-id="' + properties.id + '" href="' + editUrl + '#unchanged" class="btn waves-effect waves-light green modal-trigger" name="action"><span>Details</span> all OK <i class="material-icons right">check</i></a> &nbsp; ';
						html += '<a href="' + editUrl + '#update" class="btn waves-effect waves-light" name="action">Edit <i class="material-icons right">build</i></a>';
						html += '</p>';
					} else if (properties._status == 'deleted') {
						html += '<p style="color: green;">✓ This location has been reviewed as being no longer present.</p>';
					} else {
						html += '<p style="color: green;">✓ This location has been reviewed.</p>';
					}
				}
			}
			
			// For priority area polygons, create custom popup
			var priorityAreaPolygons = false;
			if (_action == 'priorityareas') {
				priorityAreaPolygons = true;
			}
			if (_action == 'audit' || _action == 'auditadd') {
				if (layerIndex == 1) {
					priorityAreaPolygons = true;
				}
			}
			if (priorityAreaPolygons) {
				html  = '';		// Reset any current HTML
				html += '<h3>' + telluswhere.htmlspecialchars (properties.name) + '</h3>';
				html += '<p>This area is a particular <strong>priority area</strong>.</p>';
				if (_action == 'audit' || _action == 'auditadd') {
					var dataPointsMinZoom = 17;
					if (map.getZoom() < dataPointsMinZoom) {
						html += '<p>Please zoom in to review locations in this area.</p>';
						html += '<br />';
						html += '<p><a href="#" data-lat="' + centre.lat + '" data-lng="' + centre.lng + '" data-zoom="' + dataPointsMinZoom + '" class="priorityareaszoom btn waves-effect waves-light">Browse this area <i class="material-icons right">zoom_in</i></a></p>';
					} else {
						html += '<p>Please click on an icon or line to review that location in this area.</p>';
						html += '<p>Thanks for your help!</p>';
					}
				}
			}
			
			// For priority areas, add deletion button
			if (_action == 'priorityareas') {
				html += '<p>';
//				html += '<a href="#" class="priorityareaedit btn waves-effect waves-light" name="action" data-id="' + properties.id + '">Edit area <i class="material-icons right">build</i></a> &nbsp; ';
				html += '<a href="#" class="priorityareasdelete btn waves-effect waves-light" name="action" data-id="' + properties.id + '">Delete <i class="material-icons right">build</i></a>';
				html += '</p>';
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
			+ '<div class="bubble' + (properties.hasPhoto ? ' hasphoto' : '') + '">'
			
			// Caption and ID; if nodeId exists then this is a location from OSM and so is fixed, read-only data
			+ '<p class="id">'
			+ (properties.nodeId
				? '<a href="' + 'https://www.openstreetmap.org' + '/node/' + properties.nodeId + '/" target="_blank">' + '(From OpenStreetMap)' + '</a>'
				: '<a href="' + _settings.baseUrl + '/location/' + properties.id + '/">#' + properties.id + '</a>'
			)
			+ '</p>'
			
			// Like button
			+ (enableLike ? 
				  '<div id="likes"' + (isLiked ? ' class="liked"' : '') + '><p>'
				+ '	<a href="' + _settings.baseUrl + '/location/' + properties.id + '/like/"><img src="/images/icons/thumb_up.png" class="icon" />'
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
			if (!_settings.viewOnlyMode) {
				if (_settings.useIcon == 'current') {
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
			if (_settings.selectedId) {
				var id = parseInt(feature.properties.id, 10);	// base 10
				if (id == _settings.selectedId) {
					return false;
				}
			}
			
			// Show icon by default
			return true;
		},
		
		
		// Inner function to fetch current marker data
		showCurrentDataLayer: function (ajaxResponse, layerIndex, clearOnly)
		{
			// Remove all markers, except those with open popups
			var popup;
			_currentDataLayers[layerIndex].eachLayer (function (layer) {
				popup = layer.getPopup ();
				if (!popup.isOpen ()) {
					_currentDataLayers[layerIndex].removeLayer (layer);
				}
			});
			
			// End if only clearing
			if (clearOnly) {return;}
			
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
					if (navigator.appVersion.indexOf('MSIE') != -1) {
						// bah, IE again, lets downgrade version number
						version = parseFloat(navigator.appVersion.split('MSIE')[1]);
					}
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
			// Get each data layer
			$.each (_browsingApiUrls, function (index, url) {
				telluswhere.getDataLayer (url, index);
			});
		},
		
		
		// Inner function to fetch current marker data
		getDataLayer: function (url, layerIndex)
		{
			// For the audit layer, require a close zoom before loading due to the volume of data
			// #!# Needs turning into a database setting if future datasets
			if (_action == 'audit' || _action == 'auditadd' || _action == 'auditaddlocation') {
				if (layerIndex == 0) {	// Main icons layer
					if (map.getZoom() < 17) {
						telluswhere.showCurrentDataLayer (null, layerIndex, true);
						return;
					}
				}
			}
			
			// Add BBOX
			var data = {};
			data.bbox = map.getBounds().toBBoxString();
			
			// Limit to tag if required
			if (_settings.limitToTag) {
				if (_action == 'suggest') {
					data.tags = _settings.limitToTag;	// NB API currently has "only a single tag is supported" limitation
				}
			}
			
			// Limit to date since
			if (_settings.since) {
				if (_action == 'suggest') {
					data.since = (new Date (_settings.since).getTime ())/1000;
				}
			}
			
			// Start spinner, initially adding it to the page
			if (layerIndex == 0) {	// main
				if (!$('#map #loading').length) {
					$('#map').append('<img id="loading" src="' + _settings.baseUrl + '/images/spinner.svg" />');
				}
				$('#map #loading').show();
			}
			
			// Get the data
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
				var formHtml = $("<form />", {name: 'problem', id: 'problem', method: 'POST', action: _settings.baseUrl + '/location/' + $('p.problem a').data('id') + '/problem/'});
				
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
		
		
		// Function to detread map location cookie
		getInitialLocation: function ()
		{
			// Check for a cookie location, unless disabled
			if (_settings.enableInitialCookieLocation) {
				var location = telluswhere.readCookie ('location');
				if (location) {
					var locationComponents = location.split ('/');
					return {
						latitude: locationComponents[1],
						longitude: locationComponents[2],
						zoom: locationComponents[0]
					};
				}
			}
			
			// If not present, use the default initial location
			return {
				latitude: _settings.initialLatitude,
				longitude: _settings.initialLongitude,
				zoom: _settings.initialZoom
			};
		},
		
		
		// Home page functions, including geocoder with redirection to the suggest page
		home: function ()
		{
			// Attach the autocomplete library
			autocomplete.addTo ("input[name='location']", {
				sourceUrl: _settings.apiBaseUrl + '/v2/geocoder?key=' + _settings.apiKey + '&bounded=1&bbox=' + _settings.geocoderBboxBounded,
				select: function (event, ui) {
					var latitude = ui.item.feature.geometry.coordinates[1];
					var longitude = ui.item.feature.geometry.coordinates[0];
					var zoom = 16;		// Acceptable for most types of searches
					var url = '/suggest/#' + zoom + '/' + latitude + '/' + longitude;
					window.location.href = url;
				}
			});
			
			// Add support for geolocation button
			$('.geolocation').click (function () {
				
				// If not supported, treat as link to the map page
				if (!navigator.geolocation) {
					window.location.href = '/map/';
					return;
				}
				
				// Locate the user
				navigator.geolocation.getCurrentPosition (function (position) {
					var targetUrl = '/suggest/#' + '16' + '/' + position.coords.latitude.toFixed(6) + '/' + position.coords.longitude.toFixed(6);
					window.location.href = targetUrl;
				});
			});
		},
		
		
		// Function to provide a geocoder, using the CycleStreet API and autocomplete library
		geocoder: function ()
		{
			// Attach the autocomplete library
			autocomplete.addTo ("input[name='location']", {
				sourceUrl: _settings.apiBaseUrl + '/v2/geocoder?key=' + _settings.apiKey + '&bounded=1&bbox=' + _settings.geocoderBboxBounded,
				select: function (event, ui) {
					var bbox = ui.item.feature.properties.bbox.split(',');
					map.fitBounds([ [bbox[1], bbox[0]], [bbox[3], bbox[2]] ], {maxZoom: 19});	// See: https://leafletjs.com/reference.html#latlngbounds
					event.preventDefault();
				}
			});
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
			var i;
			var c;
		    for (i = 0; i < ca.length; i++) {
		        c = ca[i];
		        while (c.charAt(0) == ' ') {
					c = c.substring (1, c.length);
				}
		        if (c.indexOf(nameEQ) == 0) {
					return c.substring(nameEQ.length,c.length);
				}
		    }
		    return null;
		},
		
		
		// Drawing functionality, wrapping Leaflet.draw
		drawing: function (targetField, geometryType, initialGeometry /* feature */, enableInitially, fragmentOnly)
		{
			// Set to show the drawing UI
			$('#drawing').show ();
			
			// Options for drawing
			var polygon_options = {
				showArea: false,
				shapeOptions: {
					stroke: true,
					color: 'blue',
					weight: 4,
					opacity: 0.5,
					fill: (geometryType == 'Polygon'),
					fillColor: null, //same as color by default
					fillOpacity: 0.2,
					clickable: true
				}
			};
			
			// Create a map drawing layer
			var drawnItems = new L.FeatureGroup();
			
			// Add default value if supplied; currently only polygon type supplied
			if (initialGeometry) {
				
				// Create the polygon and style it
				var defaultPolygonFeature = L.polygon (initialGeometry.coordinates, polygon_options.shapeOptions);
				
				// Create the layer and add the polygon to the layer
				var defaultLayer = new L.layerGroup ();
				defaultLayer.addLayer (defaultPolygonFeature);
				
				// Add the layer to the drawing canvas
				drawnItems.addLayer (defaultLayer);
			}
			
			// Add the drawing layer to the map
			map.addLayer(drawnItems);
			
			// Set the Draw object type
			var drawMethod = geometryType;	// E.g. Polygon
			if (geometryType == 'LineString') {
				drawMethod = 'Polyline';
			}
			
			// Initialise the draw control
			var drawControl = new L.Draw[drawMethod] (map, polygon_options);
			
			// Enable initially if required
			if (enableInitially) {
				drawControl.enable ();
			}
			
			// Enable the polygon drawing when the button is clicked
			$('.draw.area').click(function() {
				drawControl.enable();
				
				// Allow only a single polygon at present
				// #!# Remove this when the server-side allows multiple polygons
				drawnItems.clearLayers();
			});
			
			// Handle created polygons
			map.on ('draw:created', function (e) {
				var layer = e.layer;
				drawnItems.addLayer(layer);
				
				// Convert to GeoJSON value
				var geojsonValue = drawnItems.toGeoJSON();
				
				// Reduce coordinate accuracy to 6dp (c. 1m) to avoid over-long URLs
				// #!# Ideally this would be native within Leaflet.draw: https://github.com/Leaflet/Leaflet.draw/issues/581
				var coordinates = geojsonValue.features[0].geometry.coordinates[0];
				var accuracy = 6;	// Decimal points; gives 0.1m accuracy; see: https://en.wikipedia.org/wiki/Decimal_degrees
				var i;
				if (geometryType == 'LineString') {
					for (i = 0; i < coordinates.length; i++) {
						coordinates[i] = +coordinates[i].toFixed(accuracy);
					}
				}
				if (geometryType == 'Polygon') {
					var j;
					for (i = 0; i < coordinates.length; i++) {
						for (j = 0; j < coordinates[i].length; j++) {
							coordinates[i][j] = +coordinates[i][j].toFixed(accuracy);
						}
					}
				}
				geojsonValue.features[0].geometry.coordinates[0] = coordinates;
				
				// Send the geometry, except in legacy fragmentOnly mode which sends just the co-ordinates
				geojsonValue = geojsonValue.features[0].geometry;
				if (fragmentOnly) {		// Legacy
					geojsonValue = coordinates;
				}
				
				// Send to receiving input form
				$(targetField).val(JSON.stringify(geojsonValue));
				$('#form_zoom').val(map.getZoom ());
				
				// Set the map location cookie
				telluswhere.setMapLocationCookie ();
				
				// Trigger jQuery change event, so that .change() behaves as expected for the hidden field; see: https://stackoverflow.com/a/8965804
				// #!# Note that this fires twice for some reason - see notes to the answer in the above URL
				$(targetField).trigger('change');
				
				// Show the link
				$('.edit-instructions').hide ();
				$('.edit-clear').show ();
			});
			
			// Cancel button clears drawn polygon and clears the form value
			$('.edit-clear').click (function (e) {
				drawnItems.clearLayers();
				$(targetField).val('');
					
				
				// Trigger jQuery change event, so that .change() behaves as expected for the hidden field; see: https://stackoverflow.com/a/8965804
				$(targetField).trigger('change');
				
				// Re-enable drawing
				drawControl.enable();
				
				// Hide the link
				$('.edit-instructions').show ();
				$('.edit-clear').hide ();
				
				// Do not follow the link
				e.preventDefault ();
			});
			
			// Undo button
			$('.edit-undo').click(function() {
				drawnItems.revertLayers();
			});
		},
		
		
		// Function to enable export
		export: function ()
		{
			// Enable if the UI provides export links
			if (!$('#export').length) {return;}
			
			// Get the link targes, whose class states the format
			var linkTargets = $('#export a');
			
			// Define the link help texts
			var helpTexts = {
				csv:		'Data for the visible map area, as a CSV file, which will open in Excel/OpenOffice, and for analysis in programming languages.',
				geojson:	'Data for the visible map area, as a GeoJSON file, suitable for use in a GIS program like QGIS, ArcGIS or MapInfo, or for analysis in R.'
			};
			$.each (linkTargets, function (index, linkTarget) {
				$(linkTarget).attr ('title', helpTexts[linkTarget.className]);
			});
			
			// Adjust links on map move
			map.on ('moveend', function (e) {
				
				// Avoid ever showing the export links on mobile
				var browserWidth = $(window).width ();
				if (browserWidth < 768) {
					return false;
				}
				
				// Limit visibility of link to Local Authority area size, as API export at country-wide scale will give a misleading selection
				if (map.getZoom() >= 13) {
					$('#export').fadeIn (2000);
				} else {
					$('#export').fadeOut (1000);
				}
				
				// Add the link for each format
				$.each (linkTargets, function (index, linkTarget) {
					
					// Set the BBOX
					var parameters = {};
					parameters.bbox = map.getBounds().toBBoxString();
					
					// Limit to date since (using since, as that matches the icons, not earliestDate, which needs to be deprecated)
					if (_settings.since) {
						parameters.since = (new Date (_settings.since).getTime ())/1000;
					}
					
					// Determine export type
					parameters.format = linkTarget.className;
					
					// Assemble the link
					var url = _settings.browsingApiUrl + '&' + $.param (parameters);
					
					// Use default limit
					url = url.replace (/&limit=[0-9]+/, '');
					
					// Remove unnecessary fields from export
					url = url.replace (',iconUrl', '');
					url = url.replace (',metacategoryId', '');
					url = url.replace (',hasPhoto', '');
					
					// Do not request additionalMetadata
					url = url.replace (',additionalMetadata', '');
					
					// Use larger thumbnails
					url = url.replace (/&thumbnailsize=[0-9]+/, '&thumbnailsize=1000');
					
					// Set the href value
					$(linkTarget).attr ('href', url);
					
					// Set to open in a new window, which for CSV will be temporary
					$(linkTarget).attr ('target', '_blank');
				});
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
