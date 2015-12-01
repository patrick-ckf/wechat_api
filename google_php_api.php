<?php
set_include_path(get_include_path() . PATH_SEPARATOR . '/google-api-php-client/src');
require __DIR__ . '/google-api-php-client/src/Google/autoload.php';

define('APPLICATION_NAME', 'Drive API PHP Quickstart');
define('CREDENTIALS_PATH', '~/.credentials/drive-php-quickstart.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
define('SCOPES', implode(' ', array(
	Google_Service_Drive::DRIVE)
));

if (php_sapi_name() != 'cli') {
	throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
	$client = new Google_Client();
	$client->setApplicationName(APPLICATION_NAME);
	$client->setScopes(SCOPES);
	$client->setAuthConfigFile(CLIENT_SECRET_PATH);
	$client->setAccessType('offline');

	// Load previously authorized credentials from a file.
	$credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
	if (file_exists($credentialsPath)) {
		$accessToken = file_get_contents($credentialsPath);
	} else {
		// Request authorization from the user.
    		$authUrl = $client->createAuthUrl();
    		printf("Open the following link in your browser:\n%s\n", $authUrl);
    		print 'Enter verification code: ';
    		$authCode = trim(fgets(STDIN));

   		// Exchange authorization code for an access token.
    		$accessToken = $client->authenticate($authCode);

    		// Store the credentials to disk.
    		if(!file_exists(dirname($credentialsPath))) {
      			mkdir(dirname($credentialsPath), 0700, true);
    		}
    		file_put_contents($credentialsPath, $accessToken);
    		printf("Credentials saved to %s\n", $credentialsPath);
  	}
  	$client->setAccessToken($accessToken);

  	// Refresh the token if it's expired.
  	if ($client->isAccessTokenExpired()) {
    		$client->refreshToken($client->getRefreshToken());
    		file_put_contents($credentialsPath, $client->getAccessToken());
  	}
  	return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
	$homeDirectory = getenv('HOME');
	if (empty($homeDirectory)) {
		$homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
	}
	return str_replace('~', realpath($homeDirectory), $path);
}

function insertFile($service, $title, $description, $parentId, $mimeType, $filename) {
	$file = new Google_Service_Drive_DriveFile();
	$file->setTitle($title);
	$file->setDescription($description);
	$file->setMimeType($mimeType);

	// Set the parent folder.
	if ($parentId != null) {
		$parent = new Google_Service_Drive_ParentReference();
		$parent->setId($parentId);
		$file->setParents(array($parent));
	}

	try {
		$data = file_get_contents($filename);
		$createdFile = $service->files->insert($file, array(
			'data' => $data,
			'convert' => true,
			'mimeType' => $mimeType,
			'uploadType' => 'multipart',
		));
		return $createdFile;
	} catch (Exception $e) {
		print "An error occurred: " . $e->getMessage();
	}
}

function searchFile($service, $filename) {
	echo __FUNCTION__.": searching file $filename\n";
	
	$result = array();
	$pageToken = NULL;

	do {
		try {
			$parameters = array();
			if ($pageToken) {
				$parameters['pageToken'] = $pageToken;
			}
			$parameters['q'] = "title contains '".$filename."'";
			$files = $service->files->listFiles($parameters);
			$result = array_merge($result, $files->getItems());
			$pageToken = $files->getNextPageToken();
		} catch (Exception $e) {
			print "An error occurred: " . $e->getMessage();
			$pageToken = NULL;
		}
	} while ($pageToken);

	if (sizeof($result) <= 0) {
		echo __FUNCTION__.": file not found\n";
		return null;
	} else {
		echo __FUNCTION__.": file found with id: ".$result[0]['id']."\n";
		return $result[0]['id'];
	}
}

function updateFile($service, $fileId, $newTitle, $newDescription, $newMimeType, $newFileName, $newRevision) {
	try {
		// First retrieve the file from the API.
		$file = $service->files->get($fileId);

		// File's new metadata.
		$file->setTitle($newTitle);
		$file->setDescription($newDescription);
		$file->setMimeType($newMimeType);

		// File's new content.
		$data = file_get_contents($newFileName);

		$additionalParams = array(
        		'newRevision' => $newRevision,
        		'data' => $data,
        		'mimeType' => $newMimeType,
      			'uploadType' => 'multipart',
   		 );

    		// Send the request to the API.
    		$updatedFile = $service->files->update($fileId, $file, $additionalParams);
    		return $updatedFile;
  	} catch (Exception $e) {
    		print "An error occurred: " . $e->getMessage();
  	}
}
