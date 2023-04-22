
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
const receiverLink = "receiver.php";
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
		formData.append("filename", fileInput.files[0].name);
		formData.append("cancel", true);
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


