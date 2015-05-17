<?php

/**
 * Convert latitude/longitude <=> OS National Grid Reference points (c) Chris Veness 2005-2010
 * @link http://www.movable-type.co.uk/scripts/latlong-gridref.html
 *
 * Note: the conversions happen between OSGB 36, rather than WGS84 to convert useL
 * http://www.movable-type.co.uk/scripts/latlong-convert-coords.html
 */

class osLonLat
{
	/**
	 * Convert geodesic co-ordinates to OS grid reference
	 *@param float $pLatDeg Latitude in degrees according to the OSGB-36 datum.
	 *@param float $pLatDeg Longitude in degrees according to the OSGB-36 datum.
	 * @return string
	 */
	public static function LatLongToOSGrid ($pLatDeg, $pLonDeg)
	{
		$coordinate = array ('lon' => $pLonDeg, 'lat' => $pLatDeg, 'northing' => 0, 'easting' => 0);

		self::LatLongToEastingNorthing ($coordinate);
    
		return self::gridrefNumToLet($coordinate['easting'], $coordinate['northing'], 8);
	}

	/**
	 * Convert geodesic co-ordinates to OS grid reference
	 * @param array with input fields lat,lon in degrees according to the OSGB-36 datum. Sets output fields: easting, northing.
	 * @return void
	 */
	public static function LatLongToEastingNorthing (&$coordinate)
	{
		$pLatDeg = $coordinate['lat'];
		$pLonDeg = $coordinate['lon'];

		$lon = deg2rad($pLonDeg);
		$lat = deg2rad($pLatDeg);
    
    
		$a = 6377563.396;
		$b = 6356256.910;          // Airy 1830 major & minor semi-axes
		$F0 = 0.9996012717;                         // NatGrid scale factor on central meridian
		$lat0 = deg2rad(49);
		$lon0 = deg2rad(-2);  // NatGrid true origin
		$N0 = -100000; $E0 = 400000;                 // northing & easting of true origin, metres
		$e2 = 1 - ($b * $b)/($a * $a);               // eccentricity squared
		$n = ($a - $b)/($a + $b);
		$n2 = $n * $n;
		$n3 = $n2 * $n;
    
		$cosLat = cos($lat);
		$sinLat = sin($lat);
		$nu = $a * $F0/sqrt(1 - $e2 * $sinLat * $sinLat);              // transverse radius of curvature
		$rho = $a * $F0 * (1 - $e2)/pow(1 - $e2 * $sinLat * $sinLat, 1.5);  // meridional radius of curvature
		$eta2 = $nu / $rho - 1;
    
		$Ma = (1 + $n + (5/4) * $n2 + (5/4) * $n3) * ($lat - $lat0);
		$Mb = (3 * $n + 3 * $n2 + (21/8) * $n3) * sin($lat - $lat0) * cos($lat + $lat0);
		$Mc = ((15/8) * $n2 + (15/8) * $n3) * sin(2*($lat - $lat0)) * cos(2*($lat + $lat0));
		$Md = (35/24) * $n3 * sin(3 * ($lat - $lat0)) * cos(3 * ($lat + $lat0));
		$M = $b * $F0 * ($Ma - $Mb + $Mc - $Md);              // meridional arc
    
		$cos3lat = $cosLat * $cosLat * $cosLat;
		$cos5lat = $cos3lat * $cosLat * $cosLat;
		$tan2lat = tan($lat) * tan($lat);
		$tan4lat = $tan2lat * $tan2lat;
    
		$I = $M + $N0;
		$II = ($nu / 2) * $sinLat * $cosLat;
		$III = ($nu / 24) * $sinLat * $cos3lat * (5 - $tan2lat + 9 * $eta2);
		$IIIA = ($nu/720) * $sinLat * $cos5lat * (61 - 58 * $tan2lat + $tan4lat);
		$IV = $nu * $cosLat;
		$V = ($nu / 6) * $cos3lat * ($nu / $rho - $tan2lat);
		$VI = ($nu / 120) * $cos5lat * (5 - 18 * $tan2lat + $tan4lat + 14 * $eta2 - 58 * $tan2lat * $eta2);
    
		$dLon = $lon - $lon0;
		$dLon2 = $dLon * $dLon;
		$dLon3 = $dLon2 * $dLon;
		$dLon4 = $dLon3 * $dLon;
		$dLon5 = $dLon4 * $dLon;
		$dLon6 = $dLon5 * $dLon;
    
		$N = $I + $II * $dLon2 + $III * $dLon4 + $IIIA * $dLon6;
		$E = $E0 + $IV * $dLon + $V * $dLon3 + $VI * $dLon5;
    
		$coordinate['easting']  = $E;
		$coordinate['northing'] = $N;
	}

