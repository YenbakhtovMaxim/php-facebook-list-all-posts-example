<?php
// Сессии обязательно включить
if(!session_id()) {
    session_start();
}
require_once __DIR__ . '/src/Facebook/autoload.php'; // download official fb sdk for php @ https://github.com/facebook/php-graph-sdk

$app_id = "1601729266580492"; // id приложения
$app_secret = "ee2f8ffe2dfba5c0dae47f22e067af3a"; // секрет приложения
$page_id = 'skcukz'; // id страницы, с которой будем брать посты
define("CURRENT_PAGE_ADDR",'http://docs.com/test/test2.php'); // Полный текущий адрес страницы, что бы вернуться на нее после запроса в facebook

	
$fb = new \Facebook\Facebook(array(
	  'app_id' => $app_id,
	  'app_secret' => $app_secret,
	  'default_graph_version' => 'v2.2',
	));

// Проверяем получили ли ответ от faccebook
if(isset($_GET['code'])){
	
	$helper = $fb->getRedirectLoginHelper();
	
	// Проверяем есть ли в сессии токен
	if (isset($_SESSION['facebook_access_token'])) {
		$accessToken = $_SESSION['facebook_access_token']; // Достаем из сессии
	}else{
		try {
		  $accessToken = $helper->getAccessToken(); // получаем первоначальный токен
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
		  // When Graph returns an error
		  echo 'Graph returned an error: ' . $e->getMessage();
		  exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
		  // When validation fails or other local issues
		  echo 'Facebook SDK returned an error: ' . $e->getMessage();
		  exit;
		}
		
		$oAuth2Client = $fb->getOAuth2Client();

		// Get the access token metadata from /debug_token
		$tokenMetadata = $oAuth2Client->debugToken($accessToken);
		// echo '<h3>Metadata</h3>';
		// var_dump($tokenMetadata);

		// Validation (these will throw FacebookSDKException's when they fail)
		$tokenMetadata->validateAppId($app_id); // Replace {app-id} with your app id
		// If you know the user ID this access token belongs to, you can validate it here
		//$tokenMetadata->validateUserId('123');
		$tokenMetadata->validateExpiration();

		if (! $accessToken->isLongLived()) {
		  // Exchanges a short-lived access token for a long-lived one
		  try {
			$accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken); // Получаем долговечный токен
			//echo"<pre>";print_r($accessToken);exit;
			$accessToken = $accessToken->getValue();
			$_SESSION['facebook_access_token'] = $accessToken; // Добавляем в сессию
		  } catch (Facebook\Exceptions\FacebookSDKException $e) {
			echo "<p>Error getting long-lived access token: " . $helper->getMessage() . "</p>\n\n";
			exit;
		  }
		}
	}
	
	if($accessToken){
		$fb->setDefaultAccessToken($accessToken);
		// getting all posts published by user
		try {
			$posts_request = $fb->get("/{$page_id}/feed?fields=created_time,message,full_picture,permalink_url&limit=100");
			//echo"<pre>";print_r($posts_request);exit;
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			// When Graph returns an error
			// Если вышел срок переполучаем токен
			//echo"<pre>";print_r($e->getCode());exit;
			if (in_array($e->getCode(),[190,100])) {
				unset($_SESSION['facebook_access_token']);
				header("Location: "._getFacebookUpdateTokenLink($app_id,$app_secret,$fb));
			}else{
				echo 'Graph returned an error: ' . $e->getMessage();
			}
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			// When validation fails or other local issues
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}
		echo"<pre>";print_r($posts_request->getDecodedBody()['data']);exit; // Выводим массив с постами
	}

}else{
	header("Location: "._getFacebookUpdateTokenLink($app_id,$app_secret,$fb));
	die();
}

function _getFacebookUpdateTokenLink($app_id,$app_secret,$fb)
{	
	$helper = $fb->getRedirectLoginHelper();
	$permissions = ['email'];
	$loginUrl = $helper->getLoginUrl(CURRENT_PAGE_ADDR, $permissions);
	return $loginUrl;
}