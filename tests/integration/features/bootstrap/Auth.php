<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;

require __DIR__ . '/../../../../lib/composer/autoload.php';

trait Auth {

	private $clientToken;

	/** @BeforeScenario */
	public function setUpScenario() {
		$this->client = new Client();
		$this->responseXml = '';
	}

	/**
	 * @When requesting :url with :method
	 */
	public function requestingWith($url, $method) {
		$this->sendRequest($url, $method);
	}

	private function sendRequest($url, $method, $authHeader = null, $useCookies = false) {
		$fullUrl = substr($this->baseUrl, 0, -5) . $url;
		$options = [];
		try {
			$headers = [
				'OCS_APIREQUEST' => 'true',
				'requesttoken' => $this->requestToken
			];
			if ($authHeader) {
				$headers['Authorization'] = $authHeader;
			}
			if ($useCookies) {
				$options = [
				    'cookies' => $this->cookieJar,
				];
			}
			if ($authHeader) {
				$headers['Authorization'] = $authHeader;
			}
			$headers['OCS_APIREQUEST'] = 'true';
			$headers['requesttoken'] = $this->requestToken;
			$request = new Request($method, $fullUrl, $headers);
			$this->response = $this->client->send($request, $options);
		} catch (BadResponseException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @Given a new client token is used for user :user
	 * @param string $user
	 */
	public function aNewClientTokenIsUsed($user) {
		$client = new Client();
		$resp = $client->post(substr($this->baseUrl, 0, -5) . '/token/generate', [
			'json' => [
				'user' => $user,
				'password' => $this->getPasswordForUser($user),
			]
		]);
		$this->clientToken = json_decode($resp->getBody()->getContents())->token;
	}

	/**
	 * @When requesting :url with :method using basic auth for user :user
	 */
	public function requestingWithBasicAuth($url, $method, $user) {
		$authString = $user . ':' . $this->getPasswordForUser($user);
		$this->sendRequest($url, $method, 'basic ' . base64_encode($authString));
	}

	/**
	 * @When requesting :url with :method using basic token auth
	 */
	public function requestingWithBasicTokenAuth($url, $method) {
		$this->sendRequest($url, $method, 'basic ' . base64_encode('user0:' . $this->clientToken));
	}

	/**
	 * @When requesting :url with :method using a client token
	 */
	public function requestingWithUsingAClientToken($url, $method) {
		$this->sendRequest($url, $method, 'token ' . $this->clientToken);
	}

	/**
	 * @When requesting :url with :method using browser session
	 */
	public function requestingWithBrowserSession($url, $method) {
		$this->sendRequest($url, $method, null, true);
	}

	/**
	 * @Given a new browser session is started for user :user
	 * @param string $user
	 */
	public function aNewBrowserSessionIsStarted($user) {
		$loginUrl = substr($this->baseUrl, 0, -5) . '/login';
		// Request a new session and extract CSRF token
		$client = new Client();
		$response = $client->get(
			$loginUrl, [
		    'cookies' => $this->cookieJar,
			]
		);
		$this->extracRequestTokenFromResponse($response);

		// Login and extract new token
		$client = new Client();
		$response = $client->post(
			$loginUrl, [
				'form_params' => [
					'user' => $user,
					'password' => $this->getPasswordForUser($user),
					'requesttoken' => $this->requestToken,
				],
				'cookies' => $this->cookieJar,
			]
		);
		$this->extracRequestTokenFromResponse($response);
	}

}
