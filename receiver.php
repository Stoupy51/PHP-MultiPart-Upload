<?php

// Check for Uploaded File
if (isset($_FILES['file'])) {

	// Get File Array into variables
	$fileArray = $_FILES['file'];
	$filename = $fileArray['name'];
	$temporaryData = $fileArray['tmp_name'];

	// Save file to disk into 'temporary_files' folder
	$savePath = 'temporary_files/' . $filename;
	if (move_uploaded_file($temporaryData, $savePath) === false)
		echo 'Error: Could not save file to disk.';

	exit();
}
else
	echo 'Error: No file was uploaded.';

