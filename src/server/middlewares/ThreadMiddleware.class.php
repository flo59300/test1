<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/server/controllers/UserController.class.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/server/controllers/ThreadController.class.php';

header('Content-Type: application/json; charset=utf-8');

$response = array("response" => 400, "data" => array("message" => "Fields are empty."));

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	if (!empty($_POST['title']) && !empty($_POST['url']) && !empty($_FILES)) {
		$response = (new ThreadMiddleware())->createThread([$_POST['title'], $_POST['url'], $_FILES['threadBackground'], $_FILES['threadProfile']]);
	} else if (!empty($_POST['threadUrl']) && ($_POST['dataStatus']) == 0 || ($_POST['dataStatus']) == 1) {
		$response = (new ThreadMiddleware())->updateUserThreads([$_POST['threadUrl'], $_POST['dataStatus']]);
	}
} 

if ($_SERVER['REQUEST_METHOD'] === "GET") {
	if (!empty($_GET['query']) && isset($_GET['threadSearch']) && (bool) $_GET['threadSearch']) {
		$response = (new ThreadMiddleware())->searchThreads([$_GET['query']]);
	} else if (empty($_GET['query'])) {
		$response =(new ThreadController())->getAllThreads();
	}
}


class ThreadMiddleware {
    
  public function isLogged() : bool {
		if (isset($_SESSION['IS_AUTHORIZED'])) return true;
		return false;
	}
	
	public function searchThreads(array $params) : array {
		if (!is_string($params[0])) return array( "response" => 400, "data" => array("message" => "Invalid information"));

		$query = filter_var($params[0], FILTER_SANITIZE_STRING);
		$query = trim($query);
		$query = stripslashes($query);
		$query = htmlspecialchars($query);
		
		return (new ThreadController())->getThreadByQuery($query);
	}

	public function createThread(array $params) : array {
		if (!$this->isLogged()) return array("response" => 403);

		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
		
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
		
		$threadTitle = $params[0];
		$threadUrl = $params[1];
		$threadBackground = $params[2];
		$threadProfile = $params[3];

		if (empty($threadTitle)) {
			return array("response" => 400, "data" => array("message" => "Thread title cannot be empty."));
		}

		if (!preg_match("/^[a-zA-Z0-9\s]+$/", $threadTitle)) {
			return array("response" => 400, "data" => array("message" => "The thread title cannot contain special characters."));
		}

		if (strlen($threadTitle) > 12) {
			return array("response" => 400, "data" => array("message" => "The thread title cannot be longer than 12 characters."));
		}

		$toCheckURL = str_replace("-", " ", $threadUrl);
		if (strtolower($toCheckURL) != strtolower($threadTitle)) {
			return array("response" => 400, "data" => array("message" => "The thread URL must match the thread title."));
		}

		if ($threadBackground['size'] == 0 || $threadProfile['size'] > (5 * 1024 * 1024)) {
			return array("response" => 400, "data" => array("message" => "The thread background image must be less than 5MB."));
		}

		if ($threadProfile['size'] == 0 || $threadProfile['size'] > (5 * 1024 * 1024)) {
			return array("response" => 400, "data" => array("message" => "The thread profile image must be less than 5MB."));
		}

		$targetDirThreadBackground = $_SERVER["DOCUMENT_ROOT"].'/server/uploads/thread_backgrounds/';
		$targetDirThreadProfile = $_SERVER["DOCUMENT_ROOT"].'/server/uploads/thread_profile/';

		$threadBackgroundFileType = strtolower(pathinfo($threadBackground['name'], PATHINFO_EXTENSION));
		$threadProfileFileType = strtolower(pathinfo($threadProfile['name'], PATHINFO_EXTENSION));

		if ($threadBackgroundFileType != "jpg" && $threadBackgroundFileType != "png" && $threadBackgroundFileType != "gif")
			return array("response" => 400, "data" => array("message" => "Only .jpg, .png, and .gif format accepted."));
		
		if ($threadProfileFileType != "jpg" && $threadProfileFileType != "png" && $threadProfileFileType != "gif")
			return array("response" => 400, "data" => array("message" => "Only .jpg, .png, and .gif format accepted."));

		$threadBackgroundFile = "";
		$threadProfileFile = "";
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		for ($i = 0; $i < 16; $i++) {
			$threadBackgroundFile .= $characters[mt_rand(0, 61)];
			$threadProfileFile .= $characters[mt_rand(0, 61)];
		}

		$targetThreadBackgroundFile = $targetDirThreadBackground . 
			basename($threadBackgroundFile.'.'.strtolower(pathinfo($threadBackground["name"], PATHINFO_EXTENSION)));
		
		$targetThreadProfileFile = $targetDirThreadProfile . 
			basename($threadProfileFile.'.'.strtolower(pathinfo($threadProfile["name"], PATHINFO_EXTENSION)));
		
		move_uploaded_file($threadBackground["tmp_name"], $targetThreadBackgroundFile);
		move_uploaded_file($threadProfile["tmp_name"], $targetThreadProfileFile);

		return (new ThreadController())->post([
			$threadTitle, $threadUrl, 
			$threadBackgroundFile.'.'.$threadBackgroundFileType, 
			$threadProfileFile.'.'.$threadProfileFileType
			]);
    }

	public function updateUserThreads(array $params) {
		if (!$this->isLogged()) return array("response" => 403);

		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
		
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
		
		return (new ThreadController())->userThreadsOperations([$params[0], $params[1]]);
	}
	
}

$response = json_encode($response, true);
echo $response;
?>