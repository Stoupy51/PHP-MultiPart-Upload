<?php

/**
 * Class ST_FileUploadUtils : Provides utility methods for handling file uploads.
 * 
 * @author Stoupy51
 */
class ST_FileUploadUtils {

	///////////////////////////////////////////////////////////////////////////////////////////////
	////////////////////////////////////////// Constants //////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////////////////////

	public const UPLOADED_FILES_FOLDER = "uploaded_files";
	public const TEMPORARY_FILES_FOLDER = "temporary_files";
	public const UPLOADED_FILES_FOLDER_VALUE = 1;
	public const TEMPORARY_FILES_FOLDER_VALUE = 2;

	// The password is the sha256 hash
	private const password = "8fb85e182764a8abceb4668b004014936e18f1fe1e5e413c476605b534591396";

	///////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////// Private Methods ///////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Creates a folder if it doesn't exist.
	 * 
	 * @param string $folderPath	The path of the folder to create.
	 * 
	 * @throws Exception			The folder could not be created.
	 * 
	 * @return void
	 */
	private static function createFolder(string $folderPath) {
		if (!file_exists($folderPath) && !is_dir($folderPath))
			if (!mkdir($folderPath))
				throw new Exception("[Error createFolder()] Could not create folder '$folderPath'.");
	}

	/**
	 * Handles the login of the user by checking the password.
	 * 
	 * @param string $password		The password to check.
	 * 
	 * @return bool					True if the password is correct, false otherwise.
	 */
	private static function checkPassword(string $password) : bool {
		return hash("sha256", $password) === self::password;
	}

	/**
	 * Handles the file upload by saving the file to the temporary_files folder.
	 * 
	 * @param array $fileArray		The file array from the $_FILES variable containing ["name"] and ["tmp_name"].
	 * 
	 * @throws Exception			- The folder could not be created.
	 * 								- The file could not be moved.
	 * 
	 * @return void
	 */
	private static function saveFileChunk(array $fileArray) : void {

		// Create the folder if it doesn't exist and get save path 
		$savePath = self::TEMPORARY_FILES_FOLDER . "/" . $fileArray["name"];
		self::createFolder(self::TEMPORARY_FILES_FOLDER);

		// Move the file to the temporary_files folder
		if (move_uploaded_file($fileArray["tmp_name"], $savePath) === false)
			throw new Exception("[Error saveFileChunk()] Could not move file to '$savePath'.");
	}

	/**
	 * Handles the end of the file upload by merging the file chunks into a single file.
	 * 
	 * @param int $totalFileChunks			The total number of chunks that make up the file.
	 * @param string $filename				The name of the file.
	 * @param bool $deleteTemporaryFiles	True to delete the temporary files, false otherwise.
	 * 
	 * @throws Exception			- The file could not be created.
	 * 								- The chunk could not be read.
	 * 								- The chunk could not be written to the file.
	 * 								- The file could not be closed.
	 * 								- The chunk could not be deleted.
	 * 
	 * @return void
	 */
	private static function mergeFileChunks(int $totalFileChunks, string $filename, bool $deleteTemporaryFiles) : void {

		// Check special request (forceMerge with unknown number of chunks)
		if ($totalFileChunks == -1) {
			$filename = explode(".part", $filename)[0];
			$files = glob(self::TEMPORARY_FILES_FOLDER . "/" . $filename . ".part*");
			if ($files === false)
				throw new Exception("[Error mergeFileChunks()] Could not get files with base name '$filename'.");
			$totalFileChunks = count($files);
		}
	
		// Create a new file to save the chunks
		self::createFolder(self::UPLOADED_FILES_FOLDER);
		$savePath = self::UPLOADED_FILES_FOLDER . "/" . $filename;
		$saveFile = fopen($savePath, "w");
		if ($saveFile === false)
			throw new Exception("[Error mergeFileChunks()] Could not create file '$savePath'.");

		// Loop through all the chunks
		for ($i = 1; $i <= $totalFileChunks; $i++) {
	
			// Get the chunk
			$chunkPath = self::TEMPORARY_FILES_FOLDER . "/" . $filename . ".part" . $i;
			$chunk = file_get_contents($chunkPath);
			if ($chunk === false)
				continue;

			// Write the chunk to the new file
			if (fwrite($saveFile, $chunk) === false)
				throw new Exception("[Error mergeFileChunks()] Could not write to file '$savePath'.");
		}
	
		// Close the new file
		if (fclose($saveFile) === false)
			throw new Exception("[Error mergeFileChunks()] Could not close file '$savePath'.");

		// Delete the temporary files if requested
		if ($deleteTemporaryFiles) {
			for ($i = 1; $i <= $totalFileChunks; $i++) {
				$chunkPath = self::TEMPORARY_FILES_FOLDER . "/" . $filename . ".part" . $i;
				if (!unlink($chunkPath))
					throw new Exception("[Error mergeFileChunks()] Could not delete file '$chunkPath'.");
			}
		}
	}