	/*
	 * Convert OS grid reference to geodesic co-ordinates.
	 *
	 * @param string Grid reference, e.g. 'SU387148' 
	 * @return array based on WGS84 datum.
	 */
	public static function OSGridToLatLong ($gridRef)
	{
		// Establish the easting and northing
		if (!$gr = self::gridRef2EastingNorthing($gridRef)) {return false;}
    
		// Convert into OSGB 36
		$osgb36 = self::EastingNorthingToLatLong($gr[0], $gr[1]);

		// Setup input
		$input['lat'] = $osgb36[0];
		$input['lon'] = $osgb36[1];
		$input['height'] = 0;

		// Setup output
		$output = array ('lat' => 0, 'lon' => 0, 'height' => 0);

		// Convert to WGS84 Lon Lat
		self::convertOSGB36toWGS84 ($output, $input);

		// Return array
		return $output;
	}

	/*
	 * Convert OS grid reference to geodesic co-ordinates.
	 *
	 * @param float Easting
	 * @param float Northing
	 * @return array (latitude,longitude) on OSGB-36 datum.
	 */
	public static function EastingNorthingToLatLong ($E, $N)
	{
		$a = 6377563.396;
		$b = 6356256.910;              // Airy 1830 major & minor semi-axes
    
		$F0 = 0.9996012717;                             // NatGrid scale factor on central meridian
		$lat0 = 49 * M_PI / 180;
		$lon0 = -2 * M_PI / 180;  // NatGrid true origin
		$N0 = -100000;
		$E0 = 400000;                     // northing & easting of true origin, metres
		$e2 = 1 - ($b * $b)/($a * $a);                          // eccentricity squared
		$n = ($a - $b)/($a + $b);
		$n2 = $n * $n;
		$n3 = $n * $n2;
    
		$lat = $lat0;
		$M=0;
    
		$iterate = true;
    
		while ($iterate)  // ie until < 0.01mm
			{
				$lat = ($N - $N0 - $M)/($a * $F0) + $lat;
	
				$Ma = (1 + $n + (5 / 4) * $n2 + (5 / 4) * $n3) * ($lat - $lat0);
				$Mb = (3 * $n + 3 * $n2 + (21 / 8) * $n3) * sin($lat - $lat0) * cos($lat + $lat0);
				$Mc = ((15 / 8) * $n2 + (15/8) * $n3) * sin(2 * ($lat - $lat0)) * cos(2 * ($lat + $lat0));
				$Md = (35 / 24) * $n3 * sin(3 * ($lat - $lat0)) * cos(3 * ($lat + $lat0));
				$M = $b * $F0 * ($Ma - $Mb + $Mc - $Md);                // meridional arc
	
				$iterate = ($N - $N0 - $M >= 0.00001);
			}
    
		$cosLat = cos($lat);
		$sinLat = sin($lat);
		$nu = $a * $F0 / sqrt(1 - $e2 * $sinLat * $sinLat);              // transverse radius of curvature
		$rho = $a * $F0 * (1 - $e2) / pow(1 - $e2 * $sinLat* $sinLat, 1.5);  // meridional radius of curvature
		$eta2 = $nu / $rho - 1;
    
		$tanLat = tan($lat);
		$tan2lat = $tanLat * $tanLat;
		$tan4lat = $tan2lat * $tan2lat;
		$tan6lat = $tan4lat * $tan2lat;
		$secLat = 1 / $cosLat;
		$nu3 = $nu * $nu * $nu;
		$nu5 = $nu3 * $nu * $nu;
		$nu7 = $nu5 * $nu * $nu;
		$VII = $tanLat / (2 * $rho * $nu);
		$VIII = $tanLat / (24 * $rho * $nu3) * (5 + 3 * $tan2lat + $eta2 - 9 * $tan2lat * $eta2);
		$IX = $tanLat / (720 * $rho * $nu5) * (61 + 90 * $tan2lat + 45 * $tan4lat);
		$X = $secLat / $nu;
		$XI = $secLat / (6 * $nu3) * ($nu / $rho + 2 * $tan2lat);
		$XII = $secLat / (120 * $nu5) * (5 + 28 * $tan2lat + 24 * $tan4lat);
		$XIIA = $secLat / (5040 * $nu7) * (61 + 662 * $tan2lat + 1320 * $tan4lat + 720 * $tan6lat);
    
		$dE = ($E - $E0);
		$dE2 = $dE * $dE;
		$dE3 = $dE2 * $dE;
		$dE4 = $dE2 * $dE2;
		$dE5 = $dE3 * $dE2;
		$dE6 = $dE4 * $dE2;
		$dE7 = $dE5 * $dE2;
		$lat = $lat - $VII * $dE2 + $VIII * $dE4 - $IX * $dE6;
		$lon = $lon0 + $X * $dE - $XI * $dE3 + $XII * $dE5 - $XIIA * $dE7;
    
		return array(rad2deg($lat), rad2deg($lon));
	}

