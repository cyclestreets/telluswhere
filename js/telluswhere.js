// Telluswhere javascript module
var telluswhere = (function ($) {
	'use strict';
	
	
	/* Class properties */
	
	// Internal class properties
	var map;	// Should be _map but makes pasting from leaflet examples (which always use map) easier
	var _marker;
	var _icons;
	var _useJsonpTransport;
	var _currentDataLayer;
	var _currentDataLayer2;
	var _geolocationData;
	var _maxZoom;
	var _viewOnlyMode;
	
	// baseUrl of application
	var _baseUrl;
	
	// Initial map location
	var _initialLatitude;
	var _initialLongitude;
	var _initialZoom;
	
	// The API endpoint to use for browsing
	var _browsingApiUrl;
	var _browsingApiUrl2;
	
	// The icon to use
	var _useIcon;
	
	// Whether to set a marker initially
	var _setMarkerInitially;
	
	// Selected ID, if any, and whether it is moveable
	var _selectedId;
	
	
	
	return {
		
// Public functions
		
		// Main function
		createMap: function(baseUrl, initialLatitude, initialLongitude, initialZoom, browsingApiUrl, useIcon, setMarkerInitially, markerSetInitiallyIsDraggable, selectedId, browsingApiUrl2, viewOnlyMode, disableGeolocation) {
			
			// Set class properties
			_baseUrl = baseUrl;
			_initialLatitude = initialLatitude;
			_initialLongitude = initialLongitude;
			_initialZoom = initialZoom;
			_browsingApiUrl = browsingApiUrl;
			_browsingApiUrl2 = browsingApiUrl2;
			_useIcon = useIcon;
			_setMarkerInitially = setMarkerInitially;
			_selectedId = selectedId;	// ID of selected item
			_viewOnlyMode = viewOnlyMode;
			
			// Set map centre location
			map = L.map('map').setView([_initialLatitude, _initialLongitude], _initialZoom);
			
			// Set tile layer
			var tileUrl = 'http://{s}.tile.cyclestreets.net/mapnik/{z}/{x}/{y}.png';
			var tileAttribution = 'Map data &copy; <a href=\"http://www.openstreetmap.org/\">OpenStreetMap</a> contributors (<a href=\"http://www.openstreetmap.org/copyright\">ODbL</a>)';
			_maxZoom = 18;
			L.tileLayer(tileUrl, {
				attribution: tileAttribution,
				maxZoom: _maxZoom
			}).addTo(map);
			
			// Transmit current location
			telluswhere.transmitCurrentLocation();
			
			// Define the icon set; see: http://leafletjs.com/examples/custom-icons.html
			_icons = telluswhere.getIcons();
			
			// Geolocate the user on first run
			if(!_setMarkerInitially){
				if (!disableGeolocation) {
					telluswhere.geolocateUser();
				}
			}
			
			// Determine whether to set the marker initially
			if(_setMarkerInitially){
				var latlng = L.latLng(_initialLatitude, _initialLongitude);
				telluswhere.setMarker(latlng, _useIcon, markerSetInitiallyIsDraggable);
				map.setView(latlng,_initialZoom);
			}
			
			// Register click handler
			map.on('click', telluswhere.onMapClick);
			
			// Add the data layer to the map
			_currentDataLayer = L.geoJson(null, {
				pointToLayer: telluswhere.setIcon,
				filter: telluswhere.setIconFilter
			});
			_currentDataLayer.addTo(map);
			
			// Add second data layer to the map if defined
			if(_browsingApiUrl2) {
				_currentDataLayer2 = L.geoJson(null, {
					pointToLayer: telluswhere.setIcon,
				});
				_currentDataLayer2.addTo(map);
			}
			
			// Add support for Like clicks
			$('body').on('click','#likes a', function(event){	// http://stackoverflow.com/a/19133666/180733
				event.preventDefault();
				$.ajax({
					url: $(this).attr('href'),
					success: function(data) {
						$('#likescurrent').text(data.total);
						if(data.liked) {
							$('#likes').addClass('liked');
						} else {
							$('#likes').removeClass('liked');
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
				$('#form_file_0').change(function() {
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
		getIcons: function() {
			
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
				already: new smallIcon({iconUrl: '/images/markers/already.png'})
			};
			
			// Return the icons
			return icons;
		},
		
		
		// Function to geolocate the user
		geolocateUser: function()
		{
			map.locate({setView: true, maxZoom: 18});
		},
		
		
		// Create marker and popup when clicking on the map
		onMapClick: function(e) {
			
			// Show the help text
			$('#helptext').addClass('display');
			
			// Remove any marker present
			if(telluswhere._marker){
				map.removeLayer(telluswhere._marker);
			}
			
			// Define minimum zoom level to set
			var minZoomLevelToSet = 18;
			
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
		setMarkerLatitudeLongitude: function(latitude, longitude) {
			var latlng = L.latLng(latitude, longitude);
			map.setView(latlng, _maxZoom);
			telluswhere.setMarker(latlng, _useIcon, true);
		},
		
		
		// Function to set the marker
		setMarker: function(latlng, useIcon, markerIsDraggable) {
			
			// In view-only mode, disable marker setting functionality
			if (_viewOnlyMode) {return;}
			
			// Clear any previously-set marker
			if(_marker){
				map.removeLayer(_marker);
			}
			
			// Set marker position
			_marker = new L.Marker(latlng, {icon: _icons[useIcon], draggable: markerIsDraggable, zIndexOffset: 1000});
			map.addLayer(_marker);
			_marker.bindPopup('Cycle parking is ' + (useIcon == 'suggest' ? 'needed' : 'present') + ' here').openPopup();
			
			// Register dragend processing function
			_marker.on('dragend', telluswhere.markerDrag);
			
			// Transmit the value to the form
			telluswhere.setFormValues (latlng.lat, latlng.lng, map.getZoom());
		},
		
		
		// After dragging, transmit the value to the form, and reopen the popup
		markerDrag: function(e) {
			telluswhere.setFormValues (e.target._latlng.lat, e.target._latlng.lng, map.getZoom());
			_marker.openPopup();
		},
		
		
		// Function to transmit the location values to the form
		setFormValues: function(lat, lng, zoom) {
			if ($('#form_latitude').length > 0) {
				$('#form_latitude').val(lat);
				$('#form_longitude').val(lng);
				$('#form_zoom').val(zoom);
			}
		},
		
		
		// Function to transmit the current location to IDs for external use
		transmitCurrentLocation: function() {
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
		exifCallback: function(exifObject) {
			if(_marker){
				map.removeLayer(_marker);
			}
			_geolocationData = telluswhere.extractGeolocationData(exifObject);
			if(_geolocationData) {
				telluswhere.setMarkerLatitudeLongitude(_geolocationData.latitude, _geolocationData.longitude);
			}
			//console.log(exifObject);
		},
		
		
		// Function to convert the complex EXIF geolocation data structure into standard lat,lon,bearing; see: https://confluence.videoplaza.org/display/BLOG/2012/07/22/Geolocation+data+from+Images
		extractGeolocationData: function(exifObject) {
			
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
		
		
		
		/* Existing locations browsing functions; see: http://chris-osm.blogspot.co.uk/2013/11/using-leaflet-with-database.html */
		
		
		// Newline-to-breaks helper function
		nl2br: function(str, is_xhtml) {
			var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
			return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
		},
		
		
		// String truncate function to avoid over-long caption texts causing large bubbles
		truncateString: function(str, length) {
			return (str.length > length ? str.substring(0, length - 3) + '...' : str);
		},
		
		
		// Define HTML to be used in the popup
		popupHtml: function(properties) {
			
			// Determine whether to show the Like facility
			var enableLike = (properties.metacategoryId == 'bad');
			
			// Determine if the user has Liked the location
			if (enableLike) {
				var isLiked = false;
				var cookieValue = telluswhere.readCookie('photomap-like-' + properties.id);
				if (cookieValue) {
					cookieValue = decodeURIComponent(cookieValue);
					if (cookieValue.split(':')[1] == '1') {
						isLiked = true;
					}
				}
			}
			
			var html = ''
			+ '<div class="bubble">'
			
			// Caption and ID; if nodeId exists then this is a location from OSM and so is fixed, read-only data
			+ '<p class="id">'
			+ (properties.nodeId
				? '<a href="' + 'http://www.openstreetmap.org' + '/node/' + properties.nodeId + '/" target="_blank">' + '(From OpenStreetMap)' + '</a>'
				: '<a href="' + _baseUrl + '/location/' + properties.id + '/">#' + properties.id + '</a>'
			)
			+ '</p>'
			
			// Like button
			+ (enableLike ? 
				  '<div id="likes"' + (isLiked ? ' class="liked"' : '') + '>'
				+ '	<a href="' + _baseUrl + '/location/' + properties.id + '/like/"><img src="/images/icons/thumb_up.png" class="icon" /></a>'
				+ '	<span id="likescurrent">' + properties.likes + '</span>'
				+ '</div>'
			: '')
			
			//  Caption
			+ '<p class="caption">' + (properties.nodeId ? 'Cycle parking is present here.' : telluswhere.nl2br(telluswhere.truncateString(properties.caption, 200),true)) + '</p>'
			
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
		
		
		// Function to set the marker and attach a popup
		setIcon: function(feature,latlng) {
			
			// Create a marker and bind the popup to it
			var marker = L.marker(latlng, {icon: _icons['already']});
			marker.bindPopup(telluswhere.popupHtml(feature.properties));
			
			// Return the marker
			return marker;
		},
		
		
		// Filter to control visibility of items set with setIcon
		setIconFilter: function(feature,layer) {
		
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
		
		
		// Show data layer (wrapper to implementation function)
		showCurrentData: function(ajaxResponse) {
			telluswhere.showCurrentDataLayer (ajaxResponse, _currentDataLayer);
		},
		
		
		// Show second data layer (wrapper to implementation function)
		showCurrentData2: function(ajaxResponse) {
			telluswhere.showCurrentDataLayer (ajaxResponse, _currentDataLayer2);
		},
		
		
		// Inner function to fetch current marker data
		showCurrentDataLayer: function(ajaxResponse, selectedLayer) {
			
			// Remove all markers except those with open popups
			selectedLayer.eachLayer (function (layer) {if (!layer._popup._isOpen) {selectedLayer.removeLayer (layer);}});

			// Add the data
			selectedLayer.addData (ajaxResponse);

			// Markers with opened popups remain - this brings the old ones back on top
			// Note: the previous markers are still there underneath - put are probably benign.
			selectedLayer.eachLayer (function (layer) {if (layer._popup._isOpen) { selectedLayer.bringToFront (layer);}});
		},
		
		
		// Function to determine requirement for IE<=9 to use JSONP instead of JSON; see: http://stackoverflow.com/a/19562445/180733
		useJsonpTransport: function() {
			
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
		getData: function() {
			
			// Get data layer (pass to implementation function)
			telluswhere.getDataLayer(_browsingApiUrl, telluswhere.showCurrentData);
			
			// Get second data layer if defined
			if(_browsingApiUrl2) {
				telluswhere.getDataLayer(_browsingApiUrl2, telluswhere.showCurrentData2);
			}
		},
		
		
		// Inner function to fetch current marker data
		getDataLayer: function (browsingApiUrl, successFunction) {
			var data='bbox=' + map.getBounds().toBBoxString();
			$.ajax({
				url: browsingApiUrl,
				dataType: (_useJsonpTransport ? 'jsonp' : 'json'),
				crossDomain: true,	// Needed for IE<=9; see: http://stackoverflow.com/a/12644252/180733
				data: data,
				success: successFunction
			});
		},
		
		
		// Define mapmove action
		whenMapMoves: function(e) {
			
			// Transmit current location
			telluswhere.transmitCurrentLocation();
			
			// Get data
			telluswhere.getData();
		},
		
		
		// Function run when clicking on the problem link to provide a mini correction updates form
		problemForm: function () {
			
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
					
					// Submit the form if no problem detected; based on: http://stackoverflow.com/questions/1960240/jquery-ajax-submit-form
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
		formOk: function (formId, e){
			
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
		
		
		// Cookie reading function; see: http://www.quirksmode.org/js/cookies.html
		readCookie: function(name) {
		    var nameEQ = name + "=";
		    var ca = document.cookie.split(';');
		    for(var i=0;i < ca.length;i++) {
		        var c = ca[i];
		        while (c.charAt(0)==' ') c = c.substring(1,c.length);
		        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		    }
		    return null;
		}
	};
	
})(jQuery);