	/**
	 * Cancels the file upload by deleting the temporary files containing the same base name.
	 * 
	 * @param string $filename		The name of the file.
	 * 
	 * @throws Exception			The file could not be deleted.
	 * 
	 * @return void
	 */
	private static function cancelUpload(string $filename) : void {
		$files = glob(self::TEMPORARY_FILES_FOLDER . "/" . $filename . "*");
		if ($files === false)
			throw new Exception("[Error cancelUpload()] Could not get files with base name '$filename'.");
		foreach ($files as $file)
			if (!unlink($file))
				throw new Exception("[Error cancelUpload()] Could not delete file '$file'.");
	}

	/**
	 * Deletes a file from the server, depending on the folder.
	 * 
	 * @param string $filename		The name of the file :
	 * 								- Temporary files are named "filename.partX" where X is the total number of chunks.
	 * 								- Uploaded files are named "filename".
	 * @param int $folder			The folder where the file is located.
	 * 
	 * @throws Exception			The file could not be deleted.
	 * 
	 * @return void
	 */
	private static function deleteFile(string $filename, int $folder) : void {

		// If the file is in the temporary files folder
		if ($folder === self::TEMPORARY_FILES_FOLDER_VALUE) {

			// Get base name
			$splittedFilename = explode(".part", $filename);
			$totalFileChunks = intval($splittedFilename[1]);
			$filename = $splittedFilename[0] . ".part";

			// Delete all the chunks
			for ($i = 1; $i <= $totalFileChunks; $i++) {
				$chunkPath = self::TEMPORARY_FILES_FOLDER . "/" . $filename . $i;
				if (!unlink($chunkPath))
					throw new Exception("[Error deleteFile()] Could not delete file '$chunkPath'.");
			}
		}

		// If the file is in the uploaded files folder
		else if ($folder === self::UPLOADED_FILES_FOLDER_VALUE) {

			// Delete the file
			$filePath = self::UPLOADED_FILES_FOLDER . "/" . $filename;
			if (!unlink($filePath))
				throw new Exception("[Error deleteFile()] Could not delete file '$filePath'.");
		}

		// If the folder is not recognized
		else
			throw new Exception("[Error deleteFile()] Folder '$folder' is not recognized.");
	}

	/**
	 * Lists all the files in a folder and its subfolders recursively.
	 * Depending on the folder, the files are named differently :
	 * 	- Temporary files are named "filename.partX" where X is the total number of chunks.
	 * 	- Uploaded files are named "filename".
	 * 
	 * The files are returned in an array with the following structure :
	 * 	- [0] : The name of the file.
	 * 	- [1] : The size of the file.	(Optional)
	 * 
	 * @param int $folder		The folder where the files are located.
	 * 
	 * @throws Exception		The files could not be listed.
	 * 
	 * @return void
	 */
	private static function listFiles(int $folder) : void {

		// Array of files
		$filesArray = [];

		// If the folder is the temporary files folder
		if ($folder === self::TEMPORARY_FILES_FOLDER_VALUE) {

			// Get all the files recursively
			$files = glob(self::TEMPORARY_FILES_FOLDER . "/*");
			if ($files === false)
				throw new Exception("[Error listFiles()] Could not get files in folder '" . self::TEMPORARY_FILES_FOLDER . "'.");

			// Get the names of the files without the path to temporary files folder
			$baseNames = [];
			foreach ($files as $file) {

				// Get the base name and split it
				$baseName = basename($file);
				$splittedBaseName = explode(".part", $baseName);

				// If the file is not a chunk, skip it
				if (count($splittedBaseName) !== 2)
					continue;

				// Add the base name to the array or increment the number of chunks
				if (!isset($baseNames[$splittedBaseName[0]]))
					$baseNames[$splittedBaseName[0]] = 1;
				else
					$baseNames[$splittedBaseName[0]]++;
			}

			// Fill the with basenames followed by the number of chunks
			foreach ($baseNames as $splittedBaseName => $totalFileChunks)
				$filesArray[] = [$splittedBaseName . ".part" . $totalFileChunks];
		}

		// If the folder is the uploaded files folder
		else if ($folder === self::UPLOADED_FILES_FOLDER_VALUE) {

			// Get all the files recursively
			$files = glob(self::UPLOADED_FILES_FOLDER . "/*");
			if ($files === false)
				throw new Exception("[Error listFiles()] Could not get files in folder '" . self::UPLOADED_FILES_FOLDER . "'.");

			// Get the names of the files without the path to uploaded files folder
			foreach ($files as $file) {

				// Get the base name and the size
				$baseName = basename($file);
				$filesize = filesize($file);

				// If the size is not available
				if ($filesize === false)
					throw new Exception("[Error listFiles()] Could not get size of file '$file'.");

				// Add the file to the array
				$filesArray[] = [$baseName, $filesize];
			}
		}

		// If the folder is not recognized
		else
			throw new Exception("[Error listFiles()] Folder '$folder' is not recognized.");
		
		// Send the files
		echo json_encode($filesArray);
	}