	/* 
	 * Convert standard grid reference ('SU387148') to fully numeric ref ([438700,114800])
     * Note that northern-most grid squares will give 7-digit northings.
	 *
	 * @return array|false Co-ordinates are in metres, centred on grid square for conversion to lat/lon, false on failure.
	 */
    public static function gridRef2EastingNorthing ($gridref)
    {
		// Check whether the input looks like a valid grid reference. This should be two letters followed by 6, 8 or 10 digits.
		// OS grid layout has four 500Km identifiers (H, N, S & T), and there is no 'I' (upper case i) in the second position.
		if (!preg_match ('/([HNST]+)([A-HJ-Z]+)([0-9]{6,10})/', $gridref, $matches)) {return false;}

		// Bind the digits part
		$digits = $matches[3];

		// The number of digits must be even
		if (strlen ($digits) % 2 != 0) {return false;}

		// Get numeric values of letter references, mapping A->0, B->1, C->2, etc:
		$l1 = ord($matches[1]) - ord('A');
		$l2 = ord($matches[2]) - ord('A');

		// Shuffle down letters after 'I' (upper case i) which are not used in OS grid references
		if ($l1 > 7) {$l1--;}
		if ($l2 > 7) {$l2--;}

		// Convert grid letters into 100km-square indexes from the OS origin which is grid square SV000000
		$easting100km  = (($l1 - 2) % 5) * 5 + ($l2 % 5);
		$northing100km = (19 - floor($l1/ 5) * 5) - floor($l2 / 5);

		// Number of digits per co-ordinate
		$coordDigits = strlen ($digits) / 2;

		// How much to scale each of the co-ordinates. Five figure co-ordinates are already at metre-level precision.
		switch ($coordDigits) {
		case 5: $scaler = 1; break;
		case 4: $scaler = 10; break;
		case 3: $scaler = 100; break;
		default: return false;
		}

		// Calculate the easting and northing in metres
		$easting  = $easting100km  * 100000 + substr ($digits, 0, $coordDigits) * $scaler;
		$northing = $northing100km * 100000 + substr ($digits, $coordDigits) * $scaler;

		// Return the results as an array
		return array ($easting, $northing);
    }
    
    /*
     * convert numeric grid reference (in metres) to standard-form grid ref
     */
    private static function gridrefNumToLet($e, $n, $digits)
    {
		// get the 100km-grid indices
		$e100k = floor($e / 100000);
		$n100k = floor($n / 100000);
  
		if ($e100k < 0 || $e100k > 6 || $n100k<0 || $n100k > 12) return '';

		// translate those into numeric equivalents of the grid letters
		$l1 = (19 - $n100k) - (19 - $n100k) % 5 + floor(($e100k + 10) / 5);
		$l2 = (19 - $n100k) * 5 % 25 + $e100k % 5;

		// compensate for skipped 'I' and build grid letter-pairs
		if ($l1 > 7) $l1++;
		if ($l2 > 7) $l2++;
		$letPair = chr($l1 + ord('A')) . chr($l2 + ord('A'));# String.fromCharCode(l1+'A'.charCodeAt(0), l2+'A'.charCodeAt(0));

		// strip 100km-grid indices from easting & northing, and reduce precision
		$e = floor(($e % 100000) / pow(10, 5 - $digits / 2));
		$n = floor(($n % 100000) / pow(10, 5 - $digits / 2));

		// Prepend the letter pair with the desired number of digits.
		$halfTheDigits = floor($digits / 2);
		$gridRef = $letPair . sprintf("%0{$halfTheDigits}d", $e) . sprintf("%0{$halfTheDigits}d", $n);

		return $gridRef;
	}

	/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

	/*
	 * extend Number object with methods for converting degrees/radians
	 */
	/*
	  Number.prototype.toRad = function() {  // convert degrees to radians
	  return this * Math.PI / 180;
	  }
	  Number.prototype.toDeg = function() {  // convert radians to degrees (signed)
	  return this * 180 / Math.PI;
	  }
	*/
    /*
     * pad a number with sufficient leading zeros to make it w chars wide
     */
	/*
	  Number.prototype.padLZ = function(w) {
	  var n = this.toString();
	  for (var i=0; i<w-n.length; i++) n = '0' + n;
	  return n;
	  }
	*/
	/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */



