<?php

// Check for Uploaded File
if (isset($_FILES["file"])) {

	// Get File Array variables
	$fileArray = $_FILES["file"];
	$filename = $fileArray["name"];
	$temporaryData = $fileArray["tmp_name"];

	// Save file to disk into "temporary_files" folder
	$savePath = "temporary_files/" . $filename;
	if (move_uploaded_file($temporaryData, $savePath))
		echo "File saved to temporary_files folder.";
	else
		echo "Error: File could not be saved to temporary_files folder.";
}
else if (isset($_POST["totalFileChunks"]) && isset($_POST["filename"])) {

	// Get POST variables
	$totalFileChunks = intval($_POST["totalFileChunks"]);
	$filename = $_POST["filename"];

	// Create a new file to save the chunks
	$savePath = "uploaded_files/" . $filename;
	$saveFile = fopen($savePath, "w");

	// Loop through all the chunks
	for ($i = 1; $i <= $totalFileChunks; $i++) {

		// Get the chunk, and write it to the new file
		$chunkPath = "temporary_files/" . $filename . ".part" . $i;
		$chunk = file_get_contents($chunkPath);
		fwrite($saveFile, $chunk);
	}

	// Close the new file
	fclose($saveFile);

	// Delete the temporary files
	for ($i = 1; $i <= $totalFileChunks; $i++) {
		$chunkPath = "temporary_files/" . $filename . ".part" . $i;
		unlink($chunkPath);
	}

	echo "File saved to uploaded_files folder.";
}
else
	echo "Error: No file was uploaded.";

