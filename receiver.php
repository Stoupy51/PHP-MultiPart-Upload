<?php

// Require the ST_FileUploadUtils class
require_once "src/ST_FileUploadUtils.php";

try {

	// Handle file chunk upload : response code 200 for "OK" and 400 for "Bad Request"
	if (isset($_FILES["file"])) {
		ST_FileUploadUtils::saveFileChunk($_FILES["file"]);
		http_response_code(200);
	}

	// Handle end of file upload : response code 200 for "OK" and 400 for "Bad Request"
	else if (isset($_POST["totalFileChunks"]) && isset($_POST["filename"])) {
		ST_FileUploadUtils::mergeFileChunks($_POST["totalFileChunks"], $_POST["filename"]);
		http_response_code(200);
	}

	// Handle cancel upload : response code 200 for "OK" and 400 for "Bad Request"
	else if (isset($_POST["cancel"]) && isset($_POST["filename"])) {
		ST_FileUploadUtils::cancelUpload($_POST["filename"]);
		http_response_code(200);
	}

	// Handle error
	else
		http_response_code(400);

}

// Handle error
catch (Exception $e) {
	echo $e->getMessage();
	http_response_code(400);
}

