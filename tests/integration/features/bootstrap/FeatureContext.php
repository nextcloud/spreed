<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
require __DIR__ . '/../../vendor/autoload.php';

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext {

	/** @var array[] */
	protected static $identifierToToken;
	/** @var array[] */
	protected static $tokenToIdentifier;
	/** @var array[] */
	protected static $sessionIdToUser;

	/** @var string */
	protected $currentUser;

	/** @var ResponseInterface */
	private $response;

	/** @var CookieJar[] */
	private $cookieJars;

	/** @var string */
	protected $baseUrl;

	/** @var string */
	protected $lastEtag;

	/** @var array */
	protected $createdUsers = [];

	/** @var array */
	protected $createdGroups = [];

	public static function getTokenForIdentifier(string $identifier) {
		return self::$identifierToToken[$identifier];
	}

	/**
	 * FeatureContext constructor.
	 */
	public function __construct() {
		$this->cookieJars = [];
		$this->baseUrl = getenv('TEST_SERVER_URL');
	}

	/**
	 * @BeforeScenario
	 */
	public function setUp() {
		self::$identifierToToken = [];
		self::$tokenToIdentifier = [];
		self::$sessionIdToUser = [];

		$this->createdUsers = [];
		$this->createdGroups = [];
	}

	/**
	 * @AfterScenario
	 */
	public function tearDown() {
		foreach ($this->createdUsers as $user) {
			$this->deleteUser($user);
		}
		foreach ($this->createdGroups as $group) {
			$this->deleteGroup($group);
		}
	}

	/**
	 * @Then /^user "([^"]*)" is participant of the following rooms$/
	 *
	 * @param string $user
	 * @param TableNode|null $formData
	 */
	public function userIsParticipantOfRooms($user, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/room');
		$this->assertStatusCode($this->response, 200);

		$rooms = $this->getDataFromResponse($this->response);

		$rooms = array_filter($rooms, function($room) {
			return $room['type'] !== 4;
		});

		if ($formData === null) {
			PHPUnit_Framework_Assert::assertEmpty($rooms);
			return;
		}

		PHPUnit_Framework_Assert::assertCount(count($formData->getHash()), $rooms, 'Room count does not match');
		PHPUnit_Framework_Assert::assertEquals($formData->getHash(), array_map(function($room, $expectedRoom) {
			$participantNames = array_map(function($participant) {
				return $participant['name'];
			}, $room['participants']);

			// When participants have the same last ping the order in which they
			// are returned from the server is undefined. That is the most
			// common case during the tests, so by default the list of
			// participants returned by the server is sorted alphabetically. In
			// order to check the exact order of participants returned by the
			// server " [exact order]" can be appended in the test definition to
			// the list of expected participants of the room.
			if (strpos($expectedRoom['participants'], ' [exact order]') === false) {
				sort($participantNames);
			} else {
				// "end(array_keys(..." would generate the Strict Standards
				// error "Only variables should be passed by reference".
				$participantNamesKeys = array_keys($participantNames);
				$lastParticipantKey = end($participantNamesKeys);

				// Append " [exact order]" to the last participant so the
				// imploded string is the same as the expected one.
				$participantNames[$lastParticipantKey] .= ' [exact order]';
			}

			return [
				'id' => self::$tokenToIdentifier[$room['token']],
				'type' => (string) $room['type'],
				'participantType' => (string) $room['participantType'],
				'participants' => implode(', ', $participantNames),
			];
		}, $rooms, $formData->getHash()));
	}

	/**
	 * @Then /^user "([^"]*)" (is|is not) participant of room "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $isParticipant
	 * @param string $identifier
	 */
	public function userIsParticipantOfRoom($user, $isParticipant, $identifier) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/room');
		$this->assertStatusCode($this->response, 200);

		$isParticipant = $isParticipant === 'is';

		$rooms = $this->getDataFromResponse($this->response);

		$rooms = array_filter($rooms, function($room) {
			return $room['type'] !== 4;
		});

		if ($isParticipant) {
			PHPUnit_Framework_Assert::assertNotEmpty($rooms);
		}

		foreach ($rooms as $room) {
			if (self::$tokenToIdentifier[$room['token']] === $identifier) {
				PHPUnit_Framework_Assert::assertEquals($isParticipant, true, 'Room ' . $identifier . ' found in user´s room list');
				return;
			}
		}

		PHPUnit_Framework_Assert::assertEquals($isParticipant, false, 'Room ' . $identifier . ' not found in user´s room list');
	}

	/**
	 * @Then /^user "([^"]*)" creates room "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param TableNode|null $formData
	 */
	public function userCreatesRoom($user, $identifier, TableNode $formData = null) {
		$this->userCreatesRoomWith($user, $identifier, 201, $formData);
	}

	/**
	 * @Then /^user "([^"]*)" creates room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param TableNode|null $formData
	 */
	public function userCreatesRoomWith($user, $identifier, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/v1/room', $formData);
		$this->assertStatusCode($this->response, $statusCode);

		$response = $this->getDataFromResponse($this->response);

		if ($statusCode === 201) {
			self::$identifierToToken[$identifier] = $response['token'];
			self::$tokenToIdentifier[$response['token']] = $identifier;
		}
	}

	/**
	 * @Then /^user "([^"]*)" tries to create room with (\d+)$/
	 *
	 * @param string $user
	 * @param int $statusCode
	 * @param TableNode|null $formData
	 */
	public function userTriesToCreateRoom($user, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/v1/room', $formData);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" gets the room for path "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $path
	 * @param int $statusCode
	 */
	public function userGetsTheRoomForPath($user, $path, $statusCode) {
		$fileId = $this->getFileIdForPath($user, $path);

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/file/' . $fileId);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== '200') {
			return;
		}

		$response = $this->getDataFromResponse($this->response);

		$identifier = 'file ' . $path . ' room';
		self::$identifierToToken[$identifier] = $response['token'];
		self::$tokenToIdentifier[$response['token']] = $identifier;
	}

	/**
	 * @param string $user
	 * @param string $path
	 * @return int
	 */
	private function getFileIdForPath($user, $path) {
		$this->currentUser = $user;

		$url = "/$user/$path";

		$headers = [];
		$headers['Depth'] = 0;

		$body = '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">' .
				'	<d:prop>' .
				'		<oc:fileid/>' .
				'	</d:prop>' .
				'</d:propfind>';

		$this->sendingToDav('PROPFIND', $url, $headers, $body);

		$this->assertStatusCode($this->response, 207);

		$xmlResponse = simplexml_load_string($this->response->getBody());
		$xmlResponse->registerXPathNamespace('oc', 'http://owncloud.org/ns');

		return (int)$xmlResponse->xpath('//oc:fileid')[0];
	}

	/**
	 * @param string $verb
	 * @param string $url
	 * @param array $headers
	 * @param string $body
	 */
	private function sendingToDav(string $verb, string $url, array $headers = null, string $body = null) {
		$fullUrl = $this->baseUrl . "remote.php/dav/files" . $url;
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = 'admin';
		} else {
			$options['auth'] = [$this->currentUser, '123456'];
		}
		$options['headers'] = [
			'OCS_APIREQUEST' => 'true'
		];
		if ($headers !== null) {
			$options['headers'] = array_merge($options['headers'], $headers);
		}
		if ($body !== null) {
			$options['body'] = $body;
		}

		try {
			$this->response = $client->send($client->createRequest($verb, $fullUrl, $options));
		} catch (GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @Then /^user "([^"]*)" joins room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param TableNode|null $formData
	 */
	public function userJoinsRoom($user, $identifier, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/participants/active',
			$formData
		);
		$this->assertStatusCode($this->response, $statusCode);

		$response = $this->getDataFromResponse($this->response);
		if (array_key_exists('sessionId', $response)) {
			// In the chat guest users are identified by their sessionId. The
			// sessionId is larger than the size of the actorId column in the
			// database, though, so the ID stored in the database and returned
			// in chat messages is a hashed version instead.
			self::$sessionIdToUser[sha1($response['sessionId'])] = $user;
		}
	}

	/**
	 * @Then /^user "([^"]*)" leaves room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userExitsRoom($user, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/participants/active');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" removes themselves from room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userLeavesRoom($user, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/participants/self');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" removes "([^"]*)" from room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $toRemove
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userRemovesUserFromRoom($user, $toRemove, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/participants',
			new TableNode([['participant', $toRemove]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" deletes room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userDeletesRoom($user, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" renames room "([^"]*)" to "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $newName
	 * @param string $statusCode
	 */
	public function userRenamesRoom($user, $identifier, $newName, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier],
			new TableNode([['roomName', $newName]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets password "([^"]*)" for room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $identifier
	 * @param string $statusCode
	 * @param TableNode
	 */
	public function userSetsTheRoomPassword($user, $password, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/password',
			new TableNode([['password', $password]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" makes room "([^"]*)" (public|private) with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $newType
	 * @param string $statusCode
	 */
	public function userChangesTypeOfTheRoom($user, $identifier, $newType, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			$newType === 'public' ? 'POST' : 'DELETE',
			'/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/public'
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" (locks|unlocks) room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $newState
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userChangesReadOnlyStateOfTheRoom($user, $newState, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/read-only',
			new TableNode([['state', $newState === 'unlocks' ? 0 : 1]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" adds "([^"]*)" to room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $newUser
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userAddUserToRoom($user, $newUser, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/participants',
			new TableNode([['newParticipant', $newUser]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" (promotes|demotes) "([^"]*)" in room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $isPromotion
	 * @param string $participant
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userPromoteDemoteInRoom($user, $isPromotion, $participant, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			$isPromotion === 'promotes' ? 'POST' : 'DELETE',
			'/apps/spreed/api/v1/room/' . self::$identifierToToken[$identifier] . '/moderators',
			new TableNode([['participant', $participant]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" joins call "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param TableNode|null $formData
	 */
	public function userJoinsCall($user, $identifier, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/v1/call/' . self::$identifierToToken[$identifier],
			$formData
		);
		$this->assertStatusCode($this->response, $statusCode);

		$response = $this->getDataFromResponse($this->response);
		if (array_key_exists('sessionId', $response)) {
			// In the chat guest users are identified by their sessionId. The
			// sessionId is larger than the size of the actorId column in the
			// database, though, so the ID stored in the database and returned
			// in chat messages is a hashed version instead.
			self::$sessionIdToUser[sha1($response['sessionId'])] = $user;
		}
	}

	/**
	 * @Then /^user "([^"]*)" leaves call "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userLeavesCall($user, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/v1/call/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" sees (\d+) peers in call "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $numPeers
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userSeesPeersInCall($user, $numPeers, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/call/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode === '200') {
			$response = $this->getDataFromResponse($this->response);
			PHPUnit_Framework_Assert::assertCount((int) $numPeers, $response);
		} else {
			PHPUnit_Framework_Assert::assertEquals((int) $numPeers, 0);
		}
	}

	/**
	 * @Then /^user "([^"]*)" sends message "([^"]*)" to room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userSendsMessageToRoom($user, $message, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/v1/chat/' . self::$identifierToToken[$identifier],
			new TableNode([['message', $message]])
		);
		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages
	}

	/**
	 * @Then /^user "([^"]*)" sees the following messages in room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userSeesTheFollowingMessagesInRoom($user, $identifier, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/chat/' . self::$identifierToToken[$identifier] . '?lookIntoFuture=0');
		$this->assertStatusCode($this->response, $statusCode);

		$actual = $this->getDataFromResponse($this->response);
		$messages = [];
		array_map(function(array $message) use (&$messages) {
			// Filter out system messages
			if ($message['systemMessage'] === '') {
				$messages[] = $message;
			}
		}, $actual);

		if ($formData === null) {
			PHPUnit_Framework_Assert::assertEmpty($messages);
			return;
		}

		PHPUnit_Framework_Assert::assertCount(count($formData->getHash()), $messages, 'Message count does not match');
		PHPUnit_Framework_Assert::assertEquals($formData->getHash(), array_map(function($message) {
			return [
				'room' => self::$tokenToIdentifier[$message['token']],
				'actorType' => (string) $message['actorType'],
				'actorId' => ($message['actorType'] === 'guests')? self::$sessionIdToUser[$message['actorId']]: (string) $message['actorId'],
				'actorDisplayName' => (string) $message['actorDisplayName'],
				// TODO test timestamp; it may require using Runkit, php-timecop
				// or something like that.
				'message' => (string) $message['message'],
				'messageParameters' => json_encode($message['messageParameters']),
			];
		}, $messages));
	}

	/**
	 * @Then /^user "([^"]*)" sees the following system messages in room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function userSeesTheFollowingSystemMessagesInRoom($user, $identifier, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/chat/' . self::$identifierToToken[$identifier] . '?lookIntoFuture=0');
		$this->assertStatusCode($this->response, $statusCode);

		$messages = $this->getDataFromResponse($this->response);
		$messages = array_filter($messages, function(array $message) {
			return $message['systemMessage'] !== '';
		});

		if ($formData === null) {
			PHPUnit_Framework_Assert::assertEmpty($messages);
			return;
		}

		PHPUnit_Framework_Assert::assertCount(count($formData->getHash()), $messages, 'Message count does not match');
		PHPUnit_Framework_Assert::assertEquals($formData->getHash(), array_map(function($message) {
			return [
				'room' => self::$tokenToIdentifier[$message['token']],
				'actorType' => (string) $message['actorType'],
				'actorId' => ($message['actorType'] === 'guests')? self::$sessionIdToUser[$message['actorId']]: (string) $message['actorId'],
				'actorDisplayName' => (string) $message['actorDisplayName'],
				'systemMessage' => (string) $message['systemMessage'],
			];
		}, $messages));
	}

	/**
	 * @Then /^user "([^"]*)" gets the following candidate mentions in room "([^"]*)" for "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $search
	 * @param string $statusCode
	 * @param TableNode|null $formData
	 */
	public function userGetsTheFollowingCandidateMentionsInRoomFor($user, $identifier, $search, $statusCode, TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/v1/chat/' . self::$identifierToToken[$identifier] . '/mentions?search=' . $search);
		$this->assertStatusCode($this->response, $statusCode);

		$mentions = $this->getDataFromResponse($this->response);

		if ($formData === null) {
			PHPUnit_Framework_Assert::assertEmpty($mentions);
			return;
		}

		PHPUnit_Framework_Assert::assertCount(count($formData->getHash()), $mentions, 'Mentions count does not match');

		foreach ($formData->getHash() as $key => $row) {
			if ($row['id'] === 'GUEST_ID') {
				PHPUnit_Framework_Assert::assertRegExp('/^guest\/[0-9a-f]{40}$/', $mentions[$key]['id']);
				$mentions[$key]['id'] = 'GUEST_ID';
			}
			PHPUnit_Framework_Assert::assertEquals($row, $mentions[$key]);
		}
	}

	/**
	 * @Then /^guest "([^"]*)" sets name to "([^"]*)" in room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $name
	 * @param string $identifier
	 * @param string $statusCode
	 */
	public function guestSetsName($user, $name, $identifier, $statusCode) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/v1/guest/' . self::$identifierToToken[$identifier] . '/name',
			new TableNode([['displayName', $name]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 * @param ResponseInterface $response
	 * @return array
	 */
	protected function getDataFromResponse(ResponseInterface $response) {
		return $response->json()['ocs']['data'];
	}

	/**
	 * @Then /^status code is ([0-9]*)$/
	 *
	 * @param int $statusCode
	 */
	public function isStatusCode($statusCode) {
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 */
	public function resetSpreedAppData() {
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
		];

		try {
			return $client->send($client->createRequest('DELETE', getenv('TEST_SERVER_URL') . 'ocs/v2.php/apps/spreedcheats/', $options));
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			return $ex->getResponse();
		}
	}

	/*
	 * User management
	 */

	/**
	 * @Given /^as user "([^"]*)"$/
	 * @param string $user
	 */
	public function setCurrentUser($user) {
		$this->currentUser = $user;
	}

	/**
	 * @Given /^user "([^"]*)" exists$/
	 * @param string $user
	 */
	public function assureUserExists($user) {
		try {
			$this->userExists($user);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->createUser($user);
			// Set a display name different than the user ID to be able to
			// ensure in the tests that the right value was returned.
			$this->setUserDisplayName($user);
		}
		$response = $this->userExists($user);
		$this->assertStatusCode($response, 200);
	}

	private function userExists($user) {
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		return $client->get($this->baseUrl . 'ocs/v2.php/cloud/users/' . $user, $options);
	}

	private function createUser($user) {
		$userProvisioningUrl = $this->baseUrl . 'ocs/v2.php/cloud/users';
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'body' => [
				'userid' => $user,
				'password' => '123456'
			],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		$client->send($client->createRequest('POST', $userProvisioningUrl, $options));

		//Quick hack to login once with the current user
		$options2 = [
			'auth' => [$user, '123456'],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		$client->send($client->createRequest('GET', $userProvisioningUrl . '/' . $user, $options2));

		$this->createdUsers[] = $user;
	}

	/**
	 * @Given /^user "([^"]*)" is deleted$/
	 * @param string $user
	 */
	public function userIsDeleted($user) {
		$deleted = false;

		$this->deleteUser($user);
		try {
			$this->userExists($user);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$deleted = true;
		}

		if (!$deleted) {
			PHPUnit_Framework_Assert::fail("User $user exists");
		}
	}

	private function deleteUser($user) {
		$userProvisioningUrl = $this->baseUrl . 'ocs/v2.php/cloud/users/' . $user;
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		$client->send($client->createRequest('DELETE', $userProvisioningUrl, $options));

		unset($this->createdUsers[array_search($user, $this->createdUsers)]);
	}

	private function setUserDisplayName($user) {
		$userProvisioningUrl = $this->baseUrl . 'ocs/v2.php/cloud/users/' . $user;
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'body' => [
				'key' => 'displayname',
				'value' => $user . '-displayname'
			],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		$client->send($client->createRequest('PUT', $userProvisioningUrl, $options));
	}

	/**
	 * @Given /^group "([^"]*)" exists$/
	 * @param string $group
	 */
	public function assureGroupExists($group) {
		try {
			$this->groupExists($group);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->createGroup($group);
		}
		$response = $this->groupExists($group);
		$this->assertStatusCode($response, 200);
	}

	private function groupExists($group) {
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		return $client->get($this->baseUrl . 'ocs/v2.php/cloud/groups/' . $group, $options);
	}

	private function createGroup($group) {
		$userProvisioningUrl = $this->baseUrl . 'ocs/v2.php/cloud/groups';
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'body' => [
				'groupid' => $group,
			],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		$client->send($client->createRequest('POST', $userProvisioningUrl, $options));

		$this->createdGroups[] = $group;
	}

	private function deleteGroup($group) {
		$userProvisioningUrl = $this->baseUrl . 'ocs/v2.php/cloud/groups/' . $group;
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		$client->send($client->createRequest('DELETE', $userProvisioningUrl, $options));

		unset($this->createdGroups[array_search($group, $this->createdGroups)]);
	}

	/**
	 * @When /^user "([^"]*)" is member of group "([^"]*)"$/
	 * @param string $user
	 * @param string $group
	 */
	public function addingUserToGroup($user, $group) {
		$userProvisioningUrl = $this->baseUrl . "ocs/v2.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
			'body' => [
				'groupid' => $group,
			],
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];

		$this->response = $client->send($client->createRequest('POST', $userProvisioningUrl, $options));
	}

	/*
	 * Requests
	 */

	/**
	 * @When /^sending "([^"]*)" to "([^"]*)" with$/
	 * @param string $verb
	 * @param string $url
	 * @param TableNode $body
	 * @param array $headers
	 */
	public function sendRequest($verb, $url, $body = null, array $headers = []) {
		$fullUrl = $this->baseUrl . 'ocs/v2.php' . $url;
		$client = new Client();
		$options = ['cookies'  => $this->getUserCookieJar($this->currentUser)];
		if ($this->currentUser === 'admin') {
			$options['auth'] = ['admin', 'admin'];
		} else if (strpos($this->currentUser, 'guest') !== 0) {
			$options['auth'] = [$this->currentUser, '123456'];
		}
		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			$options['body'] = $fd;
		}

		$options['headers'] = array_merge($headers, [
			'OCS-ApiRequest' => 'true',
			'Accept' => 'application/json',
		]);

		try {
			$this->response = $client->send($client->createRequest($verb, $fullUrl, $options));
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	protected function getUserCookieJar($user) {
		if (!isset($this->cookieJars[$user])) {
			$this->cookieJars[$user] = new CookieJar();
		}
		return $this->cookieJars[$user];
	}

	/**
	 * @param ResponseInterface $response
	 * @param int $statusCode
	 */
	protected function assertStatusCode(ResponseInterface $response, $statusCode) {
		PHPUnit_Framework_Assert::assertEquals($statusCode, $response->getStatusCode());
	}

	protected function typeStringToInt($roomType) {
		switch ($roomType) {
			case 'one2one':
				return 1;
			case 'group':
				return 2;
			case 'public':
				return 3;
		}

		throw new \RuntimeException('Invalid room type');
	}
}