	///////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////// Public Methods ////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Handles the login of the user by sending a form to enter the
	 * password if no password is provided in the $_POST variable or
	 * by checking the password was already provided (in the $_SESSION variable)
	 * 
	 * @return void
	 */
	public static function handleLogin() : void {

		// Start the session
		session_start();

		// Check if the password is provided
		if (isset($_POST["password"])) {

			// Check the password
			if (self::checkPassword($_POST["password"])) {

				// Set the session variable
				$_SESSION["password"] = $_POST["password"];
			}
		}

		// Check if the password is set in the session variable
		if (!isset($_SESSION["password"])) {

			// Send the login form
			echo <<<HTML
				<form method="post" style="top: 40%; left: 50%; transform: translate(-50%, -50%); position: absolute;">
					<input type="password" name="password" placeholder="Password" autofocus required autocomplete="off" style="width: 200px; height: 30px; font-size: 20px;">
					<input type="submit" value="Login" style="width: 100px; height: 30px; font-size: 20px;">
				</form>
			HTML;
			exit();
		}
	}

	/**
	 * Returns the HTML for the file upload form.
	 * 
	 * @return string
	 */
	public static function getHTML() : string {
		$css = self::generateCss();
		$js = self::generateJs();

		return <<<HTML

<!DOCTYPE html>
<html style="height: 100%;">
<head>
	<meta charset="utf-8">
	<title> Chunk Splitting Uploader </title>
	$css
</head>
<body>
	<h1 style="text-align: center;"> Chunk Splitting Uploader </h1>

	<div id="container" style="position: relative; width: 100%; height: 100%; display: flex; justify-content: space-around;">

		<div id="upload_part">
			<h2> Uploading a File </h2>
			<form id="form">
				<datalist id="markers">
					<option value="0"></option>
					<option value="10"></option>
					<option value="20"></option>
					<option value="30"></option>
					<option value="40"></option>
					<option value="50"></option>
					<option value="60"></option>
					<option value="70"></option>
					<option value="80"></option>
					<option value="90"></option>
					<option value="100"></option>
				</datalist>

				<label>
					Select a file:
					<input type="file" id="file_input" required>
				</label><br>

				<label for="chunk_size"> Chunk Size (between 1 and 100 MB):
					<input type="number" id="cs_number" min="1" max="100" step="1" value="12"><br>
					<input type="range" id="cs_range" min="1" max="100" step="1" value="12" list="markers">
				</label><br>

				<label for="simultaneous_uploads"> Simultaneous Uploads (between 1 and 100 packets):
					<input type="number" id="su_number" min="1" max="100" step="1" value="25"><br>
					<input type="range" id="su_range" min="1" max="100" step="1" value="25" list="markers">
				</label><br>

				<label>
					<button id="button" type="submit"> Submit </button>
				</label>
			</form>

			<div id="progress_container" class="progress_container" style="display: none;">
				<div id="progress_bar" class="progress_bar">
					<div id="progress_bar_inner" class="progress_bar_inner" style="width: 0%;"></div>
				</div>
				<div id="progress_text" class="progress_text">
					<span id="progress_state"> Uploading... </span>
					<span id="progress_percent"></span>
					<span id="progress_size"></span>
					<span id="progress_speed"></span>
				</div>
				<div id="progress_time" class="progress_time">
					<span>
						<span> Time Elapsed: </span>
						<span id="progress_time_elapsed"> - </span>
					</span>
					<span>
						<span> Time Remaining: </span>
						<span id="progress_time_remaining"> - </span>
					</span>
				</div>
			</div>
		</div>

		<div id="file_explorer">
			<h2> File Explorer </h2>
			<div>
				<button id="refresh_button"> Refresh </button>
				<button id="show_uploaded_files"> Uploaded Files </button>
				<button id="show_temporary_files"> Temporary Files </button>
			</div>
			<table>
				<thead>
					<tr>
						<th> File Name </th>
						<th> File Size </th>
						<th> Actions </th>
					</tr>
				</thead>
				<tbody id="file_explorer_table_body" style="overflow-y: scroll;">
					<br>
				</tbody>
			</table>
		</div>
	</div>

	$js
</body>
</html>

HTML;
	}

	/**
	 * Deletes all the temporary files that are older than 1 day.
	 * (Search only for files with the .part extension)
	 * 
	 * @throws Exception		- The temporary files could not be retrieved.
	 * 							- The last modified time of a file could not be retrieved.
	 * 							- A file could not be deleted.
	 * 
	 * @return void
	 */
	public static function deleteOldTemporaryFiles() : void {

		// Get all the temporary files
		$files = glob(self::TEMPORARY_FILES_FOLDER . "/*.part*");
		if ($files === false)
			throw new Exception("[Error deleteOldTemporaryFiles()] Could not get temporary files.");

		// Loop through all the temporary files
		foreach ($files as $file) {

			// Get the file's last modification time
			$lastModified = filemtime($file);
			if ($lastModified === false)
				throw new Exception("[Error deleteOldTemporaryFiles()] Could not get last modified time of file '$file'.");

			// If the file is older than 1 day, delete it
			if (time() - $lastModified > 86400)
				if (!unlink($file))
					throw new Exception("[Error deleteOldTemporaryFiles()] Could not delete file '$file'.");
		}
	}

