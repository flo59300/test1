<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/server/controllers/UserController.class.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/server/controllers/PostController.class.php';

header('Content-Type: application/json; charset=utf-8');

$response = array("response" => 400, "data" => array("message" => "Fields are empty."));

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	if (!empty($_POST['postTitle'])) {
		if (!empty($_POST['postBody']) && !empty($_FILES['postImage']) && !empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([1, $_POST['postTitle'], $_POST['postBody'], $_FILES['postImage'], $_POST['postYoutubeLink'], $_POST['threadUrl']]);
		} else if (!empty($_POST['postBody']) && !empty($_FILES['postImage']) && empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([2, $_POST['postTitle'], $_POST['postBody'], $_FILES['postImage'], $_POST['threadUrl']]);
		} else if (!empty($_POST['postBody']) && empty($_FILES['postImage']) && !empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([3, $_POST['postTitle'], $_POST['postBody'], $_POST['postYoutubeLink'], $_POST['threadUrl']]);
		} else if (empty($_POST['postBody']) && !empty($_FILES['postImage']) && !empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([4, $_POST['postTitle'], $_FILES['postImage'], $_POST['postYoutubeLink'], $_POST['threadUrl']]);
		} else if (!empty($_POST['postBody']) && empty($_FILES['postImage']) && empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([5, $_POST['postTitle'], $_POST['postBody'], $_POST['threadUrl']]);
		} else if (empty($_POST['postBody']) && !empty($_FILES['postImage']) && empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([6, $_POST['postTitle'], $_FILES['postImage'], $_POST['threadUrl']]);
		} else if (empty($_POST['postBody']) && empty($_FILES['postImage']) && !empty($_POST['postYoutubeLink'])) {
			$response = (new PostMiddleware())->createPost([7, $_POST['postTitle'], $_POST['postYoutubeLink'], $_POST['threadUrl']]);
		} 
	} else if (!empty($_POST['postId']) && !empty($_POST['type']) && ($_POST['type'] === "voteUp" || $_POST['type'] === "voteDown")) {
		$response = (new PostMiddleware())->vote([$_POST['postId'], $_POST['type']]);
	} else if (!empty($_POST['postId']) && isset($_POST['postDelete'])) {
		$response = (new PostMiddleware())->removePost($_POST['postId']);
	} else if (!empty($_POST['postId']) && (bool)$_POST['hidePost'] == true && ($_POST['buttonText'] == "hide" || $_POST['buttonText'] == "unhide")) {
		$response = (new PostMiddleware())->hidePost([$_POST['postId'], $_POST['buttonText']]);
	}
} else if ($_SERVER['REQUEST_METHOD'] === "GET") {
	if(!empty($_GET['threadUrl']) && !empty($_GET['postId'])) {
		$response = (new PostController())->loadSpecificPost([$_GET['threadUrl'], $_GET['postId']]);
	} else {
		if (!empty($_GET['threadUrl']) && !empty($_GET['sortType'])) {
			$response = (new PostMiddleware())->sortPosts([$_GET['threadUrl'], $_GET['sortType']]);
		} else if (!empty($_GET['query']) && !empty($_GET['threadUrl']) && isset($_GET['postSearch']) && (bool) $_GET['postSearch']) {
			$response = (new PostMiddleware())->searchPostsInThread([$_GET['query'], $_GET['threadUrl']]);
		} else if (!empty($_GET['threadUrl']) && empty($_GET['sortType'])) {
			$response = (new PostMiddleware())->sortPosts([$_GET['threadUrl']]);
		} else if (!empty($_GET['query']) && isset($_GET['postSearch']) && (bool) $_GET['postSearch']) {
			$response = (new PostMiddleware())->searchPosts([$_GET['query']]);
		} else if (empty($_GET['query'])) {
			$response =(new PostController())->loadAllPosts();
		}
	}
}

class PostMiddleware {
    
  public function isLogged() : bool {
		if (isset($_SESSION['IS_AUTHORIZED'])) return true;
		return false;
	}

	public function searchPosts(array $params) : array {

		$query = filter_var($params[0], FILTER_SANITIZE_STRING);
		$query = trim($query);
		$query = stripslashes($query);
		$query = htmlspecialchars($query);

		return (new PostController())->getPostByQuery($query);
	}

	public function searchPostsInThread(array $params) : array {
		if (!$this->isLogged()) return array("response" => 403);
		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
	
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
		
		$query = filter_var($params[0], FILTER_SANITIZE_STRING);
		$query = trim($query);
		$query = stripslashes($query);
		$query = htmlspecialchars($query);

		return (new PostController())->searchPostByQueryInThread([$query, $params[1]]);
	}

