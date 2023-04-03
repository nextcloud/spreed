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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext {
	public const TEST_PASSWORD = '123456';

	/** @var string[] */
	protected static $identifierToToken;
	/** @var string[] */
	protected static $identifierToId;
	/** @var string[] */
	protected static $tokenToIdentifier;
	/** @var array[] */
	protected static $identifierToAvatar;
	/** @var string[] */
	protected static $sessionIdToUser;
	/** @var string[] */
	protected static $userToSessionId;
	/** @var int[] */
	protected static $userToAttendeeId;
	/** @var string[] */
	protected static $messages;
	protected static $textToMessageId;
	/** @var array[] */
	protected static $messageIdToText;
	/** @var int[] */
	protected static $remoteToInviteId;
	/** @var string[] */
	protected static $inviteIdToRemote;
	/** @var int[] */
	protected static $questionToPollId;


	protected static $permissionsMap = [
		'D' => 0, // PERMISSIONS_DEFAULT
		'C' => 1, // PERMISSIONS_CUSTOM
		'S' => 2, // PERMISSIONS_CALL_START
		'J' => 4, // PERMISSIONS_CALL_JOIN
		'L' => 8, // PERMISSIONS_LOBBY_IGNORE
		'A' => 16, // PERMISSIONS_PUBLISH_AUDIO
		'V' => 32, // PERMISSIONS_PUBLISH_VIDEO
		'P' => 64, // PERMISSIONS_PUBLISH_SCREEN
		'M' => 128, // PERMISSIONS_CHAT
	];

	/** @var string */
	protected $currentUser;

	/** @var ResponseInterface */
	private $response;

	/** @var CookieJar[] */
	private $cookieJars;

	/** @var string */
	protected $baseUrl;

	/** @var string */
	protected $baseRemoteUrl;

	/** @var string */
	protected $lastEtag;

	/** @var array */
	protected $createdUsers = [];

	/** @var array */
	protected $createdGroups = [];

	/** @var array */
	protected $createdGuestAccountUsers = [];

	/** @var array */
	protected $changedConfigs = [];

	/** @var SharingContext */
	private $sharingContext;

	/** @var null|bool */
	private $guestsAppWasEnabled = null;

	/** @var string */
	private $guestsOldWhitelist;

	use CommandLineTrait;
	use RecordingTrait;

	public static function getTokenForIdentifier(string $identifier) {
		return self::$identifierToToken[$identifier];
	}

	public function getAttendeeId(string $type, string $id, string $room, string $user = null) {
		if (!isset(self::$userToAttendeeId[$room][$type][$id])) {
			if ($user !== null) {
				$this->userLoadsAttendeeIdsInRoom($user, $room, 'v4');
			} else {
				throw new \Exception('Attendee id unknown, please call userLoadsAttendeeIdsInRoom with a user that has access before');
			}
		}

		return self::$userToAttendeeId[$room][$type][$id];
	}

	/**
	 * FeatureContext constructor.
	 */
	public function __construct() {
		$this->cookieJars = [];
		$this->baseUrl = getenv('TEST_SERVER_URL');
		$this->baseRemoteUrl = getenv('TEST_REMOTE_URL');
		$this->guestsAppWasEnabled = null;
	}

	/**
	 * @BeforeScenario
	 */
	public function setUp() {
		self::$identifierToToken = [];
		self::$identifierToId = [];
		self::$tokenToIdentifier = [];
		self::$sessionIdToUser = [
			'cli' => 'cli',
			'failed-to-get-session' => 'failed-to-get-session',
		];
		self::$userToSessionId = [];
		self::$userToAttendeeId = [];
		self::$textToMessageId = [];
		self::$messageIdToText = [];
		self::$questionToPollId = [];

		$this->createdUsers = [];
		$this->createdGroups = [];
		$this->createdGuestAccountUsers = [];
	}

	/**
	 * @BeforeScenario
	 */
	public function getOtherRequiredSiblingContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->sharingContext = $environment->getContext("SharingContext");
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
		foreach ($this->createdGuestAccountUsers as $user) {
			$this->deleteUser($user);
		}
	}

	/**
	 * @Then /^user "([^"]*)" cannot find any listed rooms \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $apiVersion
	 */
	public function userCannotFindAnyListedRooms(string $user, string $apiVersion): void {
		$this->userCanFindListedRoomsWithTerm($user, '', $apiVersion, null);
	}

	/**
	 * @Then /^user "([^"]*)" cannot find any listed rooms with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userCannotFindAnyListedRoomsWithStatus(string $user, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/listed-room');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" cannot find any listed rooms with term "([^"]*)" \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $term
	 * @param string $apiVersion
	 */
	public function userCannotFindAnyListedRoomsWithTerm(string $user, string $term, string $apiVersion): void {
		$this->userCanFindListedRoomsWithTerm($user, $term, $apiVersion, null);
	}

	/**
	 * @Then /^user "([^"]*)" can find listed rooms \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userCanFindListedRooms(string $user, string $apiVersion, TableNode $formData = null): void {
		$this->userCanFindListedRoomsWithTerm($user, '', $apiVersion, $formData);
	}

	/**
	 * @Then /^user "([^"]*)" can find listed rooms with term "([^"]*)" \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $term
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userCanFindListedRoomsWithTerm(string $user, string $term, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$suffix = '';
		if ($term !== '') {
			$suffix = '?searchTerm=' . \rawurlencode($term);
		}
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/listed-room' . $suffix);
		$this->assertStatusCode($this->response, 200);

		$rooms = $this->getDataFromResponse($this->response);

		if ($formData === null) {
			Assert::assertEmpty($rooms);
			return;
		}

		$this->assertRooms($rooms, $formData);
	}

	/**
	 * @Then /^user "([^"]*)" is participant of the following (unordered )?rooms \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $shouldOrder
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userIsParticipantOfRooms(string $user, string $shouldOrder, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room');
		$this->assertStatusCode($this->response, 200);

		$rooms = $this->getDataFromResponse($this->response);

		$rooms = array_filter($rooms, function ($room) {
			return $room['type'] !== 4;
		});

		if ($formData === null) {
			Assert::assertEmpty($rooms);
			return;
		}

		$this->assertRooms($rooms, $formData, $shouldOrder !== '');
	}

	/**
	 * @Then /^user "([^"]*)" sees the following breakout rooms for room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $apiVersion
	 * @param int $status
	 * @param TableNode|null $formData
	 */
	public function userListsBreakoutRooms(string $user, string $identifier, int $status, string $apiVersion, TableNode $formData = null): void {
		$token = self::$identifierToToken[$identifier];

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . $token . '/breakout-rooms');
		$this->assertStatusCode($this->response, $status);

		if ($status !== 200) {
			return;
		}

		$rooms = $this->getDataFromResponse($this->response);

		$rooms = array_filter($rooms, function ($room) {
			return $room['type'] !== 4;
		});

		if ($formData === null) {
			Assert::assertEmpty($rooms);
			return;
		}

		$this->assertRooms($rooms, $formData, true);
	}

	/**
	 * @param array $rooms
	 * @param TableNode $formData
	 */
	private function assertRooms($rooms, TableNode $formData, bool $shouldOrder = false) {
		Assert::assertCount(count($formData->getHash()), $rooms, 'Room count does not match');

		$expected = $formData->getHash();
		if ($shouldOrder) {
			$sorter = static function (array $roomA, array $roomB): int {
				if (str_starts_with($roomA['name'], '/')) {
					return 1;
				}
				if (str_starts_with($roomB['name'], '/')) {
					return -1;
				}

				$idA = $roomA['id'] ?? self::$identifierToId[$roomA['name']];
				$idB = $roomB['id'] ?? self::$identifierToId[$roomB['name']];

				if (isset(self::$identifierToId[$idA])) {
					$idA = self::$identifierToId[$idA];
				} else {
					self::$identifierToId[$roomA['name']] = $idA;
				}

				if (isset(self::$identifierToId[$idB])) {
					$idB = self::$identifierToId[$idB];
				} else {
					self::$identifierToId[$roomB['name']] = $idB;
				}

				return $idA < $idB ? -1 : 1;
			};

			usort($rooms, $sorter);
			usort($expected, $sorter);
		}

		Assert::assertEquals($expected, array_map(function ($room, $expectedRoom) {
			if (!isset(self::$identifierToToken[$room['name']])) {
				self::$identifierToToken[$room['name']] = $room['token'];
			}
			if (!isset(self::$tokenToIdentifier[$room['token']])) {
				self::$tokenToIdentifier[$room['token']] = $room['name'];
			}

			$data = [];
			if (isset($expectedRoom['id'])) {
				$data['id'] = self::$tokenToIdentifier[$room['token']];
			}
			if (isset($expectedRoom['name'])) {
				$data['name'] = $room['name'];

				// Breakout room regex
				if (strpos($expectedRoom['name'], '/') === 0 && preg_match($expectedRoom['name'], $room['name'])) {
					$data['name'] = $expectedRoom['name'];
				}
			}
			if (isset($expectedRoom['description'])) {
				$data['description'] = $room['description'];
			}
			if (isset($expectedRoom['type'])) {
				$data['type'] = (string) $room['type'];
			}
			if (isset($expectedRoom['hasPassword'])) {
				$data['hasPassword'] = (string) $room['hasPassword'];
			}
			if (isset($expectedRoom['readOnly'])) {
				$data['readOnly'] = (string) $room['readOnly'];
			}
			if (isset($expectedRoom['listable'])) {
				$data['listable'] = (string) $room['listable'];
			}
			if (isset($expectedRoom['participantType'])) {
				$data['participantType'] = (string) $room['participantType'];
			}
			if (isset($expectedRoom['sipEnabled'])) {
				$data['sipEnabled'] = (string) $room['sipEnabled'];
			}
			if (isset($expectedRoom['callFlag'])) {
				$data['callFlag'] = (int) $room['callFlag'];
			}
			if (isset($expectedRoom['lobbyState'])) {
				$data['lobbyState'] = (int) $room['lobbyState'];
			}
			if (isset($expectedRoom['breakoutRoomMode'])) {
				$data['breakoutRoomMode'] = (int) $room['breakoutRoomMode'];
			}
			if (isset($expectedRoom['breakoutRoomStatus'])) {
				$data['breakoutRoomStatus'] = (int) $room['breakoutRoomStatus'];
			}
			if (isset($expectedRoom['attendeePin'])) {
				$data['attendeePin'] = $room['attendeePin'] ? '**PIN**' : '';
			}
			if (isset($expectedRoom['lastMessage'])) {
				$data['lastMessage'] = $room['lastMessage'] ? $room['lastMessage']['message'] : '';
			}
			if (isset($expectedRoom['unreadMessages'])) {
				$data['unreadMessages'] = (int) $room['unreadMessages'];
			}
			if (isset($expectedRoom['unreadMention'])) {
				$data['unreadMention'] = (int) $room['unreadMention'];
			}
			if (isset($expectedRoom['unreadMentionDirect'])) {
				$data['unreadMentionDirect'] = (int) $room['unreadMentionDirect'];
			}
			if (isset($expectedRoom['messageExpiration'])) {
				$data['messageExpiration'] = (int) $room['messageExpiration'];
			}
			if (isset($expectedRoom['callRecording'])) {
				$data['callRecording'] = (int) $room['callRecording'];
			}
			if (isset($expectedRoom['participants'])) {
				throw new \Exception('participants key needs to be checked via participants endpoint');
			}

			return $data;
		}, $rooms, $formData->getHash()));
	}

	/**
	 * @Then /^user "([^"]*)" has the following invitations \((v1)\)$/
	 *
	 * @param string $user
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userHasInvites(string $user, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/federation/invitation');
		$this->assertStatusCode($this->response, 200);

		$invites = $this->getDataFromResponse($this->response);

		if ($formData === null) {
			Assert::assertEmpty($invites);
			return;
		}

		$this->assertInvites($invites, $formData);

		foreach ($invites as $data) {
			self::$remoteToInviteId[$this->translateRemoteServer($data['remote_server']) . '::' . self::$tokenToIdentifier[$data['remote_token']]] = $data['id'];
			self::$inviteIdToRemote[$data['id']] = $this->translateRemoteServer($data['remote_server']) . '::' . self::$tokenToIdentifier[$data['remote_token']];
		}
	}

	/**
	 * @Then /^user "([^"]*)" (accepts|declines) invite to room "([^"]*)" of server "([^"]*)" \((v1)\)$/
	 *
	 * @param string $user
	 * @param string $roomName
	 * @param string $server
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userAcceptsDeclinesRemoteInvite(string $user, string $acceptsDeclines, string $roomName, string $server, string $apiVersion, TableNode $formData = null): void {
		$inviteId = self::$remoteToInviteId[$server . '::' . $roomName];

		$verb = $acceptsDeclines === 'accepts' ? 'POST' : 'DELETE';

		$this->setCurrentUser($user);
		if ($server === 'LOCAL') {
			$this->sendRemoteRequest($verb, '/apps/spreed/api/' . $apiVersion . '/federation/invitation/' . $inviteId);
		}
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @param array $invites
	 * @param TableNode $formData
	 */
	private function assertInvites($invites, TableNode $formData) {
		Assert::assertCount(count($formData->getHash()), $invites, 'Invite count does not match');
		Assert::assertEquals($formData->getHash(), array_map(function ($invite, $expectedInvite) {
			$data = [];
			if (isset($expectedInvite['id'])) {
				$data['id'] = self::$tokenToIdentifier[$invite['token']];
			}
			if (isset($expectedInvite['access_token'])) {
				$data['access_token'] = (string) $invite['access_token'];
			}
			if (isset($expectedInvite['remote_token'])) {
				$data['remote_token'] = self::$tokenToIdentifier[$invite['remote_token']] ?? 'unknown-token';
			}
			if (isset($expectedInvite['remote_server'])) {
				$data['remote_server'] = $this->translateRemoteServer($invite['remote_server']);
			}

			return $data;
		}, $invites, $formData->getHash()));
	}

	protected function translateRemoteServer(string $server): string {
		$server = str_replace('http://', '', $server);
		if ($server === 'localhost:8080') {
			return 'LOCAL';
		}
		if ($server === 'localhost:8180') {
			return 'REMOTE';
		}
		return 'unknown-server';
	}

	/**
	 * @Then /^user "([^"]*)" (is|is not) participant of room "([^"]*)" \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $isOrNotParticipant
	 * @param string $identifier
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userIsParticipantOfRoom(string $user, string $isOrNotParticipant, string $identifier, string $apiVersion, TableNode $formData = null): void {
		if (strpos($user, 'guest') === 0) {
			$this->guestIsParticipantOfRoom($user, $isOrNotParticipant, $identifier, $apiVersion, $formData);

			return;
		}

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room');
		$this->assertStatusCode($this->response, 200);

		$isParticipant = $isOrNotParticipant === 'is';

		$rooms = $this->getDataFromResponse($this->response);

		$rooms = array_filter($rooms, function ($room) {
			return $room['type'] !== 4;
		});

		if ($isParticipant) {
			Assert::assertNotEmpty($rooms);
		}

		foreach ($rooms as $room) {
			if (self::$tokenToIdentifier[$room['token']] === $identifier) {
				Assert::assertEquals($isParticipant, true, 'Room ' . $identifier . ' found in user´s room list');

				if ($formData) {
					$this->assertRooms([$room], $formData);
				}
				return;
			}
		}

		Assert::assertEquals($isParticipant, false, 'Room ' . $identifier . ' not found in user´s room list');
	}

	/**
	 * @Then /^user "([^"]*)" sees the following attendees in room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode $formData
	 */
	public function userSeesAttendeesInRoom(string $user, string $identifier, int $statusCode, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants');
		$this->assertStatusCode($this->response, $statusCode);

		if ($formData instanceof TableNode) {
			$attendees = $this->getDataFromResponse($this->response);
		} else {
			$attendees = [];
		}
		$this->assertAttendeeList($identifier, $formData, $attendees);
	}

	/**
	 * @Then /^user "([^"]*)" sees the following attendees in breakout rooms for room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode $formData
	 */
	public function userSeesAttendeesInBreakoutRoomsForRoom(string $user, string $identifier, int $statusCode, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/breakout-rooms/participants');
		$this->assertStatusCode($this->response, $statusCode);

		if ($formData instanceof TableNode) {
			$attendees = $this->getDataFromResponse($this->response);
		} else {
			$attendees = [];
		}
		$this->assertAttendeeList($identifier, $formData, $attendees);
	}

	protected function assertAttendeeList(string $identifier, ?TableNode $formData, array $attendees): void {
		if ($formData instanceof TableNode) {
			$expectedKeys = array_flip($formData->getRows()[0]);

			$result = [];
			foreach ($attendees as $attendee) {
				$data = [];
				if (isset($expectedKeys['roomToken'])) {
					$data['roomToken'] = self::$tokenToIdentifier[$attendee['roomToken']];
				}
				if (isset($expectedKeys['actorType'])) {
					$data['actorType'] = $attendee['actorType'];
				}
				if (isset($expectedKeys['actorId'])) {
					$data['actorId'] = $attendee['actorId'];
				}
				if (isset($expectedKeys['participantType'])) {
					$data['participantType'] = (string) $attendee['participantType'];
				}
				if (isset($expectedKeys['inCall'])) {
					$data['inCall'] = (string) $attendee['inCall'];
				}
				if (isset($expectedKeys['attendeePin'])) {
					$data['attendeePin'] = $attendee['attendeePin'] ? '**PIN**' : '';
				}
				if (isset($expectedKeys['permissions'])) {
					$data['permissions'] = (string) $attendee['permissions'];
				}
				if (isset($expectedKeys['attendeePermissions'])) {
					$data['attendeePermissions'] = (string) $attendee['attendeePermissions'];
				}

				if (!isset(self::$userToAttendeeId[$identifier][$attendee['actorType']])) {
					self::$userToAttendeeId[$identifier][$attendee['actorType']] = [];
				}
				self::$userToAttendeeId[$identifier][$attendee['actorType']][$attendee['actorId']] = $attendee['attendeeId'];

				$result[] = $data;
			}

			$expected = array_map(function ($attendee, $actual) {
				if (isset($attendee['actorId']) && substr($attendee['actorId'], 0, strlen('"guest')) === '"guest') {
					$attendee['actorId'] = sha1(self::$userToSessionId[trim($attendee['actorId'], '"')]);
				}

				if (isset($attendee['actorId'], $attendee['actorType']) && $attendee['actorType'] === 'federated_users') {
					$attendee['actorId'] .= '@' . rtrim($this->baseRemoteUrl, '/');
				}

				// Breakout room regex
				if (isset($attendee['actorId']) && strpos($attendee['actorId'], '/') === 0 && preg_match($attendee['actorId'], $actual['actorId'])) {
					$attendee['actorId'] = $actual['actorId'];
				}

				if (isset($attendee['participantType'])) {
					$attendee['participantType'] = (string)$this->mapParticipantTypeTestInput($attendee['participantType']);
				}
				return $attendee;
			}, $formData->getHash(), $result);

			$result = array_map(function ($attendee) {
				if (isset($attendee['permissions'])) {
					$attendee['permissions'] = $this->mapPermissionsAPIOutput($attendee['permissions']);
				}
				if (isset($attendee['attendeePermissions'])) {
					$attendee['attendeePermissions'] = $this->mapPermissionsAPIOutput($attendee['attendeePermissions']);
				}
				return $attendee;
			}, $result);

			usort($expected, [self::class, 'sortAttendees']);
			usort($result, [self::class, 'sortAttendees']);

			Assert::assertEquals($expected, $result);
		} else {
			Assert::assertNull($formData);
		}
	}

	/**
	 * @Then /^user "([^"]*)" loads attendees attendee ids in room "([^"]*)" \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $apiVersion
	 */
	public function userLoadsAttendeeIdsInRoom(string $user, string $identifier, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants');
		$this->assertStatusCode($this->response, 200);
		$attendees = $this->getDataFromResponse($this->response);

		foreach ($attendees as $attendee) {
			if (!isset(self::$userToAttendeeId[$identifier][$attendee['actorType']])) {
				self::$userToAttendeeId[$identifier][$attendee['actorType']] = [];
			}
			self::$userToAttendeeId[$identifier][$attendee['actorType']][$attendee['actorId']] = $attendee['attendeeId'];
		}
	}

	protected static function sortAttendees(array $a1, array $a2): int {
		if (array_key_exists('roomToken', $a1) && array_key_exists('roomToken', $a2) && $a1['roomToken'] !== $a2['roomToken']) {
			return $a1['roomToken'] <=> $a2['roomToken'];
		}
		if (array_key_exists('participantType', $a1) && array_key_exists('participantType', $a2) && $a1['participantType'] !== $a2['participantType']) {
			return $a1['participantType'] <=> $a2['participantType'];
		}
		if ($a1['actorType'] !== $a2['actorType']) {
			return $a1['actorType'] <=> $a2['actorType'];
		}
		return $a1['actorId'] <=> $a2['actorId'];
	}

	private function mapParticipantTypeTestInput($participantType) {
		if (is_numeric($participantType)) {
			return $participantType;
		}

		switch ($participantType) {
			case 'OWNER': return 1;
			case 'MODERATOR': return 2;
			case 'USER': return 3;
			case 'GUEST': return 4;
			case 'USER_SELF_JOINED': return 5;
			case 'GUEST_MODERATOR': return 6;
		}

		Assert::fail('Invalid test input value for participant type');
	}

	private function mapPermissionsTestInput($permissions): int {
		if (is_numeric($permissions)) {
			return $permissions;
		}

		$numericPermissions = 0;
		foreach (self::$permissionsMap as $char => $int) {
			if (strpos($permissions, $char) !== false) {
				$numericPermissions += $int;
				$permissions = str_replace($char, '', $permissions);
			}
		}

		if (trim($permissions) !== '') {
			Assert::fail('Invalid test input value for permissions');
		}

		return $numericPermissions;
	}

	private function mapPermissionsAPIOutput($permissions): string {
		$permissions = (int) $permissions;

		$permissionsString = !$permissions ? 'D' : '';
		foreach (self::$permissionsMap as $char => $int) {
			if ($permissions & $int) {
				$permissionsString .= $char;
				$permissions &= ~ $int;
			}
		}

		if ($permissions !== 0) {
			Assert::fail('Invalid API output value for permissions');
		}

		return $permissionsString;
	}

	/**
	 * @param string $guest
	 * @param string $isOrNotParticipant
	 * @param string $identifier
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	private function guestIsParticipantOfRoom(string $guest, string $isOrNotParticipant, string $identifier, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($guest);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier]);

		$response = $this->getDataFromResponse($this->response);

		$isParticipant = $isOrNotParticipant === 'is';

		if ($formData) {
			$rooms = [$response];

			$this->assertRooms($rooms, $formData);
		}

		if ($isParticipant) {
			$this->assertStatusCode($this->response, 200);
			Assert::assertEquals(self::$userToSessionId[$guest], $response['sessionId']);

			return;
		}

		if ($this->response->getStatusCode() === 200) {
			// Public rooms can always be got, but if the guest is not a
			// participant the sessionId will be 0.
			Assert::assertEquals(0, $response['sessionId']);

			return;
		}

		$this->assertStatusCode($this->response, 404);
	}

	/**
	 * @Then /^user "([^"]*)" creates room "([^"]*)" \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userCreatesRoom(string $user, string $identifier, string $apiVersion, TableNode $formData = null): void {
		$this->userCreatesRoomWith($user, $identifier, 201, $apiVersion, $formData);
	}

	/**
	 * @Then /^user "([^"]*)" creates room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userCreatesRoomWith(string $user, string $identifier, int $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$body = $formData->getRowsHash();

		if (isset($body['objectType'], $body['objectId']) && $body['objectType'] === 'room') {
			$result = preg_match('/ROOM\(([^)]+)\)/', $body['objectId'], $matches);
			if ($result && isset(self::$identifierToToken[$matches[1]])) {
				$body['objectId'] = self::$identifierToToken[$matches[1]];
			} elseif ($result) {
				throw new \InvalidArgumentException('Could not find parent room');
			}
		}


		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/room', $body);
		$this->assertStatusCode($this->response, $statusCode);

		$response = $this->getDataFromResponse($this->response);

		if ($statusCode === 201) {
			self::$identifierToToken[$identifier] = $response['token'];
			self::$identifierToId[$identifier] = $response['id'];
			self::$tokenToIdentifier[$response['token']] = $identifier;
		}
	}

	/**
	 * @Then /^user "([^"]*)" tries to create room with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userTriesToCreateRoom(string $user, int $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/room', $formData);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" gets the room for path "([^"]*)" with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param string $path
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userGetsTheRoomForPath(string $user, string $path, int $statusCode, string $apiVersion): void {
		$fileId = $this->getFileIdForPath($user, $path);

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/file/' . $fileId);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== 200) {
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
		$fullUrl = $this->baseUrl . 'remote.php/dav/files' . $url;
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = 'admin';
		} else {
			$options['auth'] = [$this->currentUser, self::TEST_PASSWORD];
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
			$this->response = $client->{$verb}($fullUrl, $options);
		} catch (GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @Then /^user "([^"]*)" gets the room for last share with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userGetsTheRoomForLastShare(string $user, int $statusCode, string $apiVersion): void {
		$shareToken = $this->sharingContext->getLastShareToken();

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/publicshare/' . $shareToken);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== 200) {
			return;
		}

		$response = $this->getDataFromResponse($this->response);

		$identifier = 'file last share room';
		self::$identifierToToken[$identifier] = $response['token'];
		self::$tokenToIdentifier[$response['token']] = $identifier;
	}

	/**
	 * @Then /^user "([^"]*)" creates the password request room for last share with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userCreatesThePasswordRequestRoomForLastShare(string $user, int $statusCode, string $apiVersion): void {
		$shareToken = $this->sharingContext->getLastShareToken();

		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/publicshareauth', ['shareToken' => $shareToken]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== 201) {
			return;
		}

		$response = $this->getDataFromResponse($this->response);

		$identifier = 'password request for last share room';
		self::$identifierToToken[$identifier] = $response['token'];
		self::$tokenToIdentifier[$response['token']] = $identifier;
	}

	/**
	 * @Then /^user "([^"]*)" joins room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userJoinsRoom(string $user, string $identifier, int $statusCode, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants/active',
			$formData
		);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== 200) {
			return;
		}

		$response = $this->getDataFromResponse($this->response);
		if (array_key_exists('sessionId', $response)) {
			// In the chat guest users are identified by their sessionId. The
			// sessionId is larger than the size of the actorId column in the
			// database, though, so the ID stored in the database and returned
			// in chat messages is a hashed version instead.
			self::$sessionIdToUser[sha1($response['sessionId'])] = $user;
			self::$userToSessionId[$user] = $response['sessionId'];
		}
	}

	/**
	 * @Then /^user "([^"]*)" sets notifications to (default|disabled|mention|all) for room "([^"]*)" \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $level
	 * @param string $identifier
	 * @param string $apiVersion
	 */
	public function userSetsNotificationLevelForRoom(string $user, string $level, string $identifier, string $apiVersion): void {
		$this->setCurrentUser($user);

		$intLevel = 0; // default
		if ($level === 'disabled') {
			$intLevel = 3;
		} elseif ($level === 'mention') {
			$intLevel = 2;
		} elseif ($level === 'all') {
			$intLevel = 1;
		}

		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/notify',
			new TableNode([
				['level', $intLevel],
			])
		);

		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @Then /^user "([^"]*)" leaves room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userExitsRoom(string $user, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants/active');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" removes themselves from room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userLeavesRoom(string $user, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants/self');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" removes "([^"]*)" from room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $toRemove
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userRemovesUserFromRoom(string $user, string $toRemove, string $identifier, int $statusCode, string$apiVersion): void {
		if ($toRemove === 'stranger') {
			$attendeeId = 123456789;
		} else {
			$attendeeId = $this->getAttendeeId('users', $toRemove, $identifier, $statusCode === 200 ? $user : null);
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/attendees',
			new TableNode([['attendeeId', $attendeeId]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" removes (user|group|email) "([^"]*)" from room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $actorType
	 * @param string $actorId
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userRemovesAttendeeFromRoom(string $user, string $actorType, string $actorId, string $identifier, int $statusCode, string$apiVersion): void {
		if ($actorId === 'stranger') {
			$attendeeId = 123456789;
		} else {
			$attendeeId = $this->getAttendeeId($actorType . 's', $actorId, $identifier, $statusCode === 200 ? $user : null);
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/attendees',
			new TableNode([['attendeeId', $attendeeId]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" deletes room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userDeletesRoom(string $user, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" gets room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userGetsRoom(string $user, string $identifier, int $statusCode, string $apiVersion = 'v4', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($formData instanceof TableNode) {
			$xpectedAttributes = $formData->getColumnsHash()[0];
			$actual = $this->getDataFromResponse($this->response);
			foreach ($xpectedAttributes as $attribute => $expectedValue) {
				Assert::assertEquals($expectedValue, $actual[$attribute]);
			}
		}
	}

	/**
	 * @Then /^user "([^"]*)" renames room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $newName
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userRenamesRoom(string $user, string $identifier, string $newName, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier],
			new TableNode([['roomName', $newName]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets description for room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $description
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode
	 */
	public function userSetsDescriptionForRoomTo(string $user, string $identifier, string $description, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' .$apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/description',
			new TableNode([['description', $description]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets password "([^"]*)" for room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsTheRoomPassword(string $user, string $password, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/password',
			new TableNode([['password', $password]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets lobby state for room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $lobbyStateString
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsLobbyStateForRoomTo(string $user, string $identifier, string $lobbyStateString, int $statusCode, string $apiVersion): void {
		if ($lobbyStateString === 'no lobby') {
			$lobbyState = 0;
		} elseif ($lobbyStateString === 'non moderators') {
			$lobbyState = 1;
		} else {
			Assert::fail('Invalid lobby state');
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/webinar/lobby',
			new TableNode([['state', $lobbyState]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets lobby state for room "([^"]*)" to "([^"]*)" for (\d+) seconds with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $lobbyStateString
	 * @param int $lobbyTimer
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsLobbyStateAndTimerForRoom(string $user, string $identifier, string $lobbyStateString, int $lobbyTimer, int $statusCode, string $apiVersion): void {
		if ($lobbyStateString === 'no lobby') {
			$lobbyState = 0;
		} elseif ($lobbyStateString === 'non moderators') {
			$lobbyState = 1;
		} else {
			Assert::fail('Invalid lobby state');
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/webinar/lobby',
			new TableNode([['state', $lobbyState], ['timer', time() + $lobbyTimer]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets SIP state for room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $SIPStateString
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsSIPStateForRoomTo(string $user, string $identifier, string $SIPStateString, int $statusCode, string $apiVersion): void {
		if ($SIPStateString === 'disabled') {
			$SIPState = 0;
		} elseif ($SIPStateString === 'enabled') {
			$SIPState = 1;
		} elseif ($SIPStateString === 'no pin') {
			$SIPState = 2;
		} else {
			Assert::fail('Invalid SIP state');
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/webinar/sip',
			new TableNode([['state', $SIPState]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" makes room "([^"]*)" (public|private) with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $newType
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userChangesTypeOfTheRoom(string $user, string $identifier, string $newType, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			$newType === 'public' ? 'POST' : 'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/public'
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" (locks|unlocks) room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $newState
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userChangesReadOnlyStateOfTheRoom(string $user, string $newState, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/read-only',
			new TableNode([['state', $newState === 'unlocks' ? 0 : 1]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" allows listing room "([^"]*)" for "(none|users|all|\d+)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string|int $newState
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userChangesListableScopeOfTheRoom(string $user, string $identifier, $newState, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		if ($newState === 'none') {
			$newStateValue = 0; // Room::LISTABLE_NONE
		} elseif ($newState === 'users') {
			$newStateValue = 1; // Room::LISTABLE_USERS
		} elseif ($newState === 'all') {
			$newStateValue = 2; // Room::LISTABLE_ALL
		} elseif (is_numeric($newState)) {
			$newStateValue = (int)$newState;
		} else {
			Assert::fail('Invalid listable scope value');
		}

		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/listable',
			new TableNode([['scope', $newStateValue]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" adds (user|group|email|circle|remote) "([^"]*)" to room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $newType
	 * @param string $newId
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userAddAttendeeToRoom(string $user, string $newType, string $newId, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);

		if ($newType === 'remote') {
			$newId .= '@' . $this->baseRemoteUrl;
		}

		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants',
			new TableNode([
				['source', $newType . 's'],
				['newParticipant', $newId],
			])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" (promotes|demotes) "([^"]*)" in room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $isPromotion
	 * @param string $participant
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userPromoteDemoteInRoom(string $user, string $isPromotion, string $participant, string $identifier, int $statusCode, string $apiVersion): void {
		if ($participant === 'stranger') {
			$attendeeId = 123456789;
		} elseif (strpos($participant, 'guest') === 0) {
			$sessionId = self::$userToSessionId[$participant];
			$attendeeId = $this->getAttendeeId('guests', sha1($sessionId), $identifier, $statusCode === 200 ? $user : null);
		} else {
			$attendeeId = $this->getAttendeeId('users', $participant, $identifier, $statusCode === 200 ? $user : null);
		}

		$requestParameters = [['attendeeId', $attendeeId]];

		$this->setCurrentUser($user);
		$this->sendRequest(
			$isPromotion === 'promotes' ? 'POST' : 'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/moderators',
			new TableNode($requestParameters)
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets permissions for "([^"]*)" in room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $participant
	 * @param string $identifier
	 * @param string $permissionsString
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsPermissionsForInRoomTo(string $user, string $participant, string $identifier, string $permissionsString, int $statusCode, string $apiVersion): void {
		if ($participant === 'stranger') {
			$attendeeId = 123456789;
		} elseif (strpos($participant, 'guest') === 0) {
			$sessionId = self::$userToSessionId[$participant];
			$attendeeId = $this->getAttendeeId('guests', sha1($sessionId), $identifier, $statusCode === 200 ? $user : null);
		} else {
			$attendeeId = $this->getAttendeeId('users', $participant, $identifier, $statusCode === 200 ? $user : null);
		}

		$permissions = $this->mapPermissionsTestInput($permissionsString);

		$requestParameters = [
			['attendeeId', $attendeeId],
			['permissions', $permissions],
			['method', 'set'],
		];

		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/attendees/permissions',
			new TableNode($requestParameters)
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" sets (call|default) permissions for room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $mode
	 * @param string $identifier
	 * @param string $permissionsString
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsPermissionsForRoomTo(string $user, string $mode, string $identifier, string $permissionsString, int $statusCode, string $apiVersion): void {
		$permissions = $this->mapPermissionsTestInput($permissionsString);

		$requestParameters = [
			['permissions', $permissions],
		];

		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/permissions/' . $mode,
			new TableNode($requestParameters)
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" joins call "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userJoinsCall(string $user, string $identifier, int $statusCode, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier],
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
			self::$userToSessionId[$user] = $response['sessionId'];
		}
	}

	/**
	 * @Then /^user "([^"]*)" updates call flags in room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $flags
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userUpdatesCallFlagsInRoomTo(string $user, string $identifier, string $flags, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier],
			new TableNode([['flags', $flags]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" pings (user|guest) "([^"]*)" to join call "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $actorType
	 * @param string $actorId
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userPingsAttendeeInRoomTo(string $user, string $actorType, string $actorId, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier] . '/ring/' . self::$userToAttendeeId[$identifier][$actorType . 's'][$actorId]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" leaves call "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userLeavesCall(string $user, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" ends call "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userEndsCall(string $user, string $identifier, int $statusCode, string $apiVersion): void {
		$requestParameters = [
			['all', true],
		];

		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier],
			new TableNode($requestParameters)
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" sees (\d+) peers in call "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param int $numPeers
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSeesPeersInCall(string $user, int $numPeers, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode === 200) {
			$response = $this->getDataFromResponse($this->response);
			Assert::assertCount((int) $numPeers, $response);
		} else {
			Assert::assertEquals((int) $numPeers, 0);
		}
	}

	/**
	 * @Then /^user "([^"]*)" (silent sends|sends) message ("[^"]*"|'[^']*') to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $sendingMode
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSendsMessageToRoom(string $user, string $sendingMode, string $message, string $identifier, string $statusCode, string $apiVersion = 'v1') {
		$message = substr($message, 1, -1);
		if ($sendingMode === 'silent sends') {
			$body = new TableNode([['message', $message], ['silent', true]]);
		} else {
			$body = new TableNode([['message', $message]]);
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier],
			$body
		);
		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages

		$response = $this->getDataFromResponse($this->response);
		if (isset($response['id'])) {
			self::$textToMessageId[$message] = $response['id'];
			self::$messageIdToText[$response['id']] = $message;
		}
	}

	/**
	 * @Then /^user "([^"]*)" shares rich-object "([^"]*)" "([^"]*)" '([^']*)' to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $type
	 * @param string $id
	 * @param string $metaData
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSharesRichObjectToRoom($user, $type, $id, $metaData, $identifier, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/share',
			new TableNode([
				['objectType', $type],
				['objectId', $id],
				['metaData', $metaData],
			])
		);
		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages

		$response = $this->getDataFromResponse($this->response);
		if (isset($response['id'])) {
			self::$textToMessageId['shared::' . $type . '::' . $id] = $response['id'];
			self::$messageIdToText[$response['id']] = 'shared::' . $type . '::' . $id;
		}
	}

	/**
	 * @Then /^user "([^"]*)" creates a poll in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function createPoll(string $user, string $identifier, string $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$data = $formData->getRowsHash();
		$data['options'] = json_decode($data['options'], true);
		if ($data['resultMode'] === 'public') {
			$data['resultMode'] = 0;
		} elseif ($data['resultMode'] === 'hidden') {
			$data['resultMode'] = 1;
		} else {
			throw new \Exception('Invalid result mode');
		}
		if ($data['maxVotes'] === 'unlimited') {
			$data['maxVotes'] = 0;
		}

		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/poll/' . self::$identifierToToken[$identifier],
			$data
		);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== '201') {
			return;
		}

		$response = $this->getDataFromResponse($this->response);
		if (isset($response['id'])) {
			self::$questionToPollId[$data['question']] = $response['id'];
		}
	}

	/**
	 * @Then /^user "([^"]*)" sees poll "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $question
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 * @param ?TableNode $formData
	 */
	public function userSeesPollInRoom(string $user, string $question, string $identifier, string $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);

		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/poll/' . self::$identifierToToken[$identifier] . '/' . self::$questionToPollId[$question]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode === '200' || $formData instanceof TableNode) {
			$expected = $this->preparePollExpectedData($formData->getRowsHash());
			$response = $this->getDataFromResponse($this->response);
			$this->assertPollEquals($expected, $response);
		}
	}

	/**
	 * @Then /^user "([^"]*)" closes poll "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $question
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 * @param ?TableNode $formData
	 */
	public function userClosesPollInRoom(string $user, string $question, string $identifier, string $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/poll/' . self::$identifierToToken[$identifier] . '/' . self::$questionToPollId[$question]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== '200') {
			return;
		}

		$expected = $this->preparePollExpectedData($formData->getRowsHash());
		$response = $this->getDataFromResponse($this->response);
		$this->assertPollEquals($expected, $response);
	}

	/**
	 * @Then /^user "([^"]*)" votes for options "([^"]*)" on poll "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $options
	 * @param string $question
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 * @param ?TableNode $formData
	 */
	public function userVotesPollInRoom(string $user, string $options, string $question, string $identifier, string $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$data = [
			'optionIds' => json_decode($options, true),
		];

		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/poll/' . self::$identifierToToken[$identifier] . '/' . self::$questionToPollId[$question],
			$data
		);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== '200' && $statusCode !== '201') {
			return;
		}

		$expected = $this->preparePollExpectedData($formData->getRowsHash());
		$response = $this->getDataFromResponse($this->response);
		$this->assertPollEquals($expected, $response);
	}

	protected function assertPollEquals(array $expected, array $response): void {
		if (isset($expected['details'])) {
			$response['details'] = array_map(static function (array $detail): array {
				unset($detail['id']);
				return $detail;
			}, $response['details']);
		}

		Assert::assertEquals($expected, $response);
	}

	protected function preparePollExpectedData(array $expected): array {
		if ($expected['resultMode'] === 'public') {
			$expected['resultMode'] = 0;
		} elseif ($expected['resultMode'] === 'hidden') {
			$expected['resultMode'] = 1;
		}
		if ($expected['maxVotes'] === 'unlimited') {
			$expected['maxVotes'] = 0;
		}
		if ($expected['status'] === 'open') {
			$expected['status'] = 0;
		} elseif ($expected['status'] === 'closed') {
			$expected['status'] = 1;
		}

		if ($expected['votedSelf'] === 'not voted') {
			$expected['votedSelf'] = [];
		} else {
			$expected['votedSelf'] = json_decode($expected['votedSelf'], true);
		}

		if (isset($expected['votes'])) {
			$expected['votes'] = json_decode($expected['votes'], true);
		}
		if (isset($expected['details'])) {
			$expected['details'] = json_decode($expected['details'], true);
		}
		$expected['numVoters'] = (int) $expected['numVoters'];
		$expected['options'] = json_decode($expected['options'], true);

		$result = preg_match('/POLL_ID\(([^)]+)\)/', $expected['id'], $matches);
		if ($result) {
			$expected['id'] = self::$questionToPollId[$matches[1]];
		}

		return $expected;
	}

	/**
	 * @Then /^user "([^"]*)" sees the following entry when loading the list of dashboard widgets(?: \((v1)\))$/
	 *
	 * @param string $user
	 * @param string $apiVersion
	 * @param ?TableNode $formData
	 */
	public function userGetsDashboardWidgets($user, $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/dashboard/api/' . $apiVersion . '/widgets');
		$this->assertStatusCode($this->response, 200);

		$data = $this->getDataFromResponse($this->response);
		$expectedWidgets = $formData->getColumnsHash();

		foreach ($expectedWidgets as $widget) {
			$id = $widget['id'];
			Assert::assertArrayHasKey($widget['id'], $data);

			$widgetIconUrl = $widget['icon_url'];
			$dataIconUrl = $data[$id]['icon_url'];

			unset($widget['icon_url'], $data[$id]['icon_url']);

			$widget['item_icons_round'] = (bool) $widget['item_icons_round'];
			$widget['order'] = (int) $widget['order'];
			$widget['widget_url'] = str_replace('{$BASE_URL}', $this->baseUrl, $widget['widget_url']);
			$widget['buttons'] = str_replace('{$BASE_URL}', $this->baseUrl, $widget['buttons']);
			$widget['buttons'] = json_decode($widget['buttons'], true);

			Assert::assertEquals($widget, $data[$id], 'Mismatch of data for widget ' . $id);
			Assert::assertStringEndsWith($widgetIconUrl, $dataIconUrl, 'Mismatch of icon URL for widget ' . $id);
		}
	}

	/**
	 * @Then /^user "([^"]*)" sees the following entries for dashboard widgets "([^"]*)"(?: \((v1)\))$/
	 *
	 * @param string $user
	 * @param string $widgetId
	 * @param string $apiVersion
	 * @param ?TableNode $formData
	 */
	public function userGetsDashboardWidgetItems($user, $widgetId, $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/dashboard/api/' . $apiVersion . '/widget-items?widgets[]=' . $widgetId);
		$this->assertStatusCode($this->response, 200);

		$data = $this->getDataFromResponse($this->response);

		Assert::assertArrayHasKey($widgetId, $data);
		$expectedItems = $formData->getColumnsHash();

		if (empty($expectedItems)) {
			Assert::assertEmpty($data[$widgetId]);
			return;
		}

		Assert::assertCount(count($expectedItems), $data[$widgetId]);

		foreach ($expectedItems as $key => $item) {
			$token = self::$identifierToToken[$item['link']];
			$item['link'] = $this->baseUrl . 'index.php/call/' . $token;
			$item['iconUrl'] = str_replace('{$BASE_URL}', $this->baseUrl, $item['iconUrl']);
			$item['iconUrl'] = str_replace('{token}', $token, $item['iconUrl']);

			Assert::assertEquals($item, $data[$widgetId][$key], 'Wrong details for item #' . $key);
		}
	}

	/**
	 * @Then /^user "([^"]*)" deletes message "([^"]*)" from room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userDeletesMessageFromRoom($user, $message, $identifier, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/' . self::$textToMessageId[$message],
			new TableNode([['message', $message]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" deletes chat history for room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userDeletesHistoryFromRoom(string $user, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier]
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" reads message "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userReadsMessageInRoom($user, $message, $identifier, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/read',
			new TableNode([['lastReadMessage', self::$textToMessageId[$message]]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" marks room "([^"]*)" as unread with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userMarkUnreadRoom($user, $identifier, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/read',
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" sends message "([^"]*)" with reference id "([^"]*)" to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $message
	 * @param string $referenceId
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSendsMessageWithReferenceIdToRoom($user, $message, $referenceId, $identifier, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier],
			new TableNode([['message', $message], ['referenceId', $referenceId]])
		);
		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages

		$response = $this->getDataFromResponse($this->response);
		if (isset($response['id'])) {
			self::$textToMessageId[$message] = $response['id'];
			self::$messageIdToText[$response['id']] = $message;
		}

		Assert::assertStringStartsWith($response['referenceId'], $referenceId);
	}

	/**
	 * @Then /^user "([^"]*)" sends reply ("[^"]*"|'[^']*') on message ("[^"]*"|'[^']*') to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $reply
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSendsReplyToRoom($user, $reply, $message, $identifier, $statusCode, $apiVersion = 'v1') {
		$reply = substr($reply, 1, -1);
		$message = substr($message, 1, -1);
		$replyTo = self::$textToMessageId[$message];

		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier],
			new TableNode([['message', $reply], ['replyTo', $replyTo]])
		);
		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages

		$response = $this->getDataFromResponse($this->response);
		if (isset($response['id'])) {
			self::$textToMessageId[$reply] = $response['id'];
			self::$messageIdToText[$response['id']] = $reply;
		}
	}

	/**
	 * @Then /^user "([^"]*)" sees the following messages in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSeesTheFollowingMessagesInRoom($user, $identifier, $statusCode, $apiVersion = 'v1', TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '?lookIntoFuture=0');
		$this->assertStatusCode($this->response, $statusCode);

		$this->compareDataResponse($formData);
	}

	/**
	 * @Then /^user "([^"]*)" searches for "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $search
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSearchesInRoom(string $user, string $search, string $identifier, $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/search/providers/talk-message-current/search?term=' . $search . '&from=' . '/call/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode !== '200') {
			return;
		}

		$this->compareSearchResponse($formData);
	}

	/**
	 * @Then /^user "([^"]*)" sees the following shared (media|audio|voice|file|deckcard|location|other) in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $objectType
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSeesTheFollowingSharedMediaInRoom($user, $objectType, $identifier, $statusCode, $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/share?objectType=' . $objectType);
		$this->assertStatusCode($this->response, $statusCode);

		$this->compareDataResponse($formData);
	}

	/**
	 * @Then /^user "([^"]*)" sees the following shared summarized overview in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSeesTheFollowingSharedOverviewMediaInRoom($user, $identifier, $statusCode, $apiVersion = 'v1', TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/share/overview');
		$this->assertStatusCode($this->response, $statusCode);

		$overview = $this->getDataFromResponse($this->response);
		$expected = $formData->getRowsHash();
		$summarized = array_map(function ($type) {
			return (string) count($type);
		}, $overview);
		Assert::assertEquals($expected, $summarized);
	}

	/**
	 * @Then /^user "([^"]*)" received a system messages in room "([^"]*)" to delete "([^"]*)"(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $message
	 * @param string $apiVersion
	 */
	public function userReceivedDeleteMessage($user, $identifier, $message, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '?lookIntoFuture=0');
		$this->assertStatusCode($this->response, 200);

		$actual = $this->getDataFromResponse($this->response);

		foreach ($actual as $m) {
			if ($m['systemMessage'] === 'message_deleted') {
				if (isset($m['parent']['id']) && $m['parent']['id'] === self::$textToMessageId[$message]) {
					return;
				}
			}
		}
		Assert::fail('Missing message_deleted system message for "' . $message . '"');
	}

	/**
	 * @Then /^user "([^"]*)" sees the following messages in room "([^"]*)" starting with "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $knownMessage
	 * @param string $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userAwaitsTheFollowingMessagesInRoom($user, $identifier, $knownMessage, $statusCode, $apiVersion = 'v1', TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '?lookIntoFuture=1&includeLastKnown=1&lastKnownMessageId=' . self::$textToMessageId[$knownMessage]);
		$this->assertStatusCode($this->response, $statusCode);

		$this->compareDataResponse($formData);
	}

	/**
	 * @param TableNode|null $formData
	 */
	protected function compareDataResponse(TableNode $formData = null) {
		$actual = $this->getDataFromResponse($this->response);
		$messages = [];
		array_map(function (array $message) use (&$messages) {
			// Filter out system messages
			if ($message['systemMessage'] === '') {
				$messages[] = $message;
			}
		}, $actual);

		foreach ($messages as $message) {
			// Include the received messages in the list of messages used for
			// replies; this is needed to get special messages not explicitly
			// sent like those for shared files.
			self::$textToMessageId[$message['message']] = $message['id'];
			if ($message['message'] === '{file}' && isset($message['messageParameters']['file']['name'])) {
				self::$textToMessageId['shared::file::' . $message['messageParameters']['file']['name']] = $message['id'];
				self::$messageIdToText[$message['id']] = 'shared::file::' . $message['messageParameters']['file']['name'];
			}
		}

		if ($formData === null) {
			Assert::assertEmpty($messages);
			return;
		}
		$includeParents = in_array('parentMessage', $formData->getRow(0), true);
		$includeReferenceId = in_array('referenceId', $formData->getRow(0), true);
		$includeReactions = in_array('reactions', $formData->getRow(0), true);
		$includeReactionsSelf = in_array('reactionsSelf', $formData->getRow(0), true);

		$expected = $formData->getHash();
		$count = count($expected);
		Assert::assertCount($count, $messages, 'Message count does not match');
		for ($i = 0; $i < $count; $i++) {
			if ($expected[$i]['messageParameters'] === '"IGNORE"') {
				$messages[$i]['messageParameters'] = 'IGNORE';
			}

			$result = preg_match('/POLL_ID\(([^)]+)\)/', $expected[$i]['messageParameters'], $matches);
			if ($result) {
				$expected[$i]['messageParameters'] = str_replace($matches[0], '"' . self::$questionToPollId[$matches[1]] . '"', $expected[$i]['messageParameters']);
			}
		}

		Assert::assertEquals($expected, array_map(function ($message) use ($includeParents, $includeReferenceId, $includeReactions, $includeReactionsSelf) {
			$data = [
				'room' => self::$tokenToIdentifier[$message['token']],
				'actorType' => $message['actorType'],
				'actorId' => ($message['actorType'] === 'guests')? self::$sessionIdToUser[$message['actorId']]: $message['actorId'],
				'actorDisplayName' => $message['actorDisplayName'],
				// TODO test timestamp; it may require using Runkit, php-timecop
				// or something like that.
				'message' => $message['message'],
				'messageParameters' => json_encode($message['messageParameters']),
			];
			if ($includeParents) {
				$data['parentMessage'] = $message['parent']['message'] ?? '';
			}
			if ($includeReferenceId) {
				$data['referenceId'] = $message['referenceId'];
			}
			if ($includeReactions) {
				$data['reactions'] = json_encode($message['reactions'], JSON_UNESCAPED_UNICODE);
			}
			if ($includeReactionsSelf) {
				if (isset($message['reactionsSelf'])) {
					$data['reactionsSelf'] = json_encode($message['reactionsSelf'], JSON_UNESCAPED_UNICODE);
				} else {
					$data['reactionsSelf'] = null;
				}
			}
			return $data;
		}, $messages));
	}

	/**
	 * @param TableNode|null $formData
	 */
	protected function compareSearchResponse(TableNode $formData = null) {
		$messages = $this->getDataFromResponse($this->response)['entries'];

		if ($formData === null) {
			Assert::assertEmpty($messages);
			return;
		}

		$expected = array_map(static function (array $message) {
			$message['attributes.conversation'] = self::$identifierToToken[$message['attributes.conversation']];
			$message['attributes.messageId'] = self::$textToMessageId[$message['attributes.messageId']];
			return $message;
		}, $formData->getHash());

		$count = count($expected);
		Assert::assertCount($count, $messages, 'Message count does not match');

		Assert::assertEquals($expected, array_map(static function ($message) {
			return [
				'title' => $message['title'],
				'subline' => $message['subline'],
				'attributes.conversation' => $message['attributes']['conversation'],
				'attributes.messageId' => $message['attributes']['messageId'],
			];
		}, $messages));
	}

	/**
	 * @Then /^user "([^"]*)" sees the following system messages in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSeesTheFollowingSystemMessagesInRoom($user, $identifier, $statusCode, $apiVersion = 'v1', TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '?lookIntoFuture=0');
		$this->assertStatusCode($this->response, $statusCode);

		$messages = $this->getDataFromResponse($this->response);
		$messages = array_filter($messages, function (array $message) {
			return $message['systemMessage'] !== '';
		});

		// Fix index gaps after the array_filter above
		$messages = array_values($messages);

		foreach ($messages as $systemMessage) {
			// Include the received system messages in the list of messages used
			// for replies.
			self::$textToMessageId[$systemMessage['systemMessage']] = $systemMessage['id'];
			self::$messageIdToText[$systemMessage['id']] = $systemMessage['systemMessage'];
		}

		if ($formData === null) {
			Assert::assertEmpty($messages);
			return;
		}

		$expected = $formData->getHash();

		Assert::assertCount(count($expected), $messages, 'Message count does not match');
		Assert::assertEquals($expected, array_map(function ($message, $expected) {
			$data = [
				'room' => self::$tokenToIdentifier[$message['token']],
				'actorType' => (string) $message['actorType'],
				'actorId' => ($message['actorType'] === 'guests') ? self::$sessionIdToUser[$message['actorId']] : (string) $message['actorId'],
				'systemMessage' => (string) $message['systemMessage'],
			];

			if (isset($expected['actorDisplayName'])) {
				$data['actorDisplayName'] = $message['actorDisplayName'];
			}

			if (isset($expected['message'])) {
				$data['message'] = $message['message'];
			}

			if (isset($expected['messageParameters'])) {
				$data['messageParameters'] = json_encode($message['messageParameters']);
			}

			return $data;
		}, $messages, $expected));
	}

	/**
	 * @Then /^user "([^"]*)" gets the following candidate mentions in room "([^"]*)" for "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $search
	 * @param string $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userGetsTheFollowingCandidateMentionsInRoomFor($user, $identifier, $search, $statusCode, $apiVersion = 'v1', TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/mentions?search=' . $search);
		$this->assertStatusCode($this->response, $statusCode);

		$mentions = $this->getDataFromResponse($this->response);

		if ($formData === null) {
			Assert::assertEmpty($mentions);
			return;
		}

		Assert::assertCount(count($formData->getHash()), $mentions, 'Mentions count does not match');

		usort($mentions, function ($a, $b) {
			if ($a['source'] === $b['source']) {
				return $a['label'] <=> $b['label'];
			}
			return $a['source'] <=> $b['source'];
		});

		$expected = $formData->getHash();
		usort($expected, function ($a, $b) {
			if ($a['source'] === $b['source']) {
				return $a['label'] <=> $b['label'];
			}
			return $a['source'] <=> $b['source'];
		});

		foreach ($expected as $key => $row) {
			if ($row['id'] === 'GUEST_ID') {
				Assert::assertRegExp('/^guest\/[0-9a-f]{40}$/', $mentions[$key]['id']);
				$mentions[$key]['id'] = 'GUEST_ID';
			}
			if (array_key_exists('avatar', $row)) {
				Assert::assertRegExp('/' . self::$identifierToToken[$row['avatar']] . '\/avatar/', $mentions[$key]['avatar']);
				unset($row['avatar']);
			}
			unset($mentions[$key]['avatar'], );
			Assert::assertEquals($row, $mentions[$key]);
		}
	}

	/**
	 * @Then /^user "([^"]*)" gets the following collaborator suggestions in room "([^"]*)" for "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $search
	 * @param string $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userGetsTheFollowingCollaboratorSuggestions($user, $identifier, $search, $statusCode, $apiVersion = 'v1', TableNode $formData = null) {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/core/autocomplete/get?search=' . $search . '&itemType=call&itemId=' . self::$identifierToToken[$identifier] . '&shareTypes[]=0&shareTypes[]=1&shareTypes[]=7&shareTypes[]=4');
		$this->assertStatusCode($this->response, $statusCode);

		$mentions = $this->getDataFromResponse($this->response);

		if ($formData === null) {
			Assert::assertEmpty($mentions);
			return;
		}

		Assert::assertCount(count($formData->getHash()), $mentions, 'Mentions count does not match');

		usort($mentions, function ($a, $b) {
			if ($a['source'] === $b['source']) {
				return $a['label'] <=> $b['label'];
			}
			return $a['source'] <=> $b['source'];
		});

		$expected = $formData->getHash();
		usort($expected, function ($a, $b) {
			if ($a['source'] === $b['source']) {
				return $a['label'] <=> $b['label'];
			}
			return $a['source'] <=> $b['source'];
		});

		foreach ($expected as $key => $row) {
			unset($mentions[$key]['icon']);
			unset($mentions[$key]['status']);
			unset($mentions[$key]['subline']);
			unset($mentions[$key]['shareWithDisplayNameUnique']);
			Assert::assertEquals($row, $mentions[$key]);
		}
	}

	/**
	 * @Then /^guest "([^"]*)" sets name to "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $name
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function guestSetsName($user, $name, $identifier, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/guest/' . self::$identifierToToken[$identifier] . '/name',
			new TableNode([['displayName', $name]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^last response has (no) last common read message header$/
	 *
	 * @param string $no
	 */
	public function hasNoChatLastCommonReadHeader($no) {
		Assert::assertArrayNotHasKey('X-Chat-Last-Common-Read', $this->response->getHeaders(), 'X-Chat-Last-Common-Read is set to ' . ($this->response->getHeader('X-Chat-Last-Common-Read')[0] ?? '0'));
	}

	/**
	 * @Then /^last response has last common read message header (set to|less than) "([^"]*)"$/
	 *
	 * @param string $setOrLower
	 * @param string $message
	 */
	public function hasChatLastCommonReadHeader($setOrLower, $message) {
		Assert::assertArrayHasKey('X-Chat-Last-Common-Read', $this->response->getHeaders());
		if ($setOrLower === 'set to') {
			Assert::assertEquals(self::$textToMessageId[$message], $this->response->getHeader('X-Chat-Last-Common-Read')[0]);
		} else {
			// Less than might be required for the first message, because the last read message before is the join/room creation message and we don't know that ID
			Assert::assertLessThan(self::$textToMessageId[$message], $this->response->getHeader('X-Chat-Last-Common-Read')[0]);
		}
	}

	/**
	 * @Then /^user "([^"]*)" creates (\d+) (automatic|manual|free) breakout rooms for "([^"]*)" with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param int $amount
	 * @param string $modeString
	 * @param string $identifier
	 * @param int $status
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userCreatesBreakoutRooms(string $user, int $amount, string $modeString, string $identifier, int $status, string $apiVersion, TableNode $formData = null): void {
		switch ($modeString) {
			case 'automatic':
				$mode = 1;
				break;
			case 'manual':
				$mode = 2;
				break;
			case 'free':
				$mode = 3;
				break;
			default:
				throw new \InvalidArgumentException('Invalid breakout room mode: ' . $modeString);
		}

		$data = [
			'mode' => $mode,
			'amount' => $amount,
		];

		if ($modeString === 'manual' && $formData instanceof TableNode) {
			$mapArray = [];
			foreach ($formData->getRowsHash() as $attendee => $roomNumber) {
				[$type, $id] = explode('::', $attendee);
				$attendeeId = $this->getAttendeeId($type, $id, $identifier);
				$mapArray[$attendeeId] = (int) $roomNumber;
			}
			$data['attendeeMap'] = json_encode($mapArray, JSON_THROW_ON_ERROR);
		}

		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier], $data);
		$this->assertStatusCode($this->response, $status);
	}

	/**
	 * @Then /^user "([^"]*)" moves participants into different breakout rooms for "([^"]*)" with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $status
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userMovesParticipantsInsideBreakoutRooms(string $user, string $identifier, int $status, string $apiVersion, TableNode $formData = null): void {
		$data = [];
		if ($formData instanceof TableNode) {
			$mapArray = [];
			foreach ($formData->getRowsHash() as $attendee => $roomNumber) {
				[$type, $id] = explode('::', $attendee);
				$attendeeId = $this->getAttendeeId($type, $id, $identifier);
				$mapArray[$attendeeId] = (int) $roomNumber;
			}
			$data['attendeeMap'] = json_encode($mapArray, JSON_THROW_ON_ERROR);
		}

		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier] . '/attendees', $data);
		$this->assertStatusCode($this->response, $status);
	}

	/**
	 * @Then /^user "([^"]*)" broadcasts message "([^"]*)" to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userBroadcastsMessageToBreakoutRooms(string $user, string $message, string $identifier, string $statusCode, string $apiVersion = 'v1') {
		$body = new TableNode([['message', $message]]);

		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier] . '/broadcast',
			$body
		);

		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages
	}

	/**
	 * @Then /^user "([^"]*)" (starts|stops) breakout rooms in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $startStop
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userStartsOrStopsBreakoutRooms(string $user, string $startStop, string $identifier, string $statusCode, string $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			$startStop === 'starts' ? 'POST' : 'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier] . '/rooms'
		);

		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" switches in room "([^"]*)" to breakout room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $target
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSwitchesBreakoutRoom(string $user, string $identifier, string $target, string $statusCode, string $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST',
			'/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier] . '/switch',
			[
				'target' => self::$identifierToToken[$target],
			]
		);

		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" (requests assistance|cancels request for assistance) in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $requestCancel
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userRequestsOrCancelsAssistanceInBreakoutRooms(string $user, string $requestCancel, string $identifier, string $statusCode, string $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			$requestCancel === 'requests assistance' ? 'POST' : 'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier] . '/request-assistance'
		);

		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" sets setting "([^"]*)" to "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $setting
	 * @param string $value
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSetting($user, $setting, $value, $statusCode, $apiVersion = 'v1') {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/settings/user',
			new TableNode([['key', $setting], ['value', $value]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" has capability "([^"]*)" set to "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $capability
	 * @param string $value
	 */
	public function userCheckCapability($user, $capability, $value) {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'GET', '/cloud/capabilities'
		);


		$data = $this->getDataFromResponse($this->response);
		$capabilities = $data['capabilities'];

		$keys = explode('=>', $capability);
		$finalKey = array_pop($keys);
		$cur = $capabilities;

		foreach ($keys as $key) {
			Assert::assertArrayHasKey($key, $cur);
			$cur = $cur[$key];
		}
		Assert::assertEquals($value, $cur[$finalKey]);
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 * @param ResponseInterface $response
	 * @return array
	 */
	protected function getDataFromResponse(ResponseInterface $response) {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		return $jsonBody['ocs']['data'];
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
	 * @Given the following :appId app config is set
	 *
	 * @param TableNode $formData
	 */
	public function setAppConfig(string $appId, TableNode $formData): void {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		foreach ($formData->getRows() as $row) {
			$this->sendRequest('POST', '/apps/provisioning_api/api/v1/config/apps/' . $appId . '/' . $row[0], [
				'value' => $row[1],
			]);
			$this->changedConfigs[$appId][] = $row[0];
		}
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Then user :user has the following notifications
	 *
	 * @param string $user
	 * @param TableNode|null $body
	 */
	public function userNotifications(string $user, TableNode $body = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'GET', '/apps/notifications/api/v2/notifications'
		);

		$data = $this->getDataFromResponse($this->response);

		if ($body === null) {
			Assert::assertCount(0, $data);
			return;
		}

		$this->assertNotifications($data, $body);
	}

	private function assertNotifications($notifications, TableNode $formData) {
		Assert::assertCount(count($formData->getHash()), $notifications, 'Notifications count does not match');
		Assert::assertEquals($formData->getHash(), array_map(function ($notification, $expectedNotification) {
			$data = [];
			if (isset($expectedNotification['object_id'])) {
				if (strpos($notification['object_id'], '/') !== false) {
					[$roomToken, $message] = explode('/', $notification['object_id']);
					$data['object_id'] = self::$tokenToIdentifier[$roomToken] . '/' . self::$messageIdToText[$message] ?? 'UNKNOWN_MESSAGE';
				} elseif (strpos($expectedNotification['object_id'], 'INVITE_ID') !== false) {
					$data['object_id'] = 'INVITE_ID(' . self::$inviteIdToRemote[$notification['object_id']] . ')';
				} else {
					[$roomToken,] = explode('/', $notification['object_id']);
					$data['object_id'] = self::$tokenToIdentifier[$roomToken];
				}
			}
			if (isset($expectedNotification['subject'])) {
				$data['subject'] = (string) $notification['subject'];
			}
			if (isset($expectedNotification['message'])) {
				$data['message'] = (string) $notification['message'];
				if (str_contains($expectedNotification['message'], '{{TOKEN}}')) {
					$data['message'] = str_replace($notification['object_id'], '{{TOKEN}}', $data['message']);
				}
			}
			if (isset($expectedNotification['object_type'])) {
				$data['object_type'] = (string) $notification['object_type'];
			}
			if (isset($expectedNotification['app'])) {
				$data['app'] = (string) $notification['app'];
			}

			return $data;
		}, $notifications, $formData->getHash()));
	}

	/**
	 * @Given /^guest accounts can be created$/
	 *
	 * @param TableNode $formData
	 */
	public function allowGuestAccountsCreation(): void {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');

		// save old state and restore at the end
		$this->sendRequest('GET', '/cloud/apps?filter=enabled');
		$this->assertStatusCode($this->response, 200);
		$data = $this->getDataFromResponse($this->response);
		$this->guestsAppWasEnabled = in_array('guests', $data['apps'], true);

		if (!$this->guestsAppWasEnabled) {
			// enable guests app
			/*
			$this->sendRequest('POST', '/cloud/apps/guests');
			$this->assertStatusCode($this->response, 200);
			 */
			// seems using provisioning API doesn't create tables...
			$this->runOcc(['app:enable', 'guests']);
		}

		// save previously set whitelist
		$this->sendRequest('GET', '/apps/provisioning_api/api/v1/config/apps/guests/whitelist');
		$this->assertStatusCode($this->response, 200);
		$this->guestsOldWhitelist = $this->getDataFromResponse($this->response)['data'];

		// set whitelist to allow spreed only
		$this->sendRequest('POST', '/apps/provisioning_api/api/v1/config/apps/guests/whitelist', [
			'value' => 'spreed',
		]);

		$this->setCurrentUser($currentUser);
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 */
	public function resetSpreedAppData() {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/apps/spreedcheats/');
		foreach ($this->changedConfigs as $appId => $configs) {
			foreach ($configs as $config) {
				$this->sendRequest('DELETE', '/apps/provisioning_api/api/v1/config/apps/' . $appId . '/' . $config);
			}
		}

		$this->setCurrentUser($currentUser);
	}

	/**
	 * @AfterScenario
	 */
	public function resetGuestsAppState() {
		if ($this->guestsAppWasEnabled === null) {
			// guests app was not touched
			return;
		}

		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');

		if ($this->guestsOldWhitelist) {
			// restore old whitelist
			$this->sendRequest('POST', '/apps/provisioning_api/api/v1/config/apps/guests/whitelist', [
				'value' => $this->guestsOldWhitelist,
			]);
		} else {
			// restore to default
			$this->sendRequest('DELETE', '/apps/provisioning_api/api/v1/config/apps/guests/whitelist');
		}

		// restore app's enabled state
		$this->sendRequest($this->guestsAppWasEnabled ? 'POST' : 'DELETE', '/cloud/apps/guests');

		$this->setCurrentUser($currentUser);
		$this->guestsAppWasEnabled = null;
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
		$response = $this->userExists($user);
		if ($response->getStatusCode() !== 200) {
			$this->createUser($user);
			// Set a display name different than the user ID to be able to
			// ensure in the tests that the right value was returned.
			$this->setUserDisplayName($user);
			$response = $this->userExists($user);
			$this->assertStatusCode($response, 200);
		}
	}

	/**
	 * @Given /^user "([^"]*)" is a guest account user/
	 * @param string $email email address
	 */
	public function createGuestUser($email) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		// in case it exists
		$this->deleteUser($email);

		$lastCode = $this->runOcc([
			'guests:add',
			// creator user
			'admin',
			// email
			$email,
			'--display-name',
			$email . '-displayname',
			'--password-from-env',
		], [
			'OC_PASS' => self::TEST_PASSWORD,
		]);
		Assert::assertEquals(0, $lastCode, 'Guest creation succeeded for ' . $email);

		$this->createdGuestAccountUsers[] = $email;
		$this->setCurrentUser($currentUser);
	}

	private function userExists($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('GET', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);
		return $this->response;
	}

	private function createUser($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('POST', '/cloud/users', [
			'userid' => $user,
			'password' => self::TEST_PASSWORD,
		]);
		$this->assertStatusCode($this->response, 200, 'Failed to create user');

		//Quick hack to login once with the current user
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/cloud/users' . '/' . $user);
		$this->assertStatusCode($this->response, 200, 'Failed to do first login');

		$this->createdUsers[] = $user;

		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Given /^user "([^"]*)" is deleted$/
	 * @param string $user
	 */
	public function userIsDeleted($user) {
		$deleted = false;

		$this->deleteUser($user);

		$response = $this->userExists($user);
		$deleted = $response->getStatusCode() === 404;

		if (!$deleted) {
			Assert::fail("User $user exists");
		}
	}

	private function deleteUser($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);

		unset($this->createdUsers[array_search($user, $this->createdUsers, true)]);

		return $this->response;
	}

	private function setUserDisplayName($user) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('PUT', '/cloud/users/' . $user, [
			'key' => 'displayname',
			'value' => $user . '-displayname'
		]);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Given /^group "([^"]*)" exists$/
	 * @param string $group
	 */
	public function assureGroupExists($group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('POST', '/cloud/groups', [
			'groupid' => $group,
		]);

		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		if (isset($jsonBody['ocs']['meta'])) {
			// 102 = group exists
			// 200 = created with success
			Assert::assertContains(
				$jsonBody['ocs']['meta']['statuscode'],
				[102, 200],
				$jsonBody['ocs']['meta']['message']
			);
		} else {
			throw new \Exception('Invalid response when create group');
		}

		$this->setCurrentUser($currentUser);

		$this->createdGroups[] = $group;
	}

	private function deleteGroup($group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/cloud/groups/' . $group);
		$this->setCurrentUser($currentUser);

		unset($this->createdGroups[array_search($group, $this->createdGroups, true)]);
	}

	/**
	 * @When /^user "([^"]*)" is member of group "([^"]*)"$/
	 * @param string $user
	 * @param string $group
	 */
	public function addingUserToGroup($user, $group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('POST', "/cloud/users/$user/groups", [
			'groupid' => $group,
		]);
		$this->assertStatusCode($this->response, 200);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @When /^user "([^"]*)" is not member of group "([^"]*)"$/
	 * @param string $user
	 * @param string $group
	 */
	public function removeUserFromGroup($user, $group) {
		$currentUser = $this->currentUser;
		$this->setCurrentUser('admin');
		$this->sendRequest('DELETE', "/cloud/users/$user/groups", [
			'groupid' => $group,
		]);
		$this->assertStatusCode($this->response, 200);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Given /^user "([^"]*)" (delete react|react) with "([^"]*)" on message "([^"]*)" to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userReactWithOnMessageToRoomWith(string $user, string $action, string $reaction, string $message, string $identifier, int $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$token = self::$identifierToToken[$identifier];
		$messageId = self::$textToMessageId[$message];
		$this->setCurrentUser($user);
		$verb = $action === 'react' ? 'POST' : 'DELETE';
		$this->sendRequest($verb, '/apps/spreed/api/' . $apiVersion . '/reaction/' . $token . '/' . $messageId, [
			'reaction' => $reaction
		]);
		$this->assertStatusCode($this->response, $statusCode);
		$this->assertReactionList($formData);
	}

	/**
	 * @Given /^user "([^"]*)" retrieve reactions "([^"]*)" of message "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userRetrieveReactionsOfMessageInRoomWith(string $user, string $reaction, string $message, string $identifier, int $statusCode, string $apiVersion = 'v1', ?TableNode $formData = null): void {
		$token = self::$identifierToToken[$identifier];
		$messageId = self::$textToMessageId[$message];
		$this->setCurrentUser($user);
		$reaction = $reaction !== 'all' ? '?reaction=' . $reaction : '';
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/reaction/' . $token . '/' . $messageId . $reaction);
		$this->assertStatusCode($this->response, $statusCode);
		$this->assertReactionList($formData);
	}

	private function assertReactionList(?TableNode $formData): void {
		$expected = [];
		if (!$formData instanceof TableNode) {
			return;
		}
		foreach ($formData->getHash() as $row) {
			$reaction = $row['reaction'];
			unset($row['reaction']);
			$expected[$reaction][] = $row;
		}

		$result = $this->getDataFromResponse($this->response);
		$result = array_map(static function ($reaction, $list) use ($expected): array {
			$list = array_map(function ($reaction) {
				unset($reaction['timestamp']);
				$reaction['actorId'] = ($reaction['actorType'] === 'guests') ? self::$sessionIdToUser[$reaction['actorId']] : (string) $reaction['actorId'];
				return $reaction;
			}, $list);
			Assert::assertArrayHasKey($reaction, $expected, 'Not expected reaction: ' . $reaction);
			Assert::assertCount(count($list), $expected[$reaction], 'Reaction count by type does not match');

			usort($expected[$reaction], [self::class, 'sortAttendees']);
			usort($list, [self::class, 'sortAttendees']);
			Assert::assertEquals($expected[$reaction], $list, 'Reaction list by type does not match');
			return $list;
		}, array_keys($result), array_values($result));
		Assert::assertCount(count($expected), $result, 'Reaction count does not match');
	}

	/**
	 * @Given user :user set the message expiration to :messageExpiration of room :identifier with :statusCode (:apiVersion)
	 */
	public function userSetTheMessageExpirationToXWithStatusCode(string $user, int $messageExpiration, string $identifier, int $statusCode, string $apiVersion = 'v4'): void {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' .  $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/message-expiration', [
			'seconds' => $messageExpiration,
		]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When wait for :seconds second
	 */
	public function waitForXSecond($seconds): void {
		sleep($seconds);
	}

	/**
	 * @When wait for :seconds seconds
	 */
	public function waitForXSeconds($seconds): void {
		sleep($seconds);
	}

	/*
	 * Requests
	 */

	/**
	 * @Given /^user "([^"]*)" logs in$/
	 * @param string $user
	 */
	public function userLogsIn(string $user) {
		$loginUrl = $this->baseUrl . '/login';

		$cookieJar = $this->getUserCookieJar($user);

		// Request a new session and extract CSRF token
		$client = new Client();
		$this->response = $client->get(
			$loginUrl,
			[
				'cookies' => $cookieJar,
			]
		);

		$requestToken = $this->extractRequestTokenFromResponse($this->response);

		// Login and extract new token
		$password = ($user === 'admin') ? 'admin' : self::TEST_PASSWORD;
		$client = new Client();
		$this->response = $client->post(
			$loginUrl,
			[
				'form_params' => [
					'user' => $user,
					'password' => $password,
					'requesttoken' => $requestToken,
				],
				'cookies' => $cookieJar,
			]
		);

		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @When /^user "([^"]*)" uploads file "([^"]*)" as avatar of room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userSendTheFileAsAvatarOfRoom(string $user, string $file, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);
		$options = [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => $file !== 'invalid' ? fopen(__DIR__ . '/../../../..' . $file, 'r') : '',
				],
			],
		];
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/avatar', null, [], $options);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^the room "([^"]*)" has an avatar with (\d+)(?: \((v1)\))?$/
	 */
	public function theRoomNeedToHaveAnAvatarWithStatusCode(string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/avatar');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" delete the avatar of room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userDeleteTheAvatarOfRoom(string $user, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/avatar');
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" starts "(invalid|audio|video)" recording in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userStartRecordingInRoom(string $user, string $recordingType, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$recordingTypes = [
			'invalid' => -1,
			'video' => 1,
			'audio' => 2,
		];

		$data = [
			'status' => $recordingTypes[$recordingType]
		];

		$this->setCurrentUser($user);
		$roomToken = self::$identifierToToken[$identifier];
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/recording/' . $roomToken, $data);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" stops recording in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userStopRecordingInRoom(string $user, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);
		$roomToken = self::$identifierToToken[$identifier];
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/recording/' . $roomToken);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" store recording file "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userStoreRecordingFileInRoom(string $user, string $file, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);

		$recordingServerSharedSecret = 'the secret';
		$this->setAppConfig('spreed', new TableNode([['recording_servers', json_encode(['secret' => $recordingServerSharedSecret])]]));
		$validRandom = md5((string) rand());
		$validChecksum = hash_hmac('sha256', $validRandom . self::$identifierToToken[$identifier], $recordingServerSharedSecret);
		$headers = [
			'TALK_RECORDING_RANDOM' => $validRandom,
			'TALK_RECORDING_CHECKSUM' => $validChecksum,
		];
		$options = ['multipart' => [['name' => 'owner', 'contents' => $user]]];
		if ($file === 'invalid') {
			// Create invalid content
			$options['multipart'][] = [
				'name' => 'file',
				'contents' => '',
			];
		} elseif ($file === 'big') {
			// More details about MAX_FILE_SIZE follow the link:
			// https://www.php.net/manual/en/features.file-upload.post-method.php
			$options['multipart'][] = [
				'name' => 'MAX_FILE_SIZE',
				'contents' => 1, // Limit the max file size to 1
			];
			// Create file with big content
			$contents = tmpfile();
			fwrite($contents, 'fake content'); // Bigger than 1
			$options['multipart'][] = [
				'name' => 'file',
				'contents' => $contents,
				'filename' => 'audio.ogg', // to get the mimetype by extension and do the upload
			];
		} else {
			// Upload a file
			$options['multipart'][] = [
				'name' => 'file',
				'contents' => fopen(__DIR__ . '/../../../..' . $file, 'r'),
			];
		}
		$this->sendRequest(
			'POST',
			'/apps/spreed/api/' . $apiVersion . '/recording/' . self::$identifierToToken[$identifier] . '/store',
			null,
			$headers,
			$options
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^user "([^"]*)" set status to "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function setUserStatus(string $user, string $status, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT',
			'/apps/user_status/api/' . $apiVersion . '/user_status/status',
			new TableNode([['statusType', $status]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then the response error matches with :error
	 */
	public function assertResponseErrorMatchesWith(string $error): void {
		$responseData = $this->getDataFromResponse($this->response);
		Assert::assertEquals(['error' => $error], $responseData);
	}

	/**
	 * @param ResponseInterface $response
	 * @return string
	 */
	private function extractRequestTokenFromResponse(ResponseInterface $response): string {
		return substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);
	}

	/**
	 * @When /^sending "([^"]*)" to "([^"]*)" with$/
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|array|null $body
	 * @param array $headers
	 */
	public function sendRequest($verb, $url, $body = null, array $headers = [], array $options = []) {
		$fullUrl = $this->baseUrl . 'ocs/v2.php' . $url;
		$this->sendRequestFullUrl($verb, $fullUrl, $body, $headers, $options);
	}

	/**
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|array|null $body
	 * @param array $headers
	 */
	public function sendRemoteRequest($verb, $url, $body = null, array $headers = []) {
		$fullUrl = $this->baseRemoteUrl . 'ocs/v2.php' . $url;
		$this->sendRequestFullUrl($verb, $fullUrl, $body, $headers);
	}

	/**
	 * @param string $verb
	 * @param string $fullUrl
	 * @param TableNode|array|null $body
	 * @param array $headers
	 */
	public function sendRequestFullUrl($verb, $fullUrl, $body = null, array $headers = [], array $options = []) {
		$client = new Client();
		$options = array_merge($options, ['cookies' => $this->getUserCookieJar($this->currentUser)]);
		if ($this->currentUser === 'admin') {
			$options['auth'] = ['admin', 'admin'];
		} elseif (strpos($this->currentUser, 'guest') !== 0) {
			$options['auth'] = [$this->currentUser, self::TEST_PASSWORD];
		}
		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			$options['form_params'] = $fd;
		} elseif (is_array($body)) {
			$options['form_params'] = $body;
		} elseif (is_string($body)) {
			$options['body'] = $body;
		}

		$options['headers'] = array_merge($headers, [
			'OCS-ApiRequest' => 'true',
			'Accept' => 'application/json',
		]);

		try {
			$this->response = $client->{$verb}($fullUrl, $options);
		} catch (ClientException $ex) {
			$this->response = $ex->getResponse();
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
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
	 * @param string $message
	 */
	protected function assertStatusCode(ResponseInterface $response, int $statusCode, string $message = '') {
		if ($statusCode !== $response->getStatusCode()) {
			$content = $this->response->getBody()->getContents();
			Assert::assertEquals(
				$statusCode,
				$response->getStatusCode(),
				$message . ($message ? ': ' : '') . $content
			);
		} else {
			Assert::assertEquals($statusCode, $response->getStatusCode(), $message);
		}
	}
}
