<?php

/**
 * Upload.php : Send a page with a form to upload a file
 * with a script to send the file to the server in chunks
 * (by splitting it into smaller files)
 * 
 * @author Stoupy51
 * 
**/

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

