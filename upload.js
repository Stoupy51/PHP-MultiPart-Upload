
/**
 * Upload.js : Split a file into chunks and send them to the server.
 * This script is used by the upload.php file.
 * 
 * @brief Allow to upload large files to a server faster using HTTP Protocol.
 * 
 * @author Stoupy51
 * 
**/



// Constants (CHUNK_SIZE = 8 MB)
const CHUNK_SIZE = 1024 * 1024 * 8;
const form = document.getElementById("form");
const fileInput = document.getElementById("file_input");
console.log(fileInput);

// Variables
var simultaneousUploads = 0;
var simultaneousUploadsMax = 100;	// Maximum number of simultaneous uploads (Here, taking 800 MB of RAM)
var totalFileChunks = 0;			// Total number of file chunks

// Add an event listener to the form
form.addEventListener("submit", handleSubmit);




/**
 * Handle the form submission event to split the
 * file into chunks and send them to the server.
 * 
 * @param {Event} event The form submission event
 */
function handleSubmit(event) {

	// Prevent the default form submission
	event.preventDefault();

	// Get the file from the input element, create a new file reader, and variable
	const reader = new FileReader();
	const file = fileInput.files[0];
	let currentPosition = 0;			// Current position in the file (in bytes)
	totalFileChunks = 0;				// Reset the total number of file chunks

	/**
	 * Handle the file reader load event to send the file chunks to the server.
	 */
	reader.onload = async function() {
		// Wait if there are too many simultaneous uploads
		while (simultaneousUploads >= simultaneousUploadsMax) {
			// Wait 1s before checking again
			await new Promise(resolve => setTimeout(resolve, 1000));
		}

		// Get the current chunk of data and send it to the server
		const chunk = reader.result;
		totalFileChunks++;
		uploadFile(totalFileChunks, chunk);

		// Update the current position in the file
		currentPosition += chunk.byteLength;

		// Check if there are more chunks to read
		if (currentPosition < file.size) {
			// Read the next chunk
			const nextChunk = file.slice(currentPosition, currentPosition + CHUNK_SIZE);
			reader.readAsArrayBuffer(nextChunk);
		}
		else {
			// Send to the server the total number of file chunks
			fetch("receive.php", {
				method: "POST",
				body: JSON.stringify({
					totalFileChunks: totalFileChunks,
					filename: file.name
				})
			});
		}
	};

	// Start reading the file
	const firstChunk = file.slice(0, CHUNK_SIZE);
	reader.readAsArrayBuffer(firstChunk);
}


/**
 * Send a file chunk to the server.
 * 
 * @param {int} number The file chunk number (starting at 1)
 * @param {ArrayBuffer} chunk The file chunk to send
 */
async function uploadFile(number, chunk) {

	// Increment the number of simultaneous uploads
	simultaneousUploads++;

	// Send the file chunk to the server with the name "filename@@@number" (e.g. "file@@@1")
	const newFilename = fileInput.files[0].name + "@@@" + number;
	const formData = new FormData();
	formData.append("file", new Blob([chunk]), newFilename);
	const response = await fetch("receive.php", {
		method: "POST",
		body: formData
	});

	// Decrement the number of simultaneous uploads when the upload is finished
	simultaneousUploads--;

	// Print the response html body
	console.log(await response.text());
}