	public function vote(array $params) : array {
		if (!$this->isLogged()) return array("response" => 403);
		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
	
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
		
		$postId = intval($params[0]);
		if ($postId <= 0) return array("response" => 403);

		if (!(new PostController())->isExist($postId)) return array("response" => 403);

		if ($params[1] === "voteUp" || $params[1] === "voteDown")
			return (new PostController())->vote([$postId, $params[1]]);
		
		return array("response" => 403);
	}

	public function createPost(array $params) : array {
		if (!$this->isLogged()) return array("response" => 403);

		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
	
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
			
		$postTitle = $params[1];
		$threadUrl = end($params);

		if (empty($postTitle)) {
			return array("response" => 400, "data" => array("message" => "Post title cannot be empty."));
		}

		if (!preg_match("/^[a-zA-Z0-9\s]+$/", $postTitle)) {
			return array("response" => 400, "data" => array("message" => "The thread title cannot contain special characters."));
		}

		if (strlen($postTitle) > 75) {
			return array("response" => 400, "data" => array("message" => "The post title cannot be longer than 75 characters."));
		}

		if (strlen($postTitle) < 5) {
			return array("response" => 400, "data" => array("message" => "The post title cannot be shorter than 5 characters."));
		}

		$caseNumber = $params[0];
		switch ($caseNumber) {
			case 1:
				$postBody = htmlspecialchars($params[2]);
				$postImage = $params[3];
				$youtubeLink = $params[4];

				if ($postImage['size'] == 0 || $postImage['size'] > (5 * 1024 * 1024)) {
						return array("response" => 400, "data" => array("message" => "The post image must be less than 5MB."));
				}

				$targetDir = $_SERVER["DOCUMENT_ROOT"].'/server/uploads/post_images/';
				$imgFileType = strtolower(pathinfo($postImage['name'], PATHINFO_EXTENSION));

				if ($imgFileType != "jpg" && $imgFileType != "png" && $imgFileType != "gif")
			return array("response" => 400, "data" => array("message" => "Only .jpg, .png, and .gif format accepted."));
				
				$imgFile = "";
				$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				for ($i = 0; $i < 16; $i++)
						$imgFile .= $characters[mt_rand(0, 61)];
				
				$targetFile = $targetDir . 
												basename($imgFile.'.'.
												strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)));
				
				move_uploaded_file($postImage["tmp_name"], $targetFile);


				if (strlen($youtubeLink) > 0 && !preg_match("/^(https|http):\/\/(?:www\.)?youtube.com\/embed\/[A-z0-9]+$/", $youtubeLink)) {
						return array("response" => 400, "data" => array("message" => "The YouTube link is not valid. It should contain \"embed\" in the link."));
				}

