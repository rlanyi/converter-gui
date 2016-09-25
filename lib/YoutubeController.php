<?php

class YoutubeController {
	protected $ci;
	protected $client;

	public function __construct(Slim\Container $ci) {
		$this->ci = $ci;

		$this->client = new Google_Client();
//		$this->client->setClientId($ci['settings']['youtube']['OAUTH2_CLIENT_ID']);
//		$this->client->setClientSecret($ci['settings']['youtube']['OAUTH2_CLIENT_SECRET']);
		$this->client->setClientId('931300091879-sb09dfhd0fp78sfff9513i1kksmva6eo.apps.googleusercontent.com');
		$this->client->setClientSecret('KEn74Hqr0LX7p888JhBtIa-2');
		$this->client->setScopes('https://www.googleapis.com/auth/youtube');
		$this->client->setAccessType("offline");
	}

	function authorize($request, $response) {
		$code = $request->getQueryParam('code');
		$state = $request->getQueryParam('state');

		$tokenSessionKey = 'token-' . $this->client->prepareScopes();

		if (!is_null($state)) {
			if (strval($_SESSION['state']) !== strval($state)) {
				throw new Exception('The session state did not match.');
			}

			$this->client->setRedirectUri($request->getQueryParam('original-uri'));
			var_dump($this->client->authenticate($code));
			$_SESSION[$tokenSessionKey] = $this->client->getAccessToken();
			var_dump($this->client->getAccessToken());
			die();
			return $this->redirect($request, $response, $request->getQueryParam('original-uri'));
		}

		// If the user hasn't authorized the app, initiate the OAuth flow
		$state = mt_rand();
		$this->client->setState($state);
		$_SESSION['state'] = $state;

		// Google URL for authentication
		$redirectUri = filter_var("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
			FILTER_SANITIZE_URL);
		$this->client->setRedirectUri($request->getQueryParam('original-uri'));
		$authUrl = $this->client->createAuthUrl();

		return $this->ci->view->render($response, 'youtube/authorize.html', [
			'authUrl' => $authUrl
			]);
	}

	public function updateMeta($request, $response, $args) {
		if (!$this->prepare()) {
			return $this->redirect($request, $response);
		}

		return $this->ci->view->render($response, 'videos/list.html', [
			'videos' => $videos
			]);
	}

	private function prepare() {
		// Define an object that will be used to make all API requests.
		$youtube = new Google_Service_YouTube($this->client);

		// Check if an auth token exists for the required scopes
		$tokenSessionKey = 'token-' . $this->client->prepareScopes();
		if (isset($_GET['code'])) {
			if (strval($_SESSION['state']) !== strval($_GET['state'])) {
				var_dump($_SESSION['state'], $_GET['state']);
				die('The session state did not match.');
			}

			$this->client->authenticate($_GET['code']);
			$_SESSION[$tokenSessionKey] = $this->client->getAccessToken();
			header('Location: ' . $this->redirect);
		}

		if (isset($_SESSION[$tokenSessionKey])) {
			$this->client->setAccessToken($_SESSION[$tokenSessionKey]);
		}

		if ($this->client->getAccessToken()) {
			$_SESSION[$tokenSessionKey] = $this->client->getAccessToken();
			return true;
		} else {
			return false;
		}
	}

	function redirect($request, $response, $to = null) {
		if (null !== $to) {
			return $response->withRedirect($to);
		}

		$originaluri = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		return $response->withRedirect($this->ci->router->pathFor('youtube-authorize', [], ['original-uri' => $originaluri]));
	}

}
