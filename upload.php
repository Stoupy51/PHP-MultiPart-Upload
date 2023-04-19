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


//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////


//$js = file_get_contents('upload.js');
echo <<<HTML

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Split File Validation</title>
</head>
<body>
	<h1>Split File Validation Example</h1>
	<form id="form">
		<label>
			Select a file:
			<input type="file" id="file_input" required>
		</label>
		<br>
		<button type="submit">Submit</button>
	</form>

	<script type="text/javascript" src="upload.js"></script>
</body>
</html>

HTML;

