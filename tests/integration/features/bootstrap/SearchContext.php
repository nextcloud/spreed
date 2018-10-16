<?php

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Message\ResponseInterface;

class SearchContext implements Context {

	/** @var string */
	private $baseUrl = '';

	/** @var ResponseInterface */
	private $response = null;

	public function __construct(string $baseUrl, array $admin, string $regularUserPassword) {
		$this->baseUrl = $baseUrl;
		$this->adminUser = $admin;
		$this->regularUserPassword = $regularUserPassword;

		// In case of CI deployment we take the server url from the environment
		$testServerUrl = getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = $testServerUrl;
		}
	}

	/**
	 * @When user :user searches for :query in chat messages
	 *
	 * @param string $user
	 * @param string $query
	 */
	public function userSearchesForInChatMessages(string $user, string $query) {
		// Search endpoint is not an OCS endpoint, so a request token must be
		// provided.
		list($requestToken, $cookieJar) = $this->loggingInUsingWebAs($user);

		$url = '/index.php/core/search';

		$parameters[] = 'query=' . $query;
		$parameters[] = 'inApps[]=spreed';

		$url .= '?' . implode('&', $parameters);

		$this->sendingToWithRequestToken('GET', $url, $requestToken, $cookieJar);
	}

	/**
	 * @Then the list of search results has :count results
	 */
	public function theListOfSearchResultsHasResults(int $count) {
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());

		$searchResults = json_decode($this->response->getBody());

		PHPUnit_Framework_Assert::assertEquals($count, count($searchResults));
	}

	/**
	 * @Then search result :number contains
	 *
	 * @param int $number
	 * @param TableNode $body
	 */
	public function searchResultXContains(int $number, TableNode $body) {
		if (!($body instanceof TableNode)) {
			return;
		}

		$searchResults = json_decode($this->response->getBody(), $asAssociativeArray = true);
		$searchResult = $searchResults[$number];

		$defaultExpectedFields = [
			'type' => 'chat-message',
			'id' => 'A_NUMBER',
			'timestamp' => 'A_NUMBER'
		];
		$expectedFields = array_merge($defaultExpectedFields, $body->getRowsHash());

		if (array_key_exists('room', $expectedFields)) {
			$expectedFields['token'] = FeatureContext::getTokenForIdentifier($expectedFields['room']);
			unset($expectedFields['room']);
		}

		if (!array_key_exists('actorDisplayName', $expectedFields) &&
				array_key_exists('actorId', $expectedFields)) {
			$expectedFields['actorDisplayName'] = $expectedFields['actorId'] . '-displayname';
		}

		if (array_key_exists('actorType', $expectedFields) &&
				$expectedFields['actorType'] === 'guests' &&
				array_key_exists('actorId', $expectedFields)) {
			$expectedFields['actorId'] = FeatureContext::getSessionIdForUser($expectedFields['actorId']);
		}

		if (!array_key_exists('relevantMessagePart', $expectedFields) &&
				array_key_exists('name', $expectedFields)) {
			$expectedFields['relevantMessagePart'] = $expectedFields['name'];
		}

		foreach ($expectedFields as $expectedField => $expectedValue) {
			$this->assertFieldIsInReturnedSearchResult($expectedField, $expectedValue, $searchResult);
		}
	}

	/**
	 * @param string $verb
	 * @param string $url
	 * @param string $requestToken
	 * @param CookieJar $cookieJar
	 */
	private function sendingToWithRequestToken(string $verb, string $url, string $requestToken, CookieJar $cookieJar) {
		$fullUrl = $this->baseUrl . $url;

		$client = new Client();
		try {
			$this->response = $client->send($client->createRequest(
				$verb,
				$fullUrl,
				[
					'cookies' => $cookieJar,
					'headers' => [
						'requesttoken' => $requestToken
					]
				]
			));
		} catch (GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @param ResponseInterface $response
	 * @return string
	 */
	private function extractRequestTokenFromResponse(ResponseInterface $response): string {
		return substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);
	}

	/**
	 * @param string $user
	 */
	private function loggingInUsingWebAs(string $user) {
		$loginUrl = $this->baseUrl . '/login';

		$cookieJar = new CookieJar();

		// Request a new session and extract CSRF token
		$client = new Client();
		$response = $client->get(
			$loginUrl,
			[
				'cookies' => $cookieJar,
			]
		);
		$requestToken = $this->extractRequestTokenFromResponse($response);

		// Login and extract new token
		$password = ($user === 'admin') ? $this->adminUser[1] : $this->regularUserPassword;
		$client = new Client();
		$response = $client->post(
			$loginUrl,
			[
				'body' => [
					'user' => $user,
					'password' => $password,
					'requesttoken' => $requestToken,
				],
				'cookies' => $cookieJar,
			]
		);
		$requestToken = $this->extractRequestTokenFromResponse($response);

		return [$requestToken, $cookieJar];
	}

	/**
	 * @param string $field
	 * @param string $contentExpected
	 * @param array $returnedSearchResult
	 */
	private function assertFieldIsInReturnedSearchResult(string $field, string $contentExpected, array $returnedSearchResult){
		if (!array_key_exists($field, $returnedSearchResult)) {
			PHPUnit_Framework_Assert::fail("$field was not found in response");
		}

		if ($contentExpected === 'A_NUMBER') {
			PHPUnit_Framework_Assert::assertTrue(is_numeric((string)$returnedSearchResult[$field]), "Field '$field' is not a number: " . $returnedSearchResult[$field]);
		} else {
			PHPUnit_Framework_Assert::assertEquals($contentExpected, (string)$returnedSearchResult[$field], "Field '$field' does not match");
		}
	}

}
