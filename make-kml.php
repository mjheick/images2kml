<?php

/* This makes a huge-ass kml, sorted by year/month folders */

$kml_filename = 'awesome.kml';
$max_image_dims = 500;
$database = []; /* where we store years, months, and days, and filenames */

$files = scandir('.'); /* Natural ascending sort */
foreach ($files as $file) {
	if (($file == '.') || ($file == '..')) {
		continue;
	}
	if (!is_file($file)) {
		continue;
	}
	if (strpos($file, '.json') === false) {
		continue;
	}
	$data = json_decode(file_get_contents($file), true);
	if ($data === null) {
		continue;
	}
	echo "$file: ";
	if ((strlen($data['lat']) == 0) || (strlen($data['long']) == 0)) {
		echo "no gps\n";
		continue;
	}

	/* Parse out the date into ymd and make subarrays */
	$year = substr($data['date'], 0, 4);
	$month = substr($data['date'], 5, 2);
	$day = substr($data['date'], 8, 2);
	if (!array_key_exists($year, $database)) {
		$database[$year] = []; /* create the array */
	}
	if (!array_key_exists($month, $database[$year])) {
		$database[$year][$month] = [];
	}
	if (!array_key_exists($day, $database[$year][$month])) {
		$database[$year][$month][$day] = [];
	}
	/* Add the file to the array so we can do the needful next iteration around */
	$database[$year][$month][$day][] = $file;
	echo "ok\n";
}

$kml = fopen($kml_filename, 'wt');
/* make header */
//fwrite($kml, '' . "\n");
fwrite($kml, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
fwrite($kml, '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n");
fwrite($kml, '<Document>' . "\n");
fwrite($kml, "\t" . '<name>' . $kml_filename . '</name>' . "\n");
fwrite($kml, "\t" . '<open>1</open>' . "\n");
fwrite($kml, "\t" . '<StyleMap id="m_ylw-pushpin1"><Pair><key>normal</key><styleUrl>#s_ylw-pushpin1</styleUrl></Pair><Pair><key>highlight</key><styleUrl>#s_ylw-pushpin_hl1</styleUrl></Pair></StyleMap><Style id="s_ylw-pushpin1"><IconStyle><scale>1.1</scale><Icon><href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href></Icon><hotSpot x="20" y="2" xunits="pixels" yunits="pixels"/></IconStyle></Style><Style id="s_ylw-pushpin_hl1"><IconStyle><scale>1.3</scale><Icon><href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href></Icon><hotSpot x="20" y="2" xunits="pixels" yunits="pixels"/></IconStyle></Style>' . "\n");

/* Lets do our year/month/day subfolders */
foreach ($database as $year => $months) {
	fwrite($kml, "\t" . '<Folder>' . "\n");
	fwrite($kml, "\t\t" . '<name>' . $year . '</name>' . "\n");
	fwrite($kml, "\t\t" . '<open>0</open>' . "\n");
	/* Every folder gets 0/0/5000 as a LookAt */
	fwrite($kml, "\t\t" . '<LookAt><longitude>0.0</longitude><latitude>0.0</latitude><altitude>5000</altitude><heading>0</heading><tilt>0</tilt><range>0.1</range><gx:altitudeMode>relativeToSeaFloor</gx:altitudeMode></LookAt>' . "\n");
	/* Make the months */
	foreach ($database[$year] as $month => $days) {
		fwrite($kml, "\t\t" . '<Folder>' . "\n");
		fwrite($kml, "\t\t\t" . '<name>' . $month . '</name>' . "\n");
		fwrite($kml, "\t\t\t" . '<open>0</open>' . "\n");
		fwrite($kml, "\t\t\t" . '<LookAt><longitude>0.0</longitude><latitude>0.0</latitude><altitude>5000</altitude><heading>0</heading><tilt>0</tilt><range>0.1</range><gx:altitudeMode>relativeToSeaFloor</gx:altitudeMode></LookAt>' . "\n");
		/* Finally, the days */
		foreach ($database[$year][$month] as $day => $files) {
			fwrite($kml, "\t\t\t" . '<Folder>' . "\n");
			fwrite($kml, "\t\t\t\t" . '<name>' . $day . '</name>' . "\n");
			fwrite($kml, "\t\t\t\t" . '<open>0</open>' . "\n");
			fwrite($kml, "\t\t\t\t" . '<LookAt><longitude>0.0</longitude><latitude>0.0</latitude><altitude>5000</altitude><heading>0</heading><tilt>0</tilt><range>0.1</range><gx:altitudeMode>relativeToSeaFloor</gx:altitudeMode></LookAt>' . "\n");
			/* List the files with their respective placemarks and data */
			foreach ($files as $file) {
				echo "> $file\n";
				$data = json_decode(file_get_contents($file), true);
				$gps_lat = substr($data['lat'], 0, strlen($data['lat']) - 1);
				$gps_long = substr($data['long'], 0, strlen($data['long']) - 1);
				if (strtoupper(substr($data['lat'], strlen($data['lat']) - 1, 1)) == 'S') {
					$gps_lat = '-' . $gps_lat;
				}
				if (strtoupper(substr($data['long'], strlen($data['long']) - 1, 1)) == 'W') {
					$gps_long = '-' . $gps_long;
				}

				/* get the image data */
				$jpeg_data = '<img style="max-width:500px;" src="' . $kml_filename . '_' . $data['filename'] . '" />';
				if (!file_exists($kml_filename . '_' . $data['filename'])) {
					copy($data['filename'], $kml_filename . '_' . $data['filename']);
				}

				fwrite($kml, "\t\t\t\t" . '<Placemark>' . "\n");
				fwrite($kml, "\t\t\t\t\t" . '<name>' . $file . '</name>' . "\n");
				fwrite($kml, "\t\t\t\t\t" . '<description><![CDATA[' . $jpeg_data . ']]></description>' . "\n");
				fwrite($kml, "\t\t\t\t\t" . '<LookAt>' . "\n");

				fwrite($kml, "\t\t\t\t\t\t" . '<longitude>' . $gps_long . '</longitude>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<latitude>' . $gps_lat . '</latitude>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<altitude>311</altitude>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<heading>0</heading>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<tilt>0</tilt>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<range>0.1</range>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<gx:altitudeMode>relativeToSeaFloor</gx:altitudeMode>' . "\n");
			
				fwrite($kml, "\t\t\t\t\t" . '</LookAt>' . "\n");
				fwrite($kml, "\t\t\t\t\t" . '<styleUrl>#m_ylw-pushpin1</styleUrl>' . "\n");
				fwrite($kml, "\t\t\t\t\t" . '<Point>' . "\n");
				/* 7 in */
				fwrite($kml, "\t\t\t\t\t\t" . '<gx:drawOrder>1</gx:drawOrder>' . "\n");
				fwrite($kml, "\t\t\t\t\t\t" . '<coordinates>' . $gps_long . ',' . $gps_lat . ',0</coordinates>' . "\n");
				fwrite($kml, "\t\t\t\t\t" . '</Point>' . "\n");
				fwrite($kml, "\t\t\t\t" . '</Placemark>' . "\n");
			}
			fwrite($kml, "\t\t\t" . '</Folder>' . "\n");
		}
		fwrite($kml, "\t\t" . '</Folder>' . "\n");
	}
	
	fwrite($kml, "\t" . '</Folder>' . "\n");
}

fwrite($kml, '</Document>' . "\n");
fwrite($kml, '</kml>' . "\n");
fclose($kml);
