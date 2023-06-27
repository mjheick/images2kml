<?php

/* Globals */
ini_set('memory_limit', '512M'); /* see https://www.php.net/manual/en/ini.core.php#ini.memory-limit */
$threshold = 2000;

/* basic checks */
if (!extension_loaded('gd')) {
	die("need php-gd loaded\n");
}
if (!isset($argv[1])) {
	die("need image as parameter\n");
}
$argument_filename = $argv[1];
if (!file_exists($argument_filename)) {
	die("file provided as parameter does not exist\n");
}

/* Adjust threshold */
if (isset($argv[2])) {
	$new_threshold = intval($argv[2]);
	if (($new_threshold < 80) || ($new_threshold > $threshold)) {
		die("Invalid threshold adjustment. 80 > x > $threshold\n");
	}
	$threshold = $new_threshold;
}
echo "$argument_filename\n";
/* Make our structure */
$filename = basename($argument_filename);
$json_output = [
	'original' => [
		'sha1' => '',
		'width' => 0,
		'height' => 0,
		'filename' => $filename,
		'filesize' => filesize($argument_filename),
	],
	'filename' => '',
	'filesize' => 0,
	'exif' => false,
	'width' => 0,
	'height' => 0,
	'date' => '',
	'time' => '',
	'lat' => '',
	'long' => '',
];

/* Start gathering image data */
$json_output['original']['sha1'] = sha1_file($argument_filename, false);
$data = getimagesize($argument_filename);
if ($data === false) {
	die("getimagesize() returned back false\n");
}
$json_output['original']['width'] = $data[0];
$json_output['original']['height'] = $data[1];

/* Check exif image data */
$exif = exif_read_data($argument_filename);
if ($exif !== false) {
	$json_output['exif'] = true;
	/* $json_output['x-exif'] = $exif; */
	/* Find date from the following: DateTimeOriginal, DateTimeDigitized, DateTime, GPSDateStamp/GPSTimeStamp */
	$check_gps_datetime = true;
	$source_keys = ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'];
	foreach ($source_keys as $key) {
		if (isset($exif[$key])) {
			$json_output['date'] = substr($exif[$key], 0, 10);
			$json_output['time'] = substr($exif[$key], 11, 8);
			$json_output['date'] = str_replace(':', '-', $json_output['date']);
			$check_gps_datetime = false;
			break;
		}
	}
	if ($check_gps_datetime && isset($exif['GPSDateStamp']) && isset($exif['GPSTimeStamp'])) {
		if (is_string($exif['GPSDateStamp'])) {
			$json_output['date'] = $exif['GPSDateStamp'];
			$json_output['date'] = str_replace(':', '-', $json_output['date']);
		}
		if (is_string($exif['GPSTimeStamp'])) {
			$json_output['time'] = $exif['GPSTimeStamp'];
		}
		if (is_array($exif['GPSDateStamp'])) {
			list($dd, $dv) = explode('/', $exif['GPSDateStamp'][0]);
			$json_output['date'] = sprintf('%04d', ($dd/$dv)) . '-';
			list($dd, $dv) = explode('/', $exif['GPSDateStamp'][1]);
			$json_output['date'] = $json_output['date'] . sprintf('%02d', ($dd/$dv)) . '-';
			list($dd, $dv) = explode('/', $exif['GPSDateStamp'][2]);
			$json_output['date'] = $json_output['date'] . sprintf('%02d', ($dd/$dv));
		}
		if (is_array($exif['GPSTimeStamp'])) {
			list($dd, $dv) = explode('/', $exif['GPSTimeStamp'][0]);
			$json_output['time'] = sprintf('%02d', ($dd/$dv)) . ':';
			list($dd, $dv) = explode('/', $exif['GPSTimeStamp'][1]);
			$json_output['time'] = $json_output['time'] . sprintf('%02d', ($dd/$dv)) . ':';
			list($dd, $dv) = explode('/', $exif['GPSTimeStamp'][2]);
			$json_output['time'] = $json_output['time'] . sprintf('%02d', ($dd/$dv));
		}
	}

	/* Check if we have GPS Coordinates */
	if (isset($exif['GPSLatitudeRef']) && isset($exif['GPSLatitude']) && isset($exif['GPSLongitudeRef']) && isset($exif['GPSLongitude'])) {
		if (is_array($exif['GPSLatitude'])) {
			list($dd, $dv) = explode('/', $exif['GPSLatitude'][0]);
			$degrees = $dd/$dv;
			list($dd, $dv) = explode('/', $exif['GPSLatitude'][1]);
			$minutes = $dd/$dv;
			list($dd, $dv) = explode('/', $exif['GPSLatitude'][2]);
			$seconds = $dd/$dv;
			$json_output['lat'] = number_format($degrees + ($minutes / 60) + ($seconds / 3600), 8);
		}
		if (is_string($exif['GPSLatitudeRef'])) {
			$json_output['lat'] = $json_output['lat'] . $exif['GPSLatitudeRef'];
		}
		if (is_array($exif['GPSLongitude'])) {
			list($dd, $dv) = explode('/', $exif['GPSLongitude'][0]);
			$degrees = $dd/$dv;
			list($dd, $dv) = explode('/', $exif['GPSLongitude'][1]);
			$minutes = $dd/$dv;
			list($dd, $dv) = explode('/', $exif['GPSLongitude'][2]);
			$seconds = $dd/$dv;
			$json_output['long'] = number_format($degrees + ($minutes / 60) + ($seconds / 3600), 8);
		}
		if (is_string($exif['GPSLongitudeRef'])) {
			$json_output['long'] = $json_output['long'] . $exif['GPSLongitudeRef'];
		}
	}

	/* In the rare occurange that EXIF date/time is all 0s we need to just trust file time */
	if (($json_output['date'] == '0000-00-00') || ($json_output['time'] == '00:00:00')) {
		$json_output['date'] = '';
		$json_output['time'] = '';
	}
}
/* If we haven't really set date/time yet, defer to filename */
if ((strlen($json_output['date']) == 0) && (strlen($json_output['time']) == 0)) {
	/* Earliest File Time marker wins */
	$filetime = fileatime($argument_filename);
	if ($filetime > filectime($argument_filename)) {
		$filetime = filectime($argument_filename);
	}
	if ($filetime > filemtime($argument_filename)) {
		$filetime = filemtime($argument_filename);
	}
	$json_output['date'] = date("Y-m-d", $filetime);
	$json_output['time'] = date("H:i:s", $filetime);
}