    // The functions below are from:
    // http://www.movable-type.co.uk/scripts/latlong-convert-coords.html

    // ellipse parameters
    // Note: for some reason PHP doesn't like 1/x here, so have substituted the computed value for the 'f' parameter.
    private static $e = array('WGS84' => array('a' => 6378137,     'b' => 6356752.3142, 'f' => 0.00335281), # 1/298.257223563),
							  'Airy1830' => array('a' => 6377563.396, 'b' => 6356256.910,  'f' => 0.00334085));# 1/299.3249646));

    // helmert transform parameters
    private static $h = array('WGS84toOSGB36' => array('tx' => -446.448,  'ty' =>  125.157,   'tz' => -542.060,   // m
													   'rx' =>   -0.1502, 'ry' =>   -0.2470,  'rz' =>   -0.8421,  // sec
													   's' =>    20.4894),                               // ppm
							  'OSGB36toWGS84' => array('tx' =>  446.448,  'ty' => -125.157,   'tz' =>  542.060,
													   'rx' =>    0.1502, 'ry' =>    0.2470,  'rz' =>    0.8421,
													   's' =>   -20.4894));
    /**
	 * @param array $result By providing this array with fields lat,lon,height garbage creation can be avoided.
     * @param array $p1 with fields 'lat', 'lon', and 'height'
	 * @return void
     */
    public static function convertOSGB36toWGS84 (&$result, $p1)
	{
		self::convert($result, $p1, self::$e['Airy1830'], self::$h['OSGB36toWGS84'], self::$e['WGS84']);
    }
    
    /**
	 * @param array $result By providing this array with fields lat,lon,height garbage creation can be avoided.
     * @param array $p1 with fields 'lat', 'lon', and 'height'
	 * @return void
     */
    public static function convertWGS84toOSGB36 (&$result, $p1)
	{
		self::convert($result, $p1, self::$e['WGS84'], self::$h['WGS84toOSGB36'], self::$e['Airy1830']);
    }

    /**
     * Convert polar to cartesian coordinates (using ellipse 1)
	 * @param array $result By providing this array with fields lat,lon,height garbage creation can be avoided.
	 * @return void
     */
    private static function convert (&$result, $p1, $e1, $t, $e2)
    {
		$p1['lat'] = deg2rad($p1['lat']);
		$p1['lon'] = deg2rad($p1['lon']); 

		$a = $e1['a'];
		$b = $e1['b'];

		$sinPhi = sin($p1['lat']);
		$cosPhi = cos($p1['lat']);
		$sinLambda = sin($p1['lon']);
		$cosLambda = cos($p1['lon']);
		$H = $p1['height'];

		$eSq = ($a * $a - $b * $b) / ($a * $a);
		$nu = $a / sqrt(1 - $eSq * $sinPhi * $sinPhi);

		$x1 = ($nu + $H) * $cosPhi * $cosLambda;
		$y1 = ($nu + $H) * $cosPhi * $sinLambda;
		$z1 = ((1 - $eSq) * $nu + $H) * $sinPhi;


		// -- apply helmert transform using appropriate params
		$tx = $t['tx']; $ty = $t['ty']; $tz = $t['tz'];
		$rx = $t['rx']/3600 * M_PI/180;  // normalise seconds to radians
		$ry = $t['ry']/3600 * M_PI/180;
		$rz = $t['rz']/3600 * M_PI/180;
		$s1 = $t['s']/1e6 + 1;              // normalise ppm to (s+1)

		// apply transform
		$x2 = $tx + $x1 * $s1 - $y1 * $rz + $z1 * $ry;
		$y2 = $ty + $x1 * $rz + $y1 * $s1 - $z1 * $rx;
		$z2 = $tz - $x1 * $ry + $y1 * $rx + $z1 * $s1;

		// -- convert cartesian to polar coordinates (using ellipse 2)

		$a = $e2['a']; $b = $e2['b'];
		$precision = 4 / $a;  // results accurate to around 4 metres

		$eSq = ($a * $a - $b * $b) / ($a * $a);
		$p = sqrt($x2 * $x2 + $y2 * $y2);
		$phi = atan2($z2, $p * (1 - $eSq)); $phiP = 2 * M_PI;
		while (abs($phi - $phiP) > $precision) {
			$nu = $a / sqrt(1 - $eSq * sin($phi) * sin($phi));
			$phiP = $phi;
			$phi = atan2($z2 + $eSq * $nu * sin($phi), $p);
		}
		$lambda = atan2($y2, $x2);
		$H = $p / cos($phi) - $nu;

		// Build result
		$result['lat'] = rad2deg($phi);
		$result['lon'] = rad2deg($lambda);
		$result['height'] = $H;
    }