				return (new PostController())->post([
						$caseNumber,
						$postTitle,
						$postBody,
						$imgFile . '.' . strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)),
						$youtubeLink,
						$threadUrl
				]);
		
			case 2:
				$postBody =  htmlspecialchars($params[2]);
				$postImage = $params[3];
				
				if ($postImage['size'] == 0 || $postImage['size'] > (5 * 1024 * 1024)) {
						return array("response" => 400, "data" => array("message" => "The post image must be less than 5MB."));
				}

				$targetDir = $_SERVER["DOCUMENT_ROOT"].'/server/uploads/post_images/';
				$imgFileType = strtolower(pathinfo($postImage['name'], PATHINFO_EXTENSION));

				if ($imgFileType != "jpg" && $imgFileType != "png" && $imgFileType != "gif")
			return array("response" => 400, "data" => array("message" => "Only .jpg, .png, and .gif format accepted."));
				
				$imgFile = "";
				$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				for ($i = 0; $i < 16; $i++)
						$imgFile .= $characters[mt_rand(0, 61)];
				
				$targetFile = $targetDir . 
												basename($imgFile.'.'.
												strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)));
				
				move_uploaded_file($postImage["tmp_name"], $targetFile);

				return (new PostController())->post([
						$caseNumber,
						$postTitle,
						$postBody,
						$imgFile . '.' . strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)),
						$threadUrl
				]);
			
			case 3:
				$postBody =  htmlspecialchars($params[2]);
				$youtubeLink = $params[3];

				if (strlen($youtubeLink) > 0 && !preg_match("/^(?:https?:\/\/)?(?:m\.|www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/", $youtubeLink)) {
						return array("response" => 400, "data" => array("message" => "The YouTube link is not valid."));
				}

				return (new PostController())->post([
						$caseNumber,
						$postTitle,
						$postBody,
						$youtubeLink,
						$threadUrl
				]);
		
			case 4:
				$postImage = $params[2];
				$youtubeLink = $params[3];

				if ($postImage['size'] == 0 || $postImage['size'] > (5 * 1024 * 1024)) {
						return array("response" => 400, "data" => array("message" => "The post image must be less than 5MB."));
				}

				$targetDir = $_SERVER["DOCUMENT_ROOT"].'/server/uploads/post_images/';
				$imgFileType = strtolower(pathinfo($postImage['name'], PATHINFO_EXTENSION));

				if ($imgFileType != "jpg" && $imgFileType != "png" && $imgFileType != "gif") {
					return array("response" => 400, "data" => array("message" => "Only .jpg, .png, and .gif format accepted."));
				}
				$imgFile = "";
				$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				for ($i = 0; $i < 16; $i++)
					$imgFile .= $characters[mt_rand(0, 61)];
				
				$targetFile = $targetDir . 
												basename($imgFile.'.'.
												strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)));
				
				move_uploaded_file($postImage["tmp_name"], $targetFile);
				
				if (strlen($youtubeLink) > 0 && !preg_match("/^(?:https?:\/\/)?(?:m\.|www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/", $youtubeLink)) {
					return array("response" => 400, "data" => array("message" => "The YouTube link is not valid."));
				}

				return (new PostController())->post([
					$caseNumber,
					$postTitle,
					$imgFile . '.' . strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)),
					$youtubeLink,
					$threadUrl
				]);
		
			case 5:
				return (new PostController())->post([
					$caseNumber,
					$postTitle,
					$params[2],
					$threadUrl
				]);
					
			case 6:
				$postImage = $params[2];
				if ($postImage['size'] == 0 || $postImage['size'] > (5 * 1024 * 1024)) {
						return array("response" => 400, "data" => array("message" => "The post image must be less than 5MB."));
				}

				$targetDir = $_SERVER["DOCUMENT_ROOT"].'/server/uploads/post_images/';
				$imgFileType = strtolower(pathinfo($postImage['name'], PATHINFO_EXTENSION));

				if ($imgFileType != "jpg" && $imgFileType != "png" && $imgFileType != "gif")
			return array("response" => 400, "data" => array("message" => "Only .jpg, .png, and .gif format accepted."));
				
				$imgFile = "";
				$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				for ($i = 0; $i < 16; $i++)
						$imgFile .= $characters[mt_rand(0, 61)];
				
				$targetFile = $targetDir . basename($imgFile.'.'. strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)));
				
				move_uploaded_file($postImage["tmp_name"], $targetFile);

				return (new PostController())->post([
						$caseNumber,
						$postTitle,
						$imgFile . '.' . strtolower(pathinfo($postImage["name"], PATHINFO_EXTENSION)),
						$threadUrl
			]);

			case 7:
				$youtubeLink = $params[2];
				if (strlen($youtubeLink) > 0 && !preg_match("/^(?:https?:\/\/)?(?:m\.|www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/", $youtubeLink)) {
					return array("response" => 400, "data" => array("message" => "The YouTube link is not valid."));
				}

				return (new PostController())->post([
					$caseNumber,
					$postTitle,
					$youtubeLink,
					$threadUrl
			]);
		}
	}

	public function sortPosts(array $params) {
		if (empty($params[0])) {
			return array("response" => 400, "data" => array("message" => "You must click a sort button in a valid thread."));
		}

		if (!empty($params[1]) && !($params[1] == "Top" || $params[1] == "New")) {
			return array("response" => 400, "data" => array("message" => "You must click a sort button in a valid thread."));
		}

		if (empty($params[1])) {
			return (new PostController())->loadPostByThread([$params[0]]);
		}

		return (new PostController())->loadPostByThread([$params[0], $params[1]]);
	}

	public function removePost(int $postId) : array {
		if (!$this->isLogged()) return array("response" => 403);
		
		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
	
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
		
		if (empty($postId)) {
			return array("response" => 400, "data" => array("message" => "You must click a valid delete button in a valid thread of a valid post."));
		} 

		if ($postId <= 0) {
			return array("response" => 400, "data" => array("message" => "You must click a valid delete button in a valid thread of a valid post."));
		}

		return (new PostController())->deletePost([$postId]);
	}

	public function hidePost(array $params) : array {
		$postId = $params[0];
		$buttonText = $params[1];
		if (!$this->isLogged()) return array("response" => 403);
		
		if (!(new UserController())->isEmailConfirmedByUserName($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Email is not verified."));
	
		if ((new UserController())->isAccountDisabled($_SESSION['USERNAME'])) return array( "response" => 400, "data" => array("message" => "Unathorized attempt. Account is disabled."));
		
		if (empty($postId)) {
			return array("response" => 400, "data" => array("message" => "You must click a valid delete button in a valid thread of a valid post."));
		} 

		if ($postId <= 0) {
			return array("response" => 400, "data" => array("message" => "You must click a valid delete button in a valid thread of a valid post."));
		}
		return (new PostController())->disablePost([$postId, $buttonText]);
	}
}

$response = json_encode($response, true);
echo $response;
?>