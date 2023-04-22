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

	public const TEMPORARY_FILES_FOLDER = "temporary_files";
	public const UPLOADED_FILES_FOLDER = "uploaded_files";



	///////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////// Private Methods ///////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Creates a folder if it doesn't exist.
	 * 
	 * @throws Exception			The folder could not be created.
	 * 
	 * @param string $folderPath	The path of the folder to create.
	 */
	private function createFolder($folderPath) {
		if (!file_exists($folderPath) && !is_dir($folderPath))
			if (!mkdir($folderPath))
				throw new Exception("[Error createFolder()] Could not create folder '$folderPath'.");
	}



	///////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////// Public Methods ////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////////////////////

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
	public static function saveFileChunk($fileArray) : void {

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
	 * @param int $totalFileChunks	The total number of chunks that make up the file.
	 * @param string $filename		The name of the file.
	 * 
	 * @throws Exception			- The file could not be created.
	 * 								- The chunk could not be read.
	 * 								- The chunk could not be written to the file.
	 * 								- The file could not be closed.
	 * 								- The chunk could not be deleted.
	 * 
	 * @return void
	 */
	public static function mergeFileChunks($totalFileChunks, $filename) : void {
	
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
				throw new Exception("[Error mergeFileChunks()] Could not read part '$chunkPath' of file '$savePath'.");

			// Write the chunk to the new file
			if (fwrite($saveFile, $chunk) === false)
				throw new Exception("[Error mergeFileChunks()] Could not write to file '$savePath'.");
		}
	
		// Close the new file
		if (fclose($saveFile) === false)
			throw new Exception("[Error mergeFileChunks()] Could not close file '$savePath'.");

		// Delete the temporary files
		for ($i = 1; $i <= $totalFileChunks; $i++) {
			$chunkPath = self::TEMPORARY_FILES_FOLDER . "/" . $filename . ".part" . $i;
			if (!unlink($chunkPath))
				throw new Exception("[Error mergeFileChunks()] Could not delete file '$chunkPath'.");
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
	public static function cancelUpload($filename) : void {
		$files = glob(self::TEMPORARY_FILES_FOLDER . "/" . $filename . "*");
		if ($files === false)
			throw new Exception("[Error cancelUpload()] Could not get files with base name '$filename'.");
		foreach ($files as $file)
			if (!unlink($file))
				throw new Exception("[Error cancelUpload()] Could not delete file '$file'.");
	}

	/**
	 * Returns the HTML for the file upload form.
	 * 
	 * @return string
	 */
	public static function getHTML() : string {
		$css = '<style>' . file_get_contents("upload.css") . '</style>';
		$js = '<script type="text/javascript">' . file_get_contents("upload.js") . '</script>';

		return <<<HTML

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title> Chunk Splitting Uploader </title>
	$css
</head>
<body>
	<h1> Chunk Splitting Uploader </h1>
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

	$js
</body>
</html>

HTML;


	}
}