    // ---- the following are duplicated from LatLong.html ---- //


	/*
	 * construct a LatLon object: arguments in numeric degrees & metres
	 *
	 * note all LatLong methods expect & return numeric degrees (for lat/long & for bearings)
	 */
	/*
	  function LatLon(lat, lon, height) {
	  if (arguments.length < 3) height = 0;
	  this.lat = lat;
	  this.lon = lon;
	  this.height = height;
	  }
	*/
	/*
	 * represent point {lat, lon} in standard representation
	 */
	/*
	  LatLon.prototype.toString = function() {
	  return this.lat.toLat() + ', ' + this.lon.toLon();
	  }
	*/

	// extend String object with method for parsing degrees or lat/long values to numeric degrees
	//
	// this is very flexible on formats, allowing signed decimal degrees, or deg-min-sec suffixed by 
	// compass direction (NSEW). A variety of separators are accepted (eg 3º 37' 09"W) or fixed-width 
	// format without separators (eg 0033709W). Seconds and minutes may be omitted. (Minimal validation 
	// is done).
	/*
	  String.prototype.parseDeg = function() {
	  if (!isNaN(this)) return Number(this);                 // signed decimal degrees without NSEW

	  $degLL = this.replace(/^-/,'').replace(/[NSEW]/i,'');  // strip off any sign or compass dir'n
	  $dms = degLL.split(/[^0-9.]+/);                     // split out separate d/m/s
	  for ($i in dms) if (dms[i]=='') dms.splice(i,1);    // remove empty elements (see note below)
	  switch (dms.length) {                                  // convert to decimal degrees...
	  case 3:                                              // interpret 3-part result as d/m/s
      $deg = dms[0]/1 + dms[1]/60 + dms[2]/3600; break;
	  case 2:                                              // interpret 2-part result as d/m
      $deg = dms[0]/1 + dms[1]/60; break;
	  case 1:                                              // decimal or non-separated dddmmss
      if (/[NS]/i.test(this)) degLL = '0' + degLL;       // - normalise N/S to 3-digit degrees
      $deg = dms[0].slice(0,3)/1 + dms[0].slice(3,5)/60 + dms[0].slice(5)/3600; break;
	  default: return NaN;
	  }
	  if (/^-/.test(this) || /[WS]/i.test(this)) deg = -deg; // take '-', west and south as -ve
	  return deg;
	  }
	*/
	// note: whitespace at start/end will split() into empty elements (except in IE)


	// extend Number object with methods for converting degrees/radians
	/*
	  Number.prototype.toRad = function() {  // convert degrees to radians
	  return this * M_PI / 180;
	  }

	  Number.prototype.toDeg = function() {  // convert radians to degrees (signed)
	  return this * 180 / M_PI;
	  }
	*/
	// extend Number object with methods for presenting bearings & lat/longs
	/*
	  Number.prototype.toDMS = function(dp) {  // convert numeric degrees to deg/min/sec
	  if (arguments.length < 1) dp = 0;      // if no decimal places argument, round to int seconds
	  $d = abs(this);  // (unsigned result ready for appending compass dir'n)
	  $deg = floor(d);
	  $min = floor((d-deg)*60);
	  $sec = ((d-deg-min/60)*3600).toFixed(dp);
	  // fix any nonsensical rounding-up
	  if (sec==60) { sec = (0).toFixed(dp); min++; }
	  if (min==60) { min = 0; deg++; }
	  if (deg==360) deg = 0;
	  // add leading zeros if required
	  if (deg<100) deg = '0' + deg; if (deg<10) deg = '0' + deg;
	  if (min<10) min = '0' + min;
	  if (sec<10) sec = '0' + sec;
	  return deg + '\u00B0' + min + '\u2032' + sec + '\u2033';
	  }

	  Number.prototype.toLat = function(dp) {  // convert numeric degrees to deg/min/sec latitude
	  return this.toDMS(dp).slice(1) + (this<0 ? 'S' : 'N');  // knock off initial '0' for lat!
	  }

	  Number.prototype.toLon = function(dp) {  // convert numeric degrees to deg/min/sec longitude
	  return this.toDMS(dp) + (this>0 ? 'E' : 'W');
	  }
	*/

}