	/**
	 * Manage the tasks of the receiver such as:
	 * - Canceling an upload
	 * - Finishing an upload
	 * - Uploading a file chunk
	 * - Listing the files
	 * - Deleting a file
	 * - Forcing the merge of a file
	 * 
	 * @return bool
	 */
	public static function receiverJob() : bool {

		// Stop if there is no request
		if (!isset($_POST["request_type"]))
			return false;

		// Handle requests
		try {

			// Switch case for the request type
			switch ($_POST["request_type"]) {

				case "cancel_upload":
					self::cancelUpload($_POST["filename"]);
					break;

				case "finish_upload":
					self::mergeFileChunks($_POST["totalFileChunks"], $_POST["filename"], true);
					break;

				case "file_chunk_upload":
					self::saveFileChunk($_FILES["file"]);
					break;
				
				case "list_files":
					self::listFiles(intval($_POST["type"]));
					break;
				
				case "delete_file":
					self::deleteFile($_POST["filename"], intval($_POST["type"]));
					break;
				
				case "force_merge":
					self::mergeFileChunks(-1, $_POST["filename"], false);
					break;
				
				default:
					throw new Exception("[Error receiverJob()] Unknown request type.");
			}
		}
		
		// Handle error
		catch (Exception $e) {
			echo $e->getMessage();
			http_response_code(400);
		}

		// Return true
		return true;
	}


	///////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////// CSS & JavaScript //////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Generates the CSS code for page.
	 * 
	 * @return string	The CSS code in a style tag.
	 */
	private static function generateCss() : string {
		return
			'<style>'
			.
<<<CSS
			
/**
 * Upload.css : CSS file for the Upload page
 * 
 * @author Stoupy51
 * 
**/

/* Root */
:root {
	/* Constantes */
	--primary_color: #448a95;
	--base_animation_duration: 30s;
	--alert_bg_color: rgb(255, 243, 205);
	--alert_color: rgb(102, 77, 3);
	--alert_border_color: rgb(255, 230, 156);
	--error_bg_color: rgb(255, 205, 205);
	--error_color: rgb(102, 3, 3);
	--error_border_color: rgb(255, 156, 156);
	--root_background_color: #1e1e1e; /* e9e9e9 */
	--root_font-size: 16px;
	--root_box_shadow:	2px 2px 1px -1px rgba(0, 0, 0, 0.2),
						0px 1px 1px 0px rgba(0, 0, 0, 0.14),
						0px 1px 3px 0px rgba(0, 0, 0, 0.12);
	--root_color_main: #f2f2f2;
	--root_color_secondary: #dddddd;
	--root_color_tertiary: #cccccc;


	/* Bases */
	background: var(--root_background_color);
	font-size: var(--root_font-size);
	color : var(--root_color_main);
}






/* Global */
.as_primary_color {
	background-color: var(--primary_color);
}

.as_header {
	top: 0;
	position: fixed;
	width: 100%;
	z-index: -101;
	padding-top: 62px;
}

.as_footer {
	bottom: 0;
	position: fixed;
	width: 100%;
	z-index: 100;
}

.as_hx {
	font-size: 28px;
	font-weight: 600;
	white-space: wrap;
	line-height: 33.6px;
	color: #000000;
	padding: 1rem;
	border-radius: 0.5rem;
	background-color: var(--root_background_color);
	width: fit-content;
	margin: auto;
	box-shadow:
		0px 2px 1px -1px rgba(0, 0, 0, 0.2),
		0px 1px 1px 0px rgba(0, 0, 0, 0.14),
		0px 1px 3px 0px rgba(0, 0, 0, 0.12);
}

.as_top_container {
	position: absolute;
	top: 3rem;
	left: 50%;
	transform: translate(-50%, 0);
	padding-bottom: 4rem;
	width: max(35vw, 540px);
}

.as_warning {
	padding: 1rem;
	margin: 1rem auto;

	color: var(--alert_color);
	background-color: var(--alert_bg_color);
	border: 1px solid var(--alert_border_color);
	border-radius: 5px;

	width: max(35vw, 540px);
	box-shadow:
		0px 2px 1px -1px rgba(0, 0, 0, 0.2),
		0px 1px 1px 0px rgba(0, 0, 0, 0.14),
		0px 1px 3px 0px rgba(0, 0, 0, 0.12);
}

.as_error {
	padding: 1rem;
	margin: 1rem auto;

	color: var(--error_color);
	background-color: var(--error_bg_color);
	border: 1px solid var(--error_border_color);
	border-radius: 5px;

	width: 35vw;
	box-shadow:
		0px 2px 1px -1px rgba(0, 0, 0, 0.2),
		0px 1px 1px 0px rgba(0, 0, 0, 0.14),
		0px 1px 3px 0px rgba(0, 0, 0, 0.12);
}

.as_navigation {
	z-index: 100;
	position: fixed;
	top: 0;
	right: 0;
	background-color: #4da1ae;
	border-bottom-left-radius: 0.75rem;

	/* Animation qui fait apparaitre la navigation */
	animation: fadeIn 0.5s ease-in-out;
}

.as_navigation > a {
	text-decoration: 0;
}

.as_home_button, .as_logout_button {
	padding: 0.75rem;
	width: 64px;
}

.as_logout_button {
	background-color: #4896a1;
}



/* Banner */
@keyframes fadeIn {
	0% { opacity: 0; } 
	100% { opacity: 1; }
}

.as_banner {
	position: fixed;
	z-index: -100;
	right: 0;
	bottom: -50px;
	width: 1500px;
}


/* Gestion des vagues */
.as_waves {
	position: fixed;
	top: 60px;
	width: 100%;
	height: 15vh;
	margin-bottom: -7px;
	/* Fix for safari gap */
	min-height: 100px;
	max-height: 150px;
	scale: 1 -1;
}

.as_parallax > use {
	animation: move-forever 25s cubic-bezier(.55, .5, .45, .5) infinite;
	fill: var(--primary_color)
}

.as_parallax > use:nth-child(1) {
	animation-delay: -10s;
	animation-duration: calc(var(--base_animation_duration));
	opacity: 0.7;
}

.as_parallax > use:nth-child(2) {
	animation-delay: -16s;
	animation-duration: calc(var(--base_animation_duration) * 1.0);
	opacity: 0.5;
}

.as_parallax > use:nth-child(3) {
	animation-delay: -20s;
	animation-duration: calc(var(--base_animation_duration) * 1.6);
	opacity: 0.2;
}

.as_parallax > use:nth-child(4) {
	animation-delay: -25s;
	animation-duration: calc(var(--base_animation_duration) * 3.0);
}

@keyframes move-forever {
	0% {
		transform: translate3d(-90px, 0, 0);
	}

	100% {
		transform: translate3d(85px, 0, 0);
	}
}


/* TODO : Adaptation au mobile */
@media (max-width: 600px) {

	/* Correction des largeurs */
	.as_warning, .as_error, .as_top_container {
		width: 90vw;
	}

	/* Correction des hauteurs */
	.as_top_container {
		top: 5rem;
	}
};



html {
	height: 100%;
}

input[type="range"], .progress_container {
	width: 30rem;
}

label {
	width: fit-content;
	display: block;
}

.progress_container {
	margin-top: 1rem;
}

.progress_bar {
	width: 100%;
	display: block;
	height: 20px;

	border: 1px solid #000;
	border-radius: 5px;
}

.progress_bar_inner {
	background-color: rgb(0, 169, 0);
	display: block;
	height: 100%;
	transition: width 1s ease;
}

.progress_text {
	margin-top: 1rem;
	display: flex;
	justify-content: space-between;
}

.progress_text span {
	display: inline-block;
}

.progress_text span:nth-child(1), .progress_text span:nth-child(3) {
	text-decoration: underline;
}

.progress_time span {
	display: flex;
}

.progress_time > span > span:nth-child(1) {
	width: 25%;
}

tr > td:nth-child(2) {
	text-align: right;
}


CSS
			.
			'</style>';
	}

	/**
	 * Generates the JS code for the page.
	 * 
	 * @return string	The JS code in a script tag.
	 */
	private static function generateJs() : string {

		// Constants for the JS code
		$uploaded_files_folder = self::UPLOADED_FILES_FOLDER;
		$uploaded_files_folder_value = self::UPLOADED_FILES_FOLDER_VALUE;
		$temporary_files_folder_value = self::TEMPORARY_FILES_FOLDER_VALUE;

		// Get the current file path without the root path
		$file_link = str_replace($_SERVER['DOCUMENT_ROOT'], '', __FILE__);

		// Write the JS code
		$js = '<script type="text/javascript">';
		$js .= <<<JS

/**
 * Upload.js : Split a file into chunks and send them to the server.
 * This script is used by the upload.php file.
 * 
 * @brief Allow to upload large files to a server faster using HTTP Protocol.
 * 
 * @author Stoupy51
 * 
**/

/**
 * Convert bytes to a human readable string.
 * 
 * @param {Number} bytes The number of bytes to convert
 * 
 * @return {String} The human readable string
 */
function convertIntBytesToString(bytes, unit = "B") {
	if (bytes >= 1024 * 1024 * 1024 * 1024)
		return (bytes / 1024 / 1024 / 1024 / 1024).toFixed(2) + " T" + unit;
	if (bytes >= 1024 * 1024 * 1024)
		return (bytes / 1024 / 1024 / 1024).toFixed(2) + " G" + unit;
	if (bytes >= 1024 * 1024)
		return (bytes / 1024 / 1024).toFixed(2) + " M" + unit;
	if (bytes >= 1024)
		return (bytes / 1024).toFixed(2) + " K" + unit;
	return bytes + " " + unit;
}

// Setup the input range for Chunk Size
const cs_range = document.getElementById("cs_range");
const cs_number = document.getElementById("cs_number");
var cs_value = 1024 * 1024 * cs_number.value;
cs_range.addEventListener("input", function() {
	cs_number.value = cs_range.value;
	cs_value = 1024 * 1024 * cs_number.value;
});
cs_number.addEventListener("input", function() {
	cs_range.value = cs_number.value;
	cs_value = 1024 * 1024 * cs_number.value;
});

// Setup the input range for Simultaneous Uploads
const su_range = document.getElementById("su_range");
const su_number = document.getElementById("su_number");
su_range.addEventListener("input", function() { su_number.value = su_range.value; });
su_number.addEventListener("input", function() { su_range.value = su_number.value; });

// Constants
const form = document.getElementById("form");
const fileInput = document.getElementById("file_input");
const button = document.getElementById("button");
const receiverLink = "$file_link";
const progressContainer = document.getElementById("progress_container");	// Progress bar container element
const progressBarInner = document.getElementById("progress_bar_inner");		// Progress bar inner element
const progressState = document.getElementById("progress_state");			// Progress bar state element
const progressPercent = document.getElementById("progress_percent");		// Progress bar percent element
const progressSize = document.getElementById("progress_size");				// Progress bar size element
const progressSpeed = document.getElementById("progress_speed");			// Progress bar speed element
const progressTimeElapsed = document.getElementById("progress_time_elapsed");		// Progress bar time elapsed element
const progressTimeRemaining = document.getElementById("progress_time_remaining");	// Progress bar time remaining element

// Variables
var simultaneousUploads = 0;
var totalFileChunks = 0;				// Total number of file chunks
var isRunning = false;					// Is the script running?
var controller = new AbortController();	// Abort controller to cancel the async functions
var currentFileSize = 0;				// Current amount of bytes uploaded
var progressiveSize = 0;				// Current amount of bytes uploaded (used for the progressive upload)

// Add an event listener to the form
form.addEventListener("submit", handleSubmit);


/**
 * Update the progress bar progressively.
 * 
 * @param {AbortController} controller The AbortController to cancel the async function
 */
async function progressBarUpdateLoop(controller) {

	// Variables for the upload speed
	var startDateTime = new Date();
	var previousSize = 0;

	while (isRunning) {
		// Wait 10ms before checking again
		await new Promise(resolve => setTimeout(resolve, 10));

		// Get the time difference between the start and the current date
		var currentDateTime = new Date();
		var timeDiff = currentDateTime.getTime() - startDateTime.getTime();
		var bytesPerSecond = progressiveSize / timeDiff;

		// Update progressively the variable
		if (progressiveSize < currentFileSize) {
			progressiveSize += Math.round((currentFileSize - progressiveSize) / 100);
		}
		if (progressiveSize > currentFileSize)
			progressiveSize = currentFileSize;
		
		// When the size changed,
		if (progressiveSize > previousSize) {

			// Update the upload speed
			progressSpeed.innerHTML = convertIntBytesToString(bytesPerSecond * 1000 * 8 + 500000, "bps");
			previousSize = progressiveSize;

			// Update the time remaining using file size, current size, and time elapsed
			var timestampTotal = (fileInput.files[0].size * timeDiff / progressiveSize);
			var timeRemaining =  new Date(timestampTotal - timeDiff);
			var hours = timeRemaining.getHours() - 1;
			var minutes = timeRemaining.getMinutes();
			var seconds = timeRemaining.getSeconds();
			progressTimeRemaining.innerHTML = "";
			if (hours > 0)
				progressTimeRemaining.innerHTML += hours + " hours, ";
			if (minutes > 0)
				progressTimeRemaining.innerHTML += minutes + " minutes, ";
			progressTimeRemaining.innerHTML += seconds + " seconds";
		}

		// Update the time elapsed with format "hh hours, mm minutes, ss secondes"
		var timeElapsed = new Date(currentDateTime.getTime() - startDateTime.getTime());
		var hours = timeElapsed.getHours() - 1;
		var minutes = timeElapsed.getMinutes();
		var seconds = timeElapsed.getSeconds();
		progressTimeElapsed.innerHTML = "";
		if (hours > 0)
			progressTimeElapsed.innerHTML += hours + " hours, ";
		if (minutes > 0)
			progressTimeElapsed.innerHTML += minutes + " minutes, ";
		progressTimeElapsed.innerHTML += seconds + " seconds";

		// Update the progress bar
		if (isRunning) {
			var percent = progressiveSize / fileInput.files[0].size * 100;
			progressBarInner.style.width = percent + "%";
			progressPercent.innerHTML = percent.toFixed(2) + "%";
			progressSize.innerHTML = convertIntBytesToString(progressiveSize) + " / " + convertIntBytesToString(fileInput.files[0].size);
		}
		else
			return;
	}
}


/**
 * Handle the form submission event to split the
 * file into chunks and send them to the server.
 * 
 * @param {Event} event The form submission event
 */
function handleSubmit(event) {

	// Prevent the default form submission
	event.preventDefault();

	// Check if the script is already running, if yes, stop every async functions and send a cancel request to the server
	if (isRunning) {

		// Send a cancel request to the server with the filename
		const formData = new FormData();
		formData.append("request_type", "cancel_upload");
		formData.append("filename", fileInput.files[0].name);
		fetch(receiverLink, {
			method: "POST",
			body: formData
		});

		// Console log
		console.log("Cancelled upload of '" + fileInput.files[0].name + "'");

		// Stop every async functions (stop requests, stop reading the file, etc.)
		controller.abort();
		controller = new AbortController();

		// Reset the form
		button.innerHTML = "Upload";
		isRunning = false;

		// Reset the progress bar
		progressContainer.style.display = "none";
		progressBarInner.style.width = "0%";
		progressPercent.innerHTML = "";
		progressSize.innerHTML = "";
		progressSpeed.innerHTML = "";
		progressState.innerHTML = "Upload cancelled";
		progressTimeElapsed.innerHTML = "-";
		progressTimeRemaining.innerHTML = "-";
		return;
	}

	// Change the button text and the script as running
	button.innerHTML = "Cancel";
	isRunning = true;

	// Get the file from the input element, create a new file reader, and variable
	const reader = new FileReader();
	const file = fileInput.files[0];
	let currentPosition = 0;			// Current position in the file (in bytes)
	totalFileChunks = 0;				// Reset the total number of file chunks
	simultaneousUploads = 0;			// Reset the number of simultaneous uploads
	currentFileSize = 0;				// Reset the current file size
	progressiveSize = 0;				// Reset the progressive file size

	// Setup progress bar
	progressContainer.style.display = "block";
	progressBarInner.style.width = "0%";
	progressPercent.innerHTML = "0%";
	progressSize.innerHTML = "0 B / " + convertIntBytesToString(file.size);
	progressSpeed.innerHTML = "0 bps";
	progressState.innerHTML = "Uploading...";
	progressBarUpdateLoop(controller);

	/**
	 * Handle the file reader load event to send the file chunks to the server.
	 */
	reader.onload = async function() {
		// Wait if there are too many simultaneous uploads
		while (simultaneousUploads >= su_number.value) {
			// Wait 1ms before checking again
			await new Promise(resolve => setTimeout(resolve, 1));
		}

		// Get the current chunk of data and send it to the server
		const chunk = reader.result;
		totalFileChunks++;
		uploadFile(totalFileChunks, chunk, controller);

		// Update the current position in the file
		currentPosition += chunk.byteLength;

		// Check if there are more chunks to read
		if (currentPosition < file.size) {
			// Read the next chunk
			const nextChunk = file.slice(currentPosition, currentPosition + cs_value);
			reader.readAsArrayBuffer(nextChunk);
		}
		else {
			// Wait for all the uploads to finish by checking if there are still some simultaneous uploads every 1ms
			while (simultaneousUploads > 0)
				await new Promise(resolve => setTimeout(resolve, 1));

			// Send to the server the total number of file chunks and the filename
			const formData = new FormData();
			formData.append("request_type", "finish_upload");
			formData.append("totalFileChunks", totalFileChunks);
			formData.append("filename", file.name);
			const response = await fetch(receiverLink, {
				method: "POST",
				body: formData,
				signal: controller.signal
			});

			// Reset the form
			button.innerHTML = "Upload";
			isRunning = false;

			// Wait for the response and return
			await response.text();

			// Set the progress bar to 100% and return
			progressBarInner.style.width = "100%";
			progressPercent.innerHTML = "100%";
			var text = convertIntBytesToString(fileInput.files[0].size);
			progressSize.innerHTML = text + " / " + text;
			progressState.innerHTML = "Upload finished";
			progressTimeRemaining.innerHTML = "-";
			return;
		}
	};

	// Start reading the file
	const firstChunk = file.slice(0, cs_value);
	reader.readAsArrayBuffer(firstChunk, { signal: controller.signal });
}


/**
 * Send a file chunk to the server.
 * 
 * @param {int} number The file chunk number (starting at 1)
 * @param {ArrayBuffer} chunk The file chunk to send
 * @param {AbortController} controller The abort controller to stop the async function
 */
async function uploadFile(number, chunk, controller) {

	// Increment the number of simultaneous uploads
	simultaneousUploads++;

	// Send the file chunk to the server with the name "filename.partX" (e.g. "file.part1")
	const newFilename = fileInput.files[0].name + ".part" + number;
	const formData = new FormData();
	formData.append("request_type", "file_chunk_upload");
	formData.append("file", new Blob([chunk]), newFilename);
	try {
		const response = await fetch(receiverLink, {
			method: "POST",
			body: formData,
			signal: controller.signal,
		});

		// Wait for the response when the upload is finished
		await response.text();

		// Decrement the number of simultaneous uploads
		simultaneousUploads--;

		// Update the current file size
		currentFileSize += chunk.byteLength;
	}
	catch (error) {
		return;
	}
}




//////////////////////////////////////////////////////////
/////////////////// File Explorer part ///////////////////
//////////////////////////////////////////////////////////

// Constants
const fileExplorer = document.getElementById("file_explorer");
const fileExplorerTableBody = document.getElementById("file_explorer_table_body");
const refreshButton = document.getElementById("refresh_button");
const showUploadedFilesButton = document.getElementById("show_uploaded_files");
const showTemporaryFilesButton = document.getElementById("show_temporary_files");

// Variables
var fileExplorerType = $uploaded_files_folder_value;	// 1 = uploaded files, 2 = temporary files

// Add event listeners
refreshButton.addEventListener("click", refreshFileExplorer);
showUploadedFilesButton.addEventListener("click", showUploadedFiles);
showTemporaryFilesButton.addEventListener("click", showTemporaryFiles);

/**
 * Refresh the file explorer by sending a request to the server.
 * 
 * @param {Event} event The event that triggered the function
 * 
 * @returns {void}
 */
function refreshFileExplorer(event) {

	// Send a request to the server to get the files
	const formData = new FormData();
	formData.append("request_type", "list_files");
	formData.append("type", fileExplorerType);
	fetch(receiverLink, {
		method: "POST",
		body: formData
	})
	.then(response => response.json())

	// Wait for the response
	.then(data => {
		
		// Clear the file explorer table
		fileExplorerTableBody.innerHTML = "";

		// Add the files to the file explorer table
		for (let i = 0; i < data.length; i++) {

			// Get the current file (JSON object : {filename: "filename", filesize: 123456})
			var file = data[i];
			
			// Create the file explorer table row
			var tr = document.createElement("tr");
			var l_filename = document.createElement("td");
			l_filename.innerHTML = file[0];
			var l_filesize = document.createElement("td");
			if (file[1])
				l_filesize.innerHTML = convertIntBytesToString(file[1]);
			else
				l_filesize.innerHTML = "-";
			var l_actions = document.createElement("td");

			// If the file explorer is for uploaded files, add the download button
			if (fileExplorerType == $uploaded_files_folder_value) {
				var l_download = document.createElement("button");
				l_download.innerHTML = "Download";
				l_download.id = file[0];
				l_download.addEventListener("click", function() { downloadFile(this); });
				l_actions.appendChild(l_download);
			}

			// Else, add the force merge button
			else if (fileExplorerType == $temporary_files_folder_value) {
				var l_force_merge = document.createElement("button");
				l_force_merge.innerHTML = "Force Merge";
				l_force_merge.id = file[0];
				l_force_merge.addEventListener("click", function() { forceMerge(this); });
				l_actions.appendChild(l_force_merge);
			}

			// Delete button
			var l_delete = document.createElement("button");
			l_delete.innerHTML = "Delete";
			l_delete.id = file[0];
			l_delete.addEventListener("click", function() { deleteFile(this); });
			l_actions.appendChild(l_delete);

			// Append the elements to the table row
			tr.appendChild(l_filename);
			tr.appendChild(l_filesize);
			tr.appendChild(l_actions);

			// Append the table row to the table body
			fileExplorerTableBody.appendChild(tr);
		}

		// If there are no files, add a message
		if (data.length == 0) {
			var tr = document.createElement("tr");
			var l_no_files = document.createElement("td");
			l_no_files.innerHTML = "No files";
			l_no_files.colSpan = 3;
			tr.appendChild(l_no_files);
			fileExplorerTableBody.appendChild(tr);
		}
	});
}

/**
 * Show the uploaded files in the file explorer.
 * 
 * @param {Event} event The event that triggered the function
 * 
 * @returns {void}
 */
function showUploadedFiles(event) {
	fileExplorerType = 1;
	refreshFileExplorer();
}

/**
 * Show the temporary files in the file explorer.
 * 
 * @param {Event} event The event that triggered the function
 * 
 * @returns {void}
 */
function showTemporaryFiles(event) {
	fileExplorerType = 2;
	refreshFileExplorer();
}

/**
 * Delete a file by sending a request to the server.
 * 
 * @param {Element} element The element that triggered the function
 * 
 * @returns {void}
 */
function deleteFile(element) {

	// Send a request to the server to delete the file
	const formData = new FormData();
	formData.append("request_type", "delete_file");
	formData.append("filename", element.id);
	formData.append("type", fileExplorerType);
	fetch(receiverLink, {
		method: "POST",
		body: formData
	})
	.then(response => response.text())

	// Wait for the response
	.then(data => {
		refreshFileExplorer();
	});
}

/**
 * Download a file by opening a new tab.
 * 
 * @param {Element} element The element that triggered the function
 * 
 * @returns {void}
 */
function downloadFile(element) {
	window.open("$uploaded_files_folder/" + element.id, "_blank");
}

/**
 * Force merge a file by sending a request to the server.
 * 
 * @param {Element} element The element that triggered the function
 * 
 * @returns {void}
 */
function forceMerge(element) {

	// Send a request to the server to force merge the file
	const formData = new FormData();
	formData.append("request_type", "force_merge");
	formData.append("filename", element.id);
	fetch(receiverLink, {
		method: "POST",
		body: formData
	})
	.then(response => response.text())

	// Wait for the response
	.then(data => {
		refreshFileExplorer();
	});
}

// Refresh the file explorer
refreshFileExplorer();

JS;

		$js .= '</script>';

		return $js;
	}
}

ini_set('display_errors', 1);

///// Manage client requests
// Handle login
ST_FileUploadUtils::handleLogin();

// Check if the receiver need to do something
if (ST_FileUploadUtils::receiverJob() == false) {

	// If not, delete old temporary files
	ST_FileUploadUtils::deleteOldTemporaryFiles();

	// Send the HTML page
	echo ST_FileUploadUtils::getHTML();
}

