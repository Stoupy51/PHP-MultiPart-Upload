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
}