/* New filename */
$output_local_filename = str_replace('-', '', $json_output['date']) . '-' . str_replace(':', '', $json_output['time']) . '_' . substr($json_output['original']['sha1'], 0, 8);
$output_image_filename = $output_local_filename . '.jpg';
$output_json_filename = $output_local_filename . '.json';

/* Do we need to make a smaller image? */
if (($json_output['original']['width'] > $threshold) || ($json_output['original']['height'] > $threshold)) {
	$current_width = $json_output['original']['width'];
	$current_height = $json_output['original']['height'];
	$new_width = 0;
	$new_height = 0;
	if ($current_width < $current_height) {
		$ratio = $current_width / $current_height;
		$new_height = $threshold;
		$new_width = intval($new_height * $ratio);
	} else { /* h < w */
		$ratio = $current_height / $current_width;
		$new_width = $threshold;
		$new_height = intval($new_width * $ratio);
	}
	/* Make a new image */
	$src = imagecreatefromjpeg($argument_filename);
	if ($src === false) {
		die("failed to load '$argument_filename', probably some errors\n");
	}
	$dst = imagecreatetruecolor($new_width, $new_height);
	if (imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $current_width, $current_height) === false) {
		die("failed to resize source file '$argument_filename' from $current_width x $current_height to $new_width x $new_height\n");
	}
	imagejpeg($dst, $output_image_filename, 90);
	$json_output['width'] = $new_width;
	$json_output['height'] = $new_height;
} else {
	/* Just copy file as new */
	copy($argument_filename, $output_image_filename);
	$json_output['width'] = $json_output['original']['width'];
	$json_output['height'] = $json_output['original']['height'];
}

/* Final bits of data */
$json_output['filename'] = $output_local_filename . '.jpg';
$json_output['filesize'] = filesize($output_image_filename);

/* write json data */
$json = json_encode($json_output, JSON_PRETTY_PRINT);
file_put_contents($output_json_filename, $json);
echo "> $output_json_filename\n";
die();