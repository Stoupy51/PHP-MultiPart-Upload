<?php

// Require the ST_FileUploadUtils class
require_once "src/ST_FileUploadUtils.php";


// Handle file chunk upload : response code 200 for "OK" and 400 for "Bad Request"
if (isset($_FILES["file"])) {
	try {
		ST_FileUploadUtils::saveFileChunk($_FILES["file"]);
		http_response_code(200);
	}
	catch (Exception $e) {
		http_response_code(400);
		echo $e->getMessage();
	}
}


// Handle end of file upload : response code 200 for "OK" and 400 for "Bad Request"
else if (isset($_POST["totalFileChunks"]) && isset($_POST["filename"])) {
	try {
		ST_FileUploadUtils::mergeFileChunks($_POST["totalFileChunks"], $_POST["filename"]);
		http_response_code(200);
	}
	catch (Exception $e) {
		http_response_code(400);
		echo $e->getMessage();
	}
}

// Handle error
else
	http_response_code(400);

