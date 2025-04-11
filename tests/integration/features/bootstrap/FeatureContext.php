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

	/** @var array<string, string> */
	protected static array $identifierToToken;
	/** @var array<string, int> */
	protected static array $identifierToId;
	/** @var array<string, string> */
	protected static array $tokenToIdentifier;
	/** @var array<string, string> */
	protected static array $sessionIdToUser;
	/** @var array<string, string> */
	protected static array $userToSessionId;
	/** @var array<string, int> */
	protected static array $userToAttendeeId;
	/** @var array<string, int> */
	protected static array $textToMessageId;
	/** @var array<int, string> */
	protected static array $messageIdToText;
	/** @var array<string, int> */
	protected static array $remoteToInviteId;
	/** @var array<int, string> */
	protected static array $inviteIdToRemote;
	/** @var array<string, string> */
	protected static array $remoteAuth;
	/** @var array<string, int> */
	protected static array $questionToPollId;
	/** @var array[] */
	protected static array $lastNotifications;
	/** @var array<int, string> */
	protected static array $botIdToName;
	/** @var array<string, int> */
	protected static array $botNameToId;
	/** @var array<string, string> */
	protected static array $botNameToHash;
	/** @var array<string, string> */
	protected static array $phoneNumberToActorId;
	/** @var array<string, mixed>|null */
	protected static ?array $nextChatRequestParameters = null;
	/** @var array<string, int> */
	protected static array $modifiedSince;


	protected static array $permissionsMap = [
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

	protected ?string $currentUser = null;

	private ?ResponseInterface $response;

	/** @var CookieJar[] */
	private array $cookieJars;

	protected string $baseUrl;

	protected string $baseRemoteUrl;

	/** @var string[] */
	protected array $createdUsers = [];

	/** @var string[] */
	protected array $createdGroups = [];

	/** @var string[] */
	protected array $createdGuestAccountUsers = [];

	/** @var array */
	protected array $changedConfigs = [];

	protected bool $changedBruteforceSetting = false;

	private ?SharingContext $sharingContext;

	private ?bool $guestsAppWasEnabled = null;

	private string $guestsOldWhitelist;

	use CommandLineTrait;
	use RecordingTrait;

	public static function getTokenForIdentifier(string $identifier) {
		return self::$identifierToToken[$identifier];
	}

	public static function getMessageIdForText(string $text): int {
		return self::$textToMessageId[$text];
	}

	public static function getActorIdForPhoneNumber(string $phoneNumber): string {
		return self::$phoneNumberToActorId[$phoneNumber];
	}

	public static function getAttendeeIdForPhoneNumber(string $identifier, string $phoneNumber): string {
		return self::$userToAttendeeId[$identifier]['phones'][self::$phoneNumberToActorId[$phoneNumber]];
	}

	public static function getSessionIdForUser(string $user): string {
		return self::$userToSessionId[$user];
	}

	public function getAttendeeId(string $type, string $id, string $room, string $user = null) {
		if (!isset(self::$userToAttendeeId[$room][$type][$id])) {
			if ($user !== null) {
				$this->userLoadsAttendeeIdsInRoom($user, $room, 'v4');
			} else {
				throw new \Exception('Attendee id unknown, please call userLoadsAttendeeIdsInRoom with a user that has access before');
			}
		}

		if (!isset(self::$userToAttendeeId[$room][$type][$id])) {
			throw new \Exception('Attendee id unknown, please call userLoadsAttendeeIdsInRoom with a user that has access before');
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
	}

	/**
	 * @BeforeScenario
	 */
	public function setUp() {
		self::$identifierToToken = [];
		self::$identifierToId = [];
		self::$botNameToId = [];
		self::$tokenToIdentifier = [];
		self::$sessionIdToUser = [
			'cli' => 'cli',
			'system' => 'system',
			'failed-to-get-session' => 'failed-to-get-session',
		];
		self::$userToSessionId = [];
		self::$userToAttendeeId = [];
		self::$textToMessageId = [];
		self::$messageIdToText = [];
		self::$questionToPollId = [];
		self::$lastNotifications = [];
		self::$phoneNumberToActorId = [];
		self::$modifiedSince = [];
		self::$botNameToId = [];

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
			$this->deleteGuestUser($user);
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
	 * @Then /^user "([^"]*)" is participant of the following (unordered )?(note-to-self )?(modified-since )?rooms \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $shouldOrder
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userIsParticipantOfRooms(string $user, string $shouldOrder, string $shouldFilter, string $modifiedSince, string $apiVersion, TableNode $formData = null): void {
		$parameters = '';
		if ($modifiedSince !== '') {
			if (!isset(self::$modifiedSince[$user])) {
				throw new \RuntimeException('Must run once without "modified-since" before');
			}
			$parameters .= '?modifiedSince=' . self::$modifiedSince[$user];
		}

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room' . $parameters);
		$this->assertStatusCode($this->response, 200);
		self::$modifiedSince[$user] = time();

		$rooms = $this->getDataFromResponse($this->response);

		if ($shouldFilter === '') {
			$rooms = array_filter($rooms, static function (array $room) {
				// Filter out "Talk updates" and "Note to self" conversations
				return $room['type'] !== 4 && $room['type'] !== 6;
			});
		} elseif ($shouldFilter === 'note-to-self ') {
			$rooms = array_filter($rooms, static function (array $room) {
				// Filter out "Talk updates" conversations
				return $room['type'] !== 4;
			});
		}

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

		$rooms = array_filter($rooms, static function (array $room) {
			// Filter out "Talk updates" and "Note to self" conversations
			return $room['type'] !== 4 && $room['type'] !== 6;
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
	private function assertRooms(array $rooms, TableNode $formData, bool $shouldOrder = false) {
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

				if ($idA === $idB) {
					if (isset($roomA['remoteServer'], $roomB['remoteServer'])) {
						return $roomA['remoteServer'] < $roomB['remoteServer'] ? -1 : 1;
					}
					if (isset($roomA['remoteServer'])) {
						return 1;
					}
					if (isset($roomB['remoteServer'])) {
						return -1;
					}
				}

				return $idA < $idB ? -1 : 1;
			};

			usort($rooms, $sorter);
			usort($expected, $sorter);
		}

		Assert::assertEquals($expected, array_map(function (array $room, array $expectedRoom): array {
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
				if (str_starts_with($expectedRoom['name'], '/') && preg_match($expectedRoom['name'], $room['name'])) {
					$data['name'] = $expectedRoom['name'];
				}
			}
			if (isset($expectedRoom['description'])) {
				$data['description'] = $room['description'];
			}
			if (isset($expectedRoom['type'])) {
				$data['type'] = (string) $room['type'];
			}
			if (isset($expectedRoom['remoteServer'])) {
				$data['remoteServer'] = isset($room['remoteServer']) ? self::translateRemoteServer($room['remoteServer']) : '';
			}
			if (isset($expectedRoom['remoteToken'])) {
				if (isset($room['remoteToken'])) {
					$data['remoteToken'] = self::$tokenToIdentifier[$room['remoteToken']] ?? 'unknown-token';
				} else {
					$data['remoteToken'] = '';
				}
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
			if (isset($expectedRoom['lastMessageActorType'])) {
				$data['lastMessageActorType'] = $room['lastMessage'] ? $room['lastMessage']['actorType'] : '';
			}
			if (isset($expectedRoom['lastMessageActorId'])) {
				$data['lastMessageActorId'] = $room['lastMessage'] ? $room['lastMessage']['actorId'] : '';
				$data['lastMessageActorId'] = str_replace(rtrim($this->baseUrl, '/'), '{$BASE_URL}', $data['lastMessageActorId']);
				$data['lastMessageActorId'] = str_replace(rtrim($this->baseRemoteUrl, '/'), '{$REMOTE_URL}', $data['lastMessageActorId']);
			}
			if (isset($expectedRoom['lastReadMessage'])) {
				$data['lastReadMessage'] = self::$messageIdToText[(int) $room['lastReadMessage']] ?? (!$room['lastReadMessage'] ? 'ZERO': 'UNKNOWN_MESSAGE');
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
			if (isset($expectedRoom['recordingConsent'])) {
				$data['recordingConsent'] = (int) $room['recordingConsent'];
			}
			if (isset($expectedRoom['permissions'])) {
				$data['permissions'] = $this->mapPermissionsAPIOutput($room['permissions']);
			}
			if (isset($expectedRoom['permissions'])) {
				$data['permissions'] = $this->mapPermissionsAPIOutput($room['permissions']);
			}
			if (isset($expectedRoom['attendeePermissions'])) {
				$data['attendeePermissions'] = $this->mapPermissionsAPIOutput($room['attendeePermissions']);
			}
			if (isset($expectedRoom['callPermissions'])) {
				$data['callPermissions'] = $this->mapPermissionsAPIOutput($room['callPermissions']);
			}
			if (isset($expectedRoom['defaultPermissions'])) {
				$data['defaultPermissions'] = $this->mapPermissionsAPIOutput($room['defaultPermissions']);
			}
			if (isset($expectedRoom['participants'])) {
				throw new \Exception('participants key needs to be checked via participants endpoint');
			}

			return $data;
		}, $rooms, $expected));
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
			Assert::assertEmpty($invites, json_encode($invites, JSON_PRETTY_PRINT));
			return;
		}

		$this->assertInvites($invites, $formData);

		foreach ($invites as $data) {
			self::$remoteToInviteId[$this->translateRemoteServer($data['remoteServerUrl']) . '::' . self::$tokenToIdentifier[$data['remoteToken']]] = $data['id'];
			self::$inviteIdToRemote[$data['id']] = $this->translateRemoteServer($data['remoteServerUrl']) . '::' . self::$tokenToIdentifier[$data['remoteToken']];
			self::$identifierToToken['LOCAL::' . $data['roomName']] = $data['localToken'];
		}
	}

	/**
	 * @Then /^user "([^"]*)" (accepts|declines) invite to room "([^"]*)" of server "([^"]*)" with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param string $server
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userAcceptsDeclinesRemoteInvite(string $user, string $acceptsDeclines, string $identifier, string $server, int $status, string $apiVersion, TableNode $formData = null): void {
		$inviteId = self::$remoteToInviteId[$server . '::' . $identifier];

		$verb = $acceptsDeclines === 'accepts' ? 'POST' : 'DELETE';

		$this->setCurrentUser($user);
		if ($server === 'LOCAL') {
			$this->sendRemoteRequest($verb, '/apps/spreed/api/' . $apiVersion . '/federation/invitation/' . $inviteId);
		}
		$this->assertStatusCode($this->response, $status);
		$response = $this->getDataFromResponse($this->response);

		if ($formData) {
			if ($status === 200) {
				if (!isset(self::$tokenToIdentifier[$response['token']])) {
					self::$tokenToIdentifier[$response['token']] = $server . '::' . $identifier;
				}

				$this->assertRooms([$response], $formData);
			} else {
				Assert::assertSame($formData->getRowsHash(), $response);
			}
		} else {
			Assert::assertEmpty($response);
		}
	}

	/**
	 * @param array $invites
	 * @param TableNode $formData
	 */
	private function assertInvites($invites, TableNode $formData) {
		Assert::assertCount(count($formData->getHash()), $invites, 'Invite count does not match');
		$expectedInvites = array_map(static function ($expectedInvite): array {
			if (isset($expectedInvite['state'])) {
				$expectedInvite['state'] = (int) $expectedInvite['state'];
			}
			return $expectedInvite;
		}, $formData->getHash());

		Assert::assertEquals($expectedInvites, array_map(function ($invite, $expectedInvite): array {
			$data = [];
			if (isset($expectedInvite['id'])) {
				$data['id'] = self::$tokenToIdentifier[$invite['token']];
			}
			if (isset($expectedInvite['inviterCloudId'])) {
				$data['inviterCloudId'] = $invite['inviterCloudId'];
			}
			if (isset($expectedInvite['inviterDisplayName'])) {
				$data['inviterDisplayName'] = $invite['inviterDisplayName'];
			}
			if (isset($expectedInvite['remoteToken'])) {
				$data['remoteToken'] = self::$tokenToIdentifier[$invite['remoteToken']] ?? 'unknown-token';
			}
			if (isset($expectedInvite['remoteServerUrl'])) {
				$data['remoteServerUrl'] = $this->translateRemoteServer($invite['remoteServerUrl']);
			}
			if (isset($expectedInvite['state'])) {
				$data['state'] = $invite['state'];
			}
			if (isset($expectedInvite['localCloudId'])) {
				$data['localCloudId'] = $invite['localCloudId'];
			}

			return $data;
		}, $invites, $expectedInvites));
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
			// Filter out "Talk updates" and "Note to self" conversations
			return $room['type'] !== 4 && $room['type'] !== 6;
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
				if (isset($expectedKeys['displayName'])) {
					$data['displayName'] = (string) $attendee['displayName'];
				}
				if (isset($expectedKeys['phoneNumber'])) {
					$data['phoneNumber'] = (string) $attendee['phoneNumber'];
				}
				if (isset($expectedKeys['callId'])) {
					$data['callId'] = (string) $attendee['callId'];
				}

				if (!isset(self::$userToAttendeeId[$identifier][$attendee['actorType']])) {
					self::$userToAttendeeId[$identifier][$attendee['actorType']] = [];
				}
				self::$userToAttendeeId[$identifier][$attendee['actorType']][$attendee['actorId']] = $attendee['attendeeId'];

				if (!empty($attendee['phoneNumber'])) {
					self::$phoneNumberToActorId[$attendee['phoneNumber']] = $attendee['actorId'];
				}

				$result[] = $data;
			}
			usort($result, [self::class, 'sortAttendees']);

			$expected = array_map(function ($attendee, $actual) {
				if (isset($attendee['actorId']) && substr($attendee['actorId'], 0, strlen('"guest')) === '"guest') {
					$attendee['actorId'] = sha1(self::$userToSessionId[trim($attendee['actorId'], '"')]);
				}

				if (isset($attendee['actorId'], $attendee['actorType']) && $attendee['actorType'] === 'federated_users') {
					$attendee['actorId'] .= '@' . rtrim($this->baseRemoteUrl, '/');
				}

				if (isset($attendee['actorId'], $attendee['actorType'], $attendee['phoneNumber'])
					&& $attendee['actorType'] === 'phones'
					&& str_starts_with($attendee['actorId'], 'PHONE(')) {
					$matched = preg_match('/PHONE\((\+\d+)\)/', $attendee['actorId'], $matches);
					if ($matched) {
						$attendee['actorId'] = self::$phoneNumberToActorId[$matches[1]];
					}
				}

				// Breakout room regex
				if (isset($attendee['actorId']) && strpos($attendee['actorId'], '/') === 0 && preg_match($attendee['actorId'], $actual['actorId'])) {
					$attendee['actorId'] = $actual['actorId'];
				}

				if (isset($attendee['participantType'])) {
					$attendee['participantType'] = (string)$this->mapParticipantTypeTestInput($attendee['participantType']);
				}

				if (isset($attendee['actorType']) && $attendee['actorType'] === 'phones') {
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
	 * @Then /^user "([^"]*)" creates note-to-self \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $apiVersion
	 */
	public function userCreatesNoteToSelf(string $user, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/note-to-self');
		$this->assertStatusCode($this->response, 200);

		$response = $this->getDataFromResponse($this->response);
		self::$identifierToToken[$user . '-note-to-self'] = $response['token'];
		self::$identifierToId[$user . '-note-to-self'] = $response['id'];
		self::$tokenToIdentifier[$response['token']] = $user . '-note-to-self';
	}

	/**
	 * @Then /^user "([^"]*)" reset note-to-self preference$/
	 *
	 * @param string $user
	 */
	public function userResetNoteToSelfPreference(string $user): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/provisioning_api/api/v1/config/users/spreed/note_to_self');
		$this->assertStatusCode($this->response, 200);
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
		$this->setCurrentUser($user);

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
		} elseif ($this->currentUser !== null) {
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
		$this->userJoinsRoomWithNamedSession($user, $identifier, $statusCode, $apiVersion, '', $formData);
	}

	/**
	 * @Then /^user "([^"]*)" joins room "([^"]*)" with (\d+) \((v4)\) session name "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userJoinsRoomWithNamedSession(string $user, string $identifier, int $statusCode, string $apiVersion, string $sessionName, TableNode $formData = null): void {
		$this->setCurrentUser($user, $identifier);
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
			if ($sessionName) {
				self::$userToSessionId[$user . '#' . $sessionName] = $response['sessionId'];
			}
			if (!isset(self::$userToAttendeeId[$identifier][$response['actorType']])) {
				self::$userToAttendeeId[$identifier][$response['actorType']] = [];
			}
			self::$userToAttendeeId[$identifier][$response['actorType']][$response['actorId']] = $response['attendeeId'];
		}
	}

	/**
	 * @Then /^user "([^"]*)" resends invite for room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userResendsInvite(string $user, string $identifier, int $statusCode, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);

		/** @var ?array $body */
		$body = null;
		if ($formData instanceof TableNode) {
			$attendee = $formData?->getRowsHash()['attendeeId'] ?? '';
			if (isset(self::$userToAttendeeId[$identifier]['emails'][$attendee])) {
				$body = [
					'attendeeId' => self::$userToAttendeeId[$identifier]['emails'][$attendee],
				];
			} elseif (str_starts_with($attendee, 'not-found')) {
				$body = [
					'attendeeId' => max(self::$userToAttendeeId[$identifier]['emails']) + 1000,
				];
			} else {
				throw new \InvalidArgumentException('Unknown attendee, did you pull participants?');
			}
		}

		$this->sendRequest(
			'POST', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants/resend-invitations',
			$body
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" sets session state to (\d) in room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSessionState(string $user, int $state, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/participants/state',
			['state' => $state]
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" views call-URL of room "([^"]*)" with (\d+)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $statusCode
	 * @param null|TableNode $formData
	 */
	public function userViewsCallURL(string $user, string $identifier, int $statusCode, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendFrontpageRequest(
			'GET', '/call/' . (self::$identifierToToken[$identifier] ?? $identifier)
		);

		$this->assertStatusCode($this->response, $statusCode);

		if ($formData instanceof TableNode) {
			$content = $this->response->getBody()->getContents();
			foreach ($formData->getRows() as $line) {
				Assert::assertStringContainsString($line[0], $content);
			}
		}
	}

	/**
	 * @Then /^user "([^"]*)" views URL "([^"]*)" with query parameters and status code (\d+)$/
	 *
	 * @param string $user
	 * @param string $page
	 * @param int $statusCode
	 * @param null|TableNode $formData
	 */
	public function userViewsURLWithQuery(string $user, string $page, int $statusCode, TableNode $formData = null): void {
		$parameters = [];
		if ($formData instanceof TableNode) {
			foreach ($formData->getRowsHash() as $key => $value) {
				$parameters[$key] = $key === 'token' ? (self::$identifierToToken[$value] ?? $value) : $value;
			}
		}

		$this->setCurrentUser($user);
		$this->sendFrontpageRequest(
			'GET', '/' . $page . '?' . http_build_query($parameters)
		);

		$this->assertStatusCode($this->response, $statusCode);
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
	 * @Then /^user "([^"]*)" removes (user|group|email|remote) "([^"]*)" from room "([^"]*)" with (\d+) \((v4)\)$/
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
			if ($actorType === 'remote') {
				$actorId .= '@' . rtrim($this->baseRemoteUrl, '/');
				$actorType = 'federated_user';
			}

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
		$this->setCurrentUser($user, $identifier);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier]);
		$this->assertStatusCode($this->response, $statusCode);

		if ($formData instanceof TableNode) {
			$xpectedAttributes = $formData->getRowsHash();
			$actual = $this->getDataFromResponse($this->response);
			foreach ($xpectedAttributes as $attribute => $expectedValue) {
				if ($expectedValue === 'NOT_EMPTY') {
					Assert::assertNotEmpty($actual[$attribute]);
					continue;
				}
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
	 * @Then /^user "([^"]*)" adds (user|group|email|circle|federated_user|phone) "([^"]*)" to room "([^"]*)" with (\d+) \((v4)\)$/
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

		if ($newType === 'federated_user') {
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
	 * @When /^user "([^"]*)" (sets|removes|adds) permissions for all attendees in room "([^"]*)" to "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $mode
	 * @param string $identifier
	 * @param string $permissionsString
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsRemovesAddsPermissionsForAllAttendeesInRoomTo(string $user, string $method, string $identifier, string $permissionsString, int $statusCode, string $apiVersion): void {
		$permissions = $this->mapPermissionsTestInput($permissionsString);

		// Convert method from step syntax to what the API expects
		if ($method === 'sets') {
			$method = 'set';
		} elseif ($method === 'removes') {
			$method = 'remove';
		} else {
			$method = 'add';
		}

		$requestParameters = [
			['method', $method],
			['permissions', $permissions],
		];

		$this->setCurrentUser($user);
		$this->sendRequest(
			'PUT', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/attendees/permissions/all',
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
	 * @Then /^user "([^"]*)" pings (user|guest) "([^"]*)"( attendeeIdPlusOne)? to join call "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $actorType
	 * @param string $actorId
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function userPingsAttendeeInRoomTo(string $user, string $actorType, string $actorId, ?string $offset, string $identifier, int $statusCode, string $apiVersion): void {
		$this->setCurrentUser($user);

		$attendeeId = self::$userToAttendeeId[$identifier][$actorType . 's'][$actorId];
		if ($offset) {
			$attendeeId++;
		}

		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier] . '/ring/' . $attendeeId);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" dials out to "([^"]*)" from call in room "([^"]*)" with (\d+) \((v4)\)$/
	 *
	 * @param string $user
	 * @param string $phoneNumber
	 * @param string $identifier
	 * @param int $statusCode
	 * @param string $apiVersion
	 * @param TableNode|null $formData
	 */
	public function userDialsOut(string $user, string $phoneNumber, string $identifier, int $statusCode, string $apiVersion, TableNode $formData = null): void {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/call/' . self::$identifierToToken[$identifier] . '/dialout/'
			. self::$userToAttendeeId[$identifier]['phones'][self::$phoneNumberToActorId[$phoneNumber]]
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
		$message = str_replace('\n', "\n", $message);
		$message = str_replace('{$BASE_URL}', $this->baseUrl, $message);
		$message = str_replace('{$REMOTE_URL}', $this->baseRemoteUrl, $message);

		if ($message === '413 Payload Too Large') {
			$message .= "\n" . str_repeat('1', 32000);
		}

		if ($sendingMode === 'silent sends') {
			$body = new TableNode([['message', $message], ['silent', true]]);
		} else {
			$body = new TableNode([['message', $message]]);
		}

		$this->setCurrentUser($user, $identifier);
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
	 * @Then /^user "([^"]*)" edits message ("[^"]*"|'[^']*') in room "([^"]*)" to ("[^"]*"|'[^']*') with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $oldMessage
	 * @param string $identifier
	 * @param string $newMessage
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userEditsMessageToRoom(string $user, string $oldMessage, string $identifier, string $newMessage, int $statusCode, string $apiVersion = 'v1', ?TableNode $formData = null) {
		$oldMessage = substr($oldMessage, 1, -1);
		$oldMessage = str_replace('\n', "\n", $oldMessage);
		$messageId = self::$textToMessageId[$oldMessage];
		$newMessage = substr($newMessage, 1, -1);
		$newMessage = str_replace('\n', "\n", $newMessage);

		$this->setCurrentUser($user, $identifier);
		$this->sendRequest(
			'PUT',
			'/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/' . $messageId,
			new TableNode([['message', $newMessage]])
		);
		$this->assertStatusCode($this->response, $statusCode);
		sleep(1); // make sure Postgres manages the order of the messages

		if ($statusCode === 200 || $statusCode === 202) {
			self::$textToMessageId[$newMessage] = $messageId;
			self::$messageIdToText[$messageId] = $newMessage;
		} elseif ($formData instanceof TableNode) {
			Assert::assertEquals(
				$formData->getRowsHash(),
				$this->getDataFromResponse($this->response),
			);
		}
	}

	/**
	 * @Then /^user "([^"]*)" sets reminder for message ("[^"]*"|'[^']*') in room "([^"]*)" for time (\d+) with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $setOrDelete
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSetsReminder(string $user, string $message, string $identifier, int $timestamp, int $statusCode, string $apiVersion = 'v1'): void {
		$message = substr($message, 1, -1);

		$this->setCurrentUser($user);
		$this->sendRequest(
			'POST',
			'/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/' . self::$textToMessageId[$message] . '/reminder',
			new TableNode([['timestamp', $timestamp]])
		);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^user "([^"]*)" deletes reminder for message ("[^"]*"|'[^']*') in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $setOrDelete
	 * @param string $message
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userDeletesReminder(string $user, string $message, string $identifier, string $statusCode, string $apiVersion = 'v1'): void {
		$message = substr($message, 1, -1);

		$this->setCurrentUser($user);
		$this->sendRequest(
			'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '/' . self::$textToMessageId[$message] . '/reminder'
		);
		$this->assertStatusCode($this->response, $statusCode);
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

		if (str_ends_with($expected['actorId'], '@{$BASE_URL}')) {
			$expected['actorId'] = str_replace('{$BASE_URL}', rtrim($this->baseUrl, '/'), $expected['actorId']);
		}
		if (str_ends_with($expected['actorId'], '@{$REMOTE_URL}')) {
			$expected['actorId'] = str_replace('{$REMOTE_URL}', rtrim($this->baseRemoteUrl, '/'), $expected['actorId']);
		}

		if (isset($expected['details'])) {
			if (str_contains($expected['details'], '@{$BASE_URL}')) {
				$expected['details'] = str_replace('{$BASE_URL}', rtrim($this->baseUrl, '/'), $expected['details']);
			}
			if (str_contains($expected['details'], '@{$REMOTE_URL}')) {
				$expected['details'] = str_replace('{$REMOTE_URL}', rtrim($this->baseRemoteUrl, '/'), $expected['details']);
			}
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
			$widget['item_api_versions'] = json_decode($widget['item_api_versions'], true);

			Assert::assertEquals($widget, $data[$id], 'Mismatch of data for widget ' . $id);
			Assert::assertStringEndsWith($widgetIconUrl, $dataIconUrl, 'Mismatch of icon URL for widget ' . $id);
		}
	}

	/**
	 * @Then /^user "([^"]*)" sees the following entries for dashboard widgets "([^"]*)"(?: \((v1|v2)\))$/
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

		if ($apiVersion === 'v1') {
			$actualItems = $data[$widgetId];
		} else {
			$actualItems = $data[$widgetId]['items'];
		}

		$actualItems = array_values(array_filter($actualItems, static fn ($item) => $item['title'] !== 'Note to self' && $item['title'] !== 'Talk updates ✅'));

		if (empty($expectedItems)) {
			Assert::assertEmpty($actualItems);
			return;
		}

		Assert::assertCount(count($expectedItems), $actualItems, json_encode($actualItems, JSON_PRETTY_PRINT));

		foreach ($expectedItems as $key => $item) {
			$token = self::$identifierToToken[$item['link']];
			$item['link'] = $this->baseUrl . 'index.php/call/' . $token;
			$item['iconUrl'] = str_replace('{$BASE_URL}', $this->baseUrl, $item['iconUrl']);
			$item['iconUrl'] = str_replace('{token}', $token, $item['iconUrl']);

			Assert::assertMatchesRegularExpression('/\?v=\w{8}$/', $actualItems[$key]['iconUrl']);
			preg_match('/(?<version>\?v=\w{8})$/', $actualItems[$key]['iconUrl'], $matches);
			$item['iconUrl'] = str_replace('{version}', $matches['version'], $item['iconUrl']);

			Assert::assertEquals($item, $actualItems[$key], 'Wrong details for item #' . $key);
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
			$message === 'NULL' ? null : new TableNode([['lastReadMessage', self::$textToMessageId[$message]]]),
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
	 * @Then /^next message request has the following parameters set$/
	 */
	public function setChatParametersForNextRequest(TableNode $formData = null): void {
		$parameters = [];
		foreach ($formData->getRowsHash() as $key => $value) {
			if (in_array($key, ['lastCommonReadId', 'lastKnownMessageId'], true)) {
				$parameters[$key] = self::$textToMessageId[$value];
			} else {
				$parameters[$key] = $value;
			}
		}
		self::$nextChatRequestParameters = $parameters;
	}

	/**
	 * @Then /^user "([^"]*)" sees the following messages in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userSeesTheFollowingMessagesInRoom(string $user, string $identifier, int $statusCode, string $apiVersion = 'v1', TableNode $formData = null) {
		$query = ['lookIntoFuture' => 0];
		if (self::$nextChatRequestParameters !== null) {
			$query = array_merge($query, self::$nextChatRequestParameters);
			self::$nextChatRequestParameters = null;
		}

		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/chat/' . self::$identifierToToken[$identifier] . '?' . http_build_query($query));
		$this->assertStatusCode($this->response, $statusCode);

		if ($statusCode === 304) {
			return;
		}

		$this->compareDataResponse($formData);
	}

	/**
	 * @Then /^user "([^"]*)" searches for messages ?(in other rooms)? with "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $search
	 * @param string $identifier
	 * @param string $statusCode
	 * @param string $apiVersion
	 */
	public function userSearchesInRoom(string $user, string $searchProvider, string $search, string $identifier, $statusCode, string $apiVersion = 'v1', TableNode $formData = null): void {
		$searchProvider = $searchProvider === 'in other rooms' ? 'talk-message' : 'talk-message-current';

		$searchUrl = '/search/providers/' . $searchProvider . '/search?from=/call/' . self::$identifierToToken[$identifier];
		if (str_contains($search, 'conversation:ROOM(')) {
			if (preg_match('/conversation:ROOM\((?P<name>\w+)\)/', $search, $matches)) {
				if (array_key_exists($matches['name'], self::$identifierToToken)) {
					$search = trim(preg_replace('/conversation:ROOM\((\w+)\)/', '', $search));
					$searchUrl .= '&conversation=' . self::$identifierToToken[$matches['name']];
				}
			}
		}

		$searchUrl .= '&term=' . $search;

		$this->setCurrentUser($user);
		$this->sendRequest('GET', $searchUrl);
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

		$contents = $this->response->getBody()->getContents();
		$this->assertEmptyArrayIsNotAListButADictionary($formData, $contents);
		$overview = $this->getDataFromResponseBody($contents);

		if ($formData instanceof TableNode) {
			$expected = $formData->getRowsHash();
			$summarized = array_map(function ($type) {
				return (string) count($type);
			}, $overview);
			Assert::assertEquals($expected, $summarized);
		}
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
			self::$messageIdToText[$message['id']] = $message['message'];
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
		$includeLastEdit = in_array('lastEditActorId', $formData->getRow(0), true);
		$includeMessageType = in_array('messageType', $formData->getRow(0), true);

		$expected = $formData->getHash();
		$count = count($expected);
		Assert::assertCount($count, $messages, 'Message count does not match' . "\n" . print_r($messages, true));
		for ($i = 0; $i < $count; $i++) {
			if ($expected[$i]['messageParameters'] === '"IGNORE"') {
				$messages[$i]['messageParameters'] = 'IGNORE';
			}

			$result = preg_match('/POLL_ID\(([^)]+)\)/', $expected[$i]['messageParameters'], $matches);
			if ($result) {
				$expected[$i]['messageParameters'] = str_replace($matches[0], '"' . self::$questionToPollId[$matches[1]] . '"', $expected[$i]['messageParameters']);
			}
			if (isset($messages[$i]['messageParameters']['object']['icon-url'])) {
				$result = preg_match('/"\{VALIDATE_ICON_URL_PATTERN\}"/', $expected[$i]['messageParameters'], $matches);
				if ($result) {
					Assert::assertMatchesRegularExpression('/avatar(\?v=\w+)?/', $messages[$i]['messageParameters']['object']['icon-url']);
					$expected[$i]['messageParameters'] = str_replace($matches[0], json_encode($messages[$i]['messageParameters']['object']['icon-url']), $expected[$i]['messageParameters']);
				}
			}
			$expected[$i]['message'] = str_replace('\n', "\n", $expected[$i]['message']);

			if (str_ends_with($expected[$i]['actorId'], '@{$BASE_URL}')) {
				$expected[$i]['actorId'] = str_replace('{$BASE_URL}', rtrim($this->baseUrl, '/'), $expected[$i]['actorId']);
			}
			if (str_ends_with($expected[$i]['actorId'], '@{$REMOTE_URL}')) {
				$expected[$i]['actorId'] = str_replace('{$REMOTE_URL}', rtrim($this->baseRemoteUrl, '/'), $expected[$i]['actorId']);
			}

			if (str_contains($expected[$i]['messageParameters'], '{$BASE_URL}')) {
				$expected[$i]['messageParameters'] = str_replace('{$BASE_URL}', str_replace('/', '\/', rtrim($this->baseUrl, '/')), $expected[$i]['messageParameters']);
			}
			if (str_contains($expected[$i]['messageParameters'], '{$REMOTE_URL}')) {
				$expected[$i]['messageParameters'] = str_replace('{$REMOTE_URL}', str_replace('/', '\/', rtrim($this->baseRemoteUrl, '/')), $expected[$i]['messageParameters']);
			}

			if (isset($expected[$i]['lastEditActorId'])) {
				if (str_ends_with($expected[$i]['lastEditActorId'], '@{$BASE_URL}')) {
					$expected[$i]['lastEditActorId'] = str_replace('{$BASE_URL}', rtrim($this->baseUrl, '/'), $expected[$i]['lastEditActorId']);
				}
				if (str_ends_with($expected[$i]['lastEditActorId'], '@{$REMOTE_URL}')) {
					$expected[$i]['lastEditActorId'] = str_replace('{$REMOTE_URL}', rtrim($this->baseRemoteUrl, '/'), $expected[$i]['lastEditActorId']);
				}
			}

			if ($expected[$i]['actorType'] === 'bots') {
				$result = preg_match('/BOT\(([^)]+)\)/', $expected[$i]['actorId'], $matches);
				if ($result && isset(self::$botNameToHash[$matches[1]])) {
					$expected[$i]['actorId'] = 'bot-' . self::$botNameToHash[$matches[1]];
				}
			}

			// Replace the date/time line of the call summary because we can not know if we jumped a minute, hour or day on the execution.
			if (str_contains($expected[$i]['message'], '{DATE}')) {
				$messages[$i]['message'] = preg_replace(
					'/[A-Za-z]+day, [A-Za-z]+ \d+, \d+ · \d+:\d+ [AP]M – \d+:\d+ [AP]M \(UTC\)/u',
					'{DATE}',
					$messages[$i]['message']
				);
			}
		}

		Assert::assertEquals($expected, array_map(function ($message, $expected) use ($includeParents, $includeReferenceId, $includeReactions, $includeReactionsSelf, $includeLastEdit, $includeMessageType) {
			$data = [
				'room' => self::$tokenToIdentifier[$message['token']],
				'actorType' => $message['actorType'],
				'actorId' => $message['actorType'] === 'guests' ? self::$sessionIdToUser[$message['actorId']] : $message['actorId'],
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
			if ($includeMessageType) {
				$data['messageType'] = $message['messageType'];
			}
			if (isset($expected['silent'])) {
				$data['silent'] = isset($message['silent']) ? json_encode($message['silent']) : '!ISSET';
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

			if ($includeLastEdit) {
				$data['lastEditActorType'] = $message['lastEditActorType'] ?? '';
				$data['lastEditActorDisplayName'] = $message['lastEditActorDisplayName'] ?? '';
				$data['lastEditActorId'] = $message['lastEditActorId'] ?? '';
				if (($message['lastEditActorType'] ?? '') === 'guests') {
					$data['lastEditActorId'] = self::$sessionIdToUser[$message['lastEditActorId']];
				}
			}

			return $data;
		}, $messages, $expected));
	}

	/**
	 * @param TableNode|null $formData
	 */
	protected function compareSearchResponse(?TableNode $formData = null, ?string $expectedCursor = null) {
		$data = $this->getDataFromResponse($this->response);
		$results = $data['entries'];

		if ($expectedCursor !== null) {
			Assert::assertSame($expectedCursor, $data['cursor']);
		}

		if ($formData === null) {
			Assert::assertEmpty($results);
			return;
		}

		$expected = array_map(static function (array $result) {
			if (isset($result['attributes.conversation'])) {
				$result['attributes.conversation'] = self::$identifierToToken[$result['attributes.conversation']];
			}
			if (isset($result['attributes.messageId'])) {
				$result['attributes.messageId'] = self::$textToMessageId[$result['attributes.messageId']];
			}
			return $result;
		}, $formData->getHash());

		$count = count($expected);
		Assert::assertCount($count, $results, 'Result count does not match');

		Assert::assertEquals($expected, array_map(static function ($actual) {
			$compare = [
				'title' => $actual['title'],
				'subline' => $actual['subline'],
			];
			if (isset($actual['attributes']['conversation'])) {
				$compare['attributes.conversation'] = $actual['attributes']['conversation'];
			}
			if (isset($actual['attributes']['messageId'])) {
				$compare['attributes.messageId'] = $actual['attributes']['messageId'];
			}
			return $compare;
		}, $results));
	}

	/**
	 * @Then /^user "([^"]*)" searches for conversations with "([^"]*)"(?: offset "([^"]*)")? limit (\d+)(?: expected cursor "([^"]*)")?$/
	 *
	 * @param string $user
	 * @param string $search
	 * @param int $limit
	 */
	public function userSearchesRooms(string $user, string $search, string $offset, int $limit, string $expectedCursor, ?TableNode $formData = null): void {
		$searchUrl = '/search/providers/talk-conversations/search?limit=' . $limit;
		if ($offset && array_key_exists($offset, self::$identifierToToken)) {
			$searchUrl .= '&cursor=' . self::$identifierToToken[$offset];
		}

		$searchUrl .= '&term=' . $search;

		$this->setCurrentUser($user);
		$this->sendRequest('GET', $searchUrl);
		$this->assertStatusCode($this->response, 200);

		if ($expectedCursor !== null) {
			$expectedCursor = self::$identifierToToken[$expectedCursor] ?? '';
		}

		$this->compareSearchResponse($formData, $expectedCursor);
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

		$expected = array_map(static function (array $message) {
			if (isset($message['messageParameters'])) {
				$result = preg_match('/POLL_ID\(([^)]+)\)/', $message['messageParameters'], $matches);
				if ($result) {
					$message['messageParameters'] = str_replace($matches[0], '"' . self::$questionToPollId[$matches[1]] . '"', $message['messageParameters']);
				}
			}
			return $message;
		}, $formData->getHash());


		Assert::assertCount(count($expected), $messages, 'Message count does not match:' . "\n" . json_encode($messages, JSON_PRETTY_PRINT));
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
				if ($expected['messageParameters'] === '"IGNORE"') {
					$data['messageParameters'] = '"IGNORE"';
				}
			}

			if (isset($expected['silent'])) {
				$data['silent'] = isset($message['silent']) ? json_encode($message['silent']) : '!ISSET';
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

		Assert::assertCount(count($formData->getHash()), $mentions, 'Mentions count does not match' . "\n" . json_encode($mentions, JSON_PRETTY_PRINT));

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
			if ($row['mentionId'] === 'GUEST_ID') {
				Assert::assertRegExp('/^guest\/[0-9a-f]{40}$/', $mentions[$key]['mentionId']);
				$mentions[$key]['mentionId'] = 'GUEST_ID';
			}
			if (str_ends_with($row['id'], '@{$BASE_URL}')) {
				$row['id'] = str_replace('{$BASE_URL}', rtrim($this->baseUrl, '/'), $row['id']);
			}
			if (str_ends_with($row['id'], '@{$REMOTE_URL}')) {
				$row['id'] = str_replace('{$REMOTE_URL}', rtrim($this->baseRemoteUrl, '/'), $row['id']);
			}
			if (str_ends_with($row['mentionId'], '@{$BASE_URL}')) {
				$row['mentionId'] = str_replace('{$BASE_URL}', rtrim($this->baseUrl, '/'), $row['mentionId']);
			}
			if (str_ends_with($row['mentionId'], '@{$REMOTE_URL}')) {
				$row['mentionId'] = str_replace('{$REMOTE_URL}', rtrim($this->baseRemoteUrl, '/'), $row['mentionId']);
			}
			if (array_key_exists('avatar', $row)) {
				Assert::assertRegExp('/' . self::$identifierToToken[$row['avatar']] . '\/avatar/', $mentions[$key]['avatar']);
				unset($row['avatar']);
			}
			unset($mentions[$key]['avatar']);
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

		$mentions = array_map(static function (array $mention): array {
			unset($mention['icon']);
			unset($mention['status']);
			unset($mention['subline']);
			unset($mention['shareWithDisplayNameUnique']);
			return $mention;
		}, $this->getDataFromResponse($this->response));

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

		$expected = array_map(function (array $mention): array {
			$result = preg_match('/TEAM_ID\(([^)]+)\)/', $mention['id'], $matches);
			if ($result) {
				$mention['id'] = self::$createdTeams[$this->currentServer][$matches[1]];
			}
			return $mention;
		}, $formData->getHash());

		usort($expected, function ($a, $b) {
			if ($a['source'] === $b['source']) {
				return $a['label'] <=> $b['label'];
			}
			return $a['source'] <=> $b['source'];
		});

		Assert::assertEquals($expected, $mentions);
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
	 * @Then /^last response has federation invites header set to "([^"]*)"$/
	 *
	 * @param string $count
	 */
	public function hasFederationInvitesHeader(string $count): void {
		if ($count === 'NULL') {
			Assert::assertFalse($this->response->hasHeader('X-Nextcloud-Talk-Federation-Invites'), "Should not contain 'X-Nextcloud-Talk-Federation-Invites' header\n" . json_encode($this->response->getHeaders(), JSON_PRETTY_PRINT));
		} else {
			Assert::assertTrue($this->response->hasHeader('X-Nextcloud-Talk-Federation-Invites'), "Should contain 'X-Nextcloud-Talk-Federation-Invites' header\n" . json_encode($this->response->getHeaders(), JSON_PRETTY_PRINT));
			Assert::assertEquals($count, $this->response->getHeader('X-Nextcloud-Talk-Federation-Invites')[0]);
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
	 * @Then /^user "([^"]*)" removes breakout rooms from "([^"]*)" with (\d+) \((v1)\)$/
	 *
	 * @param string $user
	 * @param string $identifier
	 * @param int $status
	 * @param string $apiVersion
	 */
	public function userRemovesBreakoutRooms(string $user, string $identifier, int $status, string $apiVersion): void {
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/spreed/api/' . $apiVersion . '/breakout-rooms/' . self::$identifierToToken[$identifier]);
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
	 * Parses the JSON answer to get the array of users returned.
	 * @param ResponseInterface $response
	 * @return array
	 */
	protected function getDataFromResponse(ResponseInterface $response) {
		return $this->getDataFromResponseBody($response->getBody()->getContents());
	}

	/**
	 * Parses the JSON answer to get the array of users returned.
	 * @param string $response
	 * @return array
	 */
	protected function getDataFromResponseBody(string $response) {
		$jsonBody = json_decode($response, true);
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
		$currentUser = $this->setCurrentUser('admin');
		foreach ($formData->getRows() as $row) {
			$this->sendRequest('POST', '/apps/provisioning_api/api/v1/config/apps/' . $appId . '/' . $row[0], [
				'value' => $row[1],
			]);
			$this->changedConfigs[$appId][] = $row[0];
		}
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Given /^OCM provider (does not have|has) the following resource types$/
	 *
	 * @param TableNode $formData
	 */
	public function checkOCMProviderResourceTypes(string $shouldFind, TableNode $formData): void {
		$this->sendFrontpageRequest('GET', '/ocm-provider');
		$data = json_decode($this->response->getBody()->getContents(), true);
		$expectedTypes = $formData->getHash();
		$expectedFound = $shouldFind === 'has';

		foreach ($expectedTypes as $expected) {
			$found = false;
			foreach ($data['resourceTypes'] as $type) {
				if ($type['name'] === $expected['name']) {
					$found = true;
					Assert::assertEquals(
						json_decode($expected['shareTypes'], true),
						$type['shareTypes'],
					);
					Assert::assertEquals(
						json_decode($expected['protocols'], true),
						$type['protocols'],
					);
				}
			}
			Assert::assertEquals($expectedFound, $found);
		}
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
			self::$lastNotifications = [];
			Assert::assertCount(0, $data, json_encode($data, JSON_PRETTY_PRINT));
			return;
		}

		$this->assertNotifications($data, $body);
		self::$lastNotifications = $data;
	}

	private function assertNotifications($notifications, TableNode $formData) {
		Assert::assertCount(count($formData->getHash()), $notifications, 'Notifications count does not match:' . "\n" . json_encode($notifications, JSON_PRETTY_PRINT));
		Assert::assertEquals($formData->getHash(), array_map(function ($notification, $expectedNotification) {
			$data = [];
			if (isset($expectedNotification['object_id'])) {
				if (strpos($notification['object_id'], '/') !== false) {
					[$roomToken, $message] = explode('/', $notification['object_id']);
					$messageText = self::$messageIdToText[$message] ?? 'UNKNOWN_MESSAGE';

					$messageText = str_replace($this->baseUrl, '{$BASE_URL}', $messageText);
					$messageText = str_replace($this->baseRemoteUrl, '{$REMOTE_URL}', $messageText);

					$data['object_id'] = self::$tokenToIdentifier[$roomToken] . '/' . $messageText;
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
				$result = preg_match('/ROOM\(([^)]+)\)/', $expectedNotification['message'], $matches);
				if ($result && isset(self::$identifierToToken[$matches[1]])) {
					$data['message'] = str_replace(self::$identifierToToken[$matches[1]], $matches[0], $data['message']);
				}
			}
			if (isset($expectedNotification['object_type'])) {
				$data['object_type'] = (string) $notification['object_type'];
			}
			if (isset($expectedNotification['app'])) {
				$data['app'] = (string) $notification['app'];
			}

			return $data;
		}, $notifications, $formData->getHash()), json_encode($notifications, JSON_PRETTY_PRINT));
	}

	/**
	 * @Given /^guest accounts can be created$/
	 *
	 * @param TableNode $formData
	 */
	public function allowGuestAccountsCreation(): void {
		$currentUser = $this->setCurrentUser('admin');

		// save old state and restore at the end
		$this->sendRequest('GET', '/cloud/apps?filter=enabled');
		$this->assertStatusCode($this->response, 200);
		$data = $this->getDataFromResponse($this->response);
		$this->guestsAppWasEnabled = in_array('guests', $data['apps'], true);

		if (!$this->guestsAppWasEnabled) {
			// enable Guests app
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
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/apps/spreedcheats/');
		foreach ($this->changedConfigs as $appId => $configs) {
			foreach ($configs as $config) {
				$this->sendRequest('DELETE', '/apps/provisioning_api/api/v1/config/apps/' . $appId . '/' . $config);
			}
		}

		$this->setCurrentUser($currentUser);
		if ($this->changedBruteforceSetting) {
			$this->enableDisableBruteForceProtection('disable');
		}
	}

	/**
	 * @AfterScenario
	 */
	public function resetGuestsAppState() {
		if ($this->guestsAppWasEnabled === null) {
			// Guests app was not touched
			return;
		}

		$currentUser = $this->setCurrentUser('admin');

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
	 */
	public function setCurrentUser(?string $user, ?string $identifier = null): ?string {
		$oldUser = $this->currentUser;
		$this->currentUser = $user;
		return $oldUser;
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
	 * @Given /^(enable|disable) brute force protection$/
	 */
	public function enableDisableBruteForceProtection(string $enable): void {
		if ($enable === 'enable') {
			$this->changedBruteforceSetting = true;
		} else {
			// Reset the attempts before disabling
			$this->runOcc(['security:bruteforce:reset', '127.0.0.1']);
			$this->theCommandWasSuccessful();
			$this->runOcc(['security:bruteforce:reset', '::1']);
			$this->theCommandWasSuccessful();
		}

		// config:system:get auth.bruteforce.protection.enabled
		$this->runOcc(['config:system:set', 'auth.bruteforce.protection.enabled', '--type=boolean', '--value=' . ($enable === 'enable' ? 'true' : 'false')]);
		$this->theCommandWasSuccessful();

		// config:system:get auth.bruteforce.protection.testing
		if ($enable === 'enable') {
			$this->runOcc(['config:system:set', 'auth.bruteforce.protection.testing', '--type=boolean', '--value=' . 'true']);
		} else {
			$this->runOcc(['config:system:delete', 'auth.bruteforce.protection.testing']);
		}
		$this->theCommandWasSuccessful();

		if ($enable === 'enable') {
			// Reset the attempts after enabling
			$this->runOcc(['security:bruteforce:reset', '127.0.0.1']);
			$this->theCommandWasSuccessful();
			$this->runOcc(['security:bruteforce:reset', '::1']);
			$this->theCommandWasSuccessful();
		} else {
			$this->changedBruteforceSetting = false;
		}
	}

	/**
	 * @Given /^the following brute force attempts are registered$/
	 */
	public function assertBruteforceAttempts(TableNode $tableNode = null): void {
		$totalCount = 0;
		if ($tableNode instanceof TableNode) {
			foreach ($tableNode->getRowsHash() as $action => $attempts) {
				$this->runOcc(['security:bruteforce:attempts', '127.0.0.1', $action, '--output=json']);
				$this->theCommandWasSuccessful();
				$info = json_decode($this->getLastStdOut(), true);
				$totalCount += $info['attempts'];
				$ipv4Attempts = $info['attempts'];

				$this->runOcc(['security:bruteforce:attempts', '::1', $action, '--output=json']);
				$this->theCommandWasSuccessful();
				$info = json_decode($this->getLastStdOut(), true);
				$totalCount += $info['attempts'];
				$ipv6Attempts = $info['attempts'];

				Assert::assertEquals($attempts, $ipv4Attempts + $ipv6Attempts);
			}
		}

		$this->runOcc(['security:bruteforce:attempts', '127.0.0.1', '--output=json']);
		$this->theCommandWasSuccessful();
		$info = json_decode($this->getLastStdOut(), true);
		$ipv4Attempts = $info['attempts'];

		$this->runOcc(['security:bruteforce:attempts', '::1', '--output=json']);
		$this->theCommandWasSuccessful();
		$info = json_decode($this->getLastStdOut(), true);
		$ipv6Attempts = $info['attempts'];

		Assert::assertEquals($totalCount, $ipv4Attempts + $ipv6Attempts, 'IP has bruteforce attempts for other actions registered');
	}

	/**
	 * @Given /^user "([^"]*)" is a guest account user/
	 * @param string $email email address
	 */
	public function createGuestUser($email) {
		$currentUser = $this->setCurrentUser('admin');
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

		$this->createdGuestAccountUsers[$email] = $email;
		$this->setCurrentUser($currentUser);
	}

	private function userExists($user) {
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('GET', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);
		return $this->response;
	}

	private function createUser($user) {
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('POST', '/cloud/users', [
			'userid' => $user,
			'password' => self::TEST_PASSWORD,
		]);
		$this->assertStatusCode($this->response, 200, 'Failed to create user');

		//Quick hack to login once with the current user
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/cloud/users' . '/' . $user);
		$this->assertStatusCode($this->response, 200, 'Failed to do first login');

		$this->createdUsers[$user] = $user;

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
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);

		unset($this->createdUsers[$user]);

		return $this->response;
	}

	private function deleteGuestUser($user) {
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/cloud/users/' . $user);
		$this->setCurrentUser($currentUser);

		unset($this->createdGuestAccountUsers[$user]);

		return $this->response;
	}

	private function setUserDisplayName($user) {
		$currentUser = $this->setCurrentUser('admin');
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
		$currentUser = $this->setCurrentUser('admin');
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

		$this->createdGroups[$group] = $group;
	}

	/**
	 * @Given /^set display name of group "([^"]*)" to "([^"]*)"$/
	 * @param string $groupId
	 * @param string $displayName
	 */
	public function renameGroup(string $groupId, string $displayName): void {
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('PUT', '/cloud/groups/' . urlencode($groupId), [
			'key' => 'displayname',
			'value' => $displayName,
		]);

		$this->assertStatusCode($this->response, 200);
		$this->setCurrentUser($currentUser);
	}

	private function deleteGroup($group) {
		$currentUser = $this->setCurrentUser('admin');
		$this->sendRequest('DELETE', '/cloud/groups/' . $group);
		$this->setCurrentUser($currentUser);

		unset($this->createdGroups[$group]);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * @When /^user "([^"]*)" is member of group "([^"]*)"$/
	 * @param string $user
	 * @param string $group
	 */
	public function addingUserToGroup($user, $group) {
		$currentUser = $this->setCurrentUser('admin');
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
		$currentUser = $this->setCurrentUser('admin');
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
		if ($statusCode === 200 || $statusCode === 201) {
			$this->assertReactionList($formData);
		}
	}

	/**
	 * @Given /^user "([^"]*)" retrieve reactions "([^"]*)" of message "([^"]*)" in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userRetrieveReactionsOfMessageInRoomWith(string $user, string $reaction, string $message, string $identifier, int $statusCode, string $apiVersion = 'v1', ?TableNode $formData = null): void {
		$message = str_replace('\n', "\n", $message);
		$token = self::$identifierToToken[$identifier];
		$messageId = self::$textToMessageId[$message];
		$this->setCurrentUser($user);
		$reaction = $reaction !== 'all' ? '?reaction=' . $reaction : '';
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/reaction/' . $token . '/' . $messageId . $reaction);
		$this->assertStatusCode($this->response, $statusCode);
		$this->assertReactionList($formData);
	}

	private function assertReactionList(?TableNode $formData): void {
		$contents = $this->response->getBody()->getContents();
		$this->assertEmptyArrayIsNotAListButADictionary($formData, $contents);
		$reactions = $this->getDataFromResponseBody($contents);

		$expected = [];
		if (!$formData instanceof TableNode) {
			return;
		}

		foreach ($formData->getHash() as $row) {
			$reaction = $row['reaction'];
			unset($row['reaction']);

			if ($row['actorType'] === 'bots') {
				$result = preg_match('/BOT\(([^)]+)\)/', $row['actorId'], $matches);
				if ($result && isset(self::$botNameToHash[$matches[1]])) {
					$row['actorId'] = 'bot-' . self::$botNameToHash[$matches[1]];
				}
			}

			$expected[$reaction][] = $row;
		}

		$actual = array_map(function ($reaction, $list) use ($expected): array {
			$list = array_map(function ($reaction) {
				unset($reaction['timestamp']);
				$reaction['actorId'] = ($reaction['actorType'] === 'guests') ? self::$sessionIdToUser[$reaction['actorId']] : (string) $reaction['actorId'];
				if ($reaction['actorType'] === 'federated_users') {
					$reaction['actorId'] = str_replace(rtrim($this->baseUrl, '/'), '{$BASE_URL}', $reaction['actorId']);
					$reaction['actorId'] = str_replace(rtrim($this->baseRemoteUrl, '/'), '{$REMOTE_URL}', $reaction['actorId']);
				}
				return $reaction;
			}, $list);
			Assert::assertArrayHasKey($reaction, $expected, 'Not expected reaction: ' . $reaction);
			Assert::assertCount(count($list), $expected[$reaction], 'Reaction count by type does not match');

			usort($expected[$reaction], [self::class, 'sortAttendees']);
			usort($list, [self::class, 'sortAttendees']);
			Assert::assertEquals($expected[$reaction], $list, 'Reaction list by type does not match');
			return $list;
		}, array_keys($reactions), array_values($reactions));
		Assert::assertCount(count($expected), $actual, 'Reaction count does not match');
	}

	/**
	 * @Given /^user "([^"]*)" set the message expiration to ([-\d]+) of room "([^"]*)" with (\d+) \((v4)\)$/
	 */
	public function userSetTheMessageExpirationToXWithStatusCode(string $user, int $messageExpiration, string $identifier, int $statusCode, string $apiVersion = 'v4'): void {
		$this->setCurrentUser($user);
		$this->sendRequest('POST', '/apps/spreed/api/' .  $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/message-expiration', [
			'seconds' => $messageExpiration,
		]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Given /^user "([^"]*)" sets the recording consent to (\d+) for room "([^"]*)" with (\d+) \((v4)\)$/
	 */
	public function userSetsTheRecordingConsentToXWithStatusCode(string $user, int $recordingConsent, string $identifier, int $statusCode, string $apiVersion = 'v4'): void {
		$this->setCurrentUser($user);
		$this->sendRequest('PUT', '/apps/spreed/api/' .  $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/recording-consent', [
			'recordingConsent' => $recordingConsent,
		]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Given /^aging messages (\d+) hours in room "([^"]*)"$/
	 */
	public function occAgeChatMessages(int $hours, string $identifier): void {
		$this->runOcc(['talk:developer:age-chat-messages', '--hours', $hours, self::$identifierToToken[$identifier]]);
		$this->theCommandWasSuccessful();
	}

	/**
	 * @Given /^the following recording consent is recorded for (room|user) "([^"]*)"$/
	 */
	public function occRecordingConsentLists(string $filterType, string $identifier, TableNode $tableNode): void {
		if ($filterType === 'room') {
			$filter = ' --token ' . self::$identifierToToken[$identifier];
		} else {
			$filter = ' --actor-type users --actor-id ' . $identifier;
		}
		$this->invokingTheCommand('talk:recording:consent --output json' . $filter);
		$this->theCommandWasSuccessful();
		$json = $this->getLastStdOut();

		// Replace identifiers with token
		$expected = array_map(static function (array $data): array {
			$data['token'] = self::$identifierToToken[$data['token']];
			return $data;
		}, $tableNode->getHash());

		// Remove timestamp from output
		$actual = array_map(static function (array $data): array {
			Assert::assertIsInt($data['timestamp'], 'Timestamp of recording consent was not an integer');
			unset($data['timestamp']);
			return $data;
		}, json_decode($json, true, 512, JSON_THROW_ON_ERROR));

		Assert::assertEquals($expected, $actual);
	}

	/**
	 * @When /^wait for ([0-9]+) (second|seconds)$/
	 */
	public function waitForXSecond($seconds): void {
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
	 * @When /^user "([^"]*)" sets emoji "([^"]*)" with color "([^"]*)" as avatar of room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function userSetsEmojiAsAvatarOfRoom(string $user, string $emoji, string $color, string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->setCurrentUser($user);
		$options = [
			'emoji' => $emoji,
			'color' => $color,
		];

		if ($color === 'null') {
			unset($options['color']);
		}
		$this->sendRequest('POST', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/avatar/emoji', $options);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^the room "([^"]*)" has an avatar with (\d+)(?: \((v1)\))?$/
	 */
	public function theRoomHasAnAvatarWithStatusCode(string $identifier, int $statusCode, string $apiVersion = 'v1', bool $darkTheme = false): void {
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/avatar' . ($darkTheme ? '/dark' : ''));
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @When /^the room "([^"]*)" has an svg as (dark avatar|avatar) with (\d+)(?: \((v1)\))?$/
	 */
	public function theRoomHasASvgAvatarWithStatusCode(string $identifier, string $darkOrBright, int $statusCode, string $apiVersion = 'v1'): void {
		$darkTheme = $darkOrBright === 'dark avatar';
		$this->theRoomHasNoSvgAvatarWithStatusCode($identifier, $statusCode, $apiVersion, true, $darkTheme);
	}

	/**
	 * @When /^the room "([^"]*)" has not an svg as avatar with (\d+)(?: \((v1)\))?$/
	 */
	public function theRoomHasNoSvgAvatarWithStatusCode(string $identifier, int $statusCode, string $apiVersion = 'v1', bool $expectedToBeSvg = false, bool $darkTheme = false): void {
		$this->theRoomHasAnAvatarWithStatusCode($identifier, $statusCode, $apiVersion, $darkTheme);
		$content = $this->response->getBody()->getContents();
		try {
			simplexml_load_string($content);
			$actualIsSvg = true;
		} catch (\Throwable $th) {
			$actualIsSvg = false;
		}
		if ($expectedToBeSvg) {
			Assert::assertEquals($expectedToBeSvg, $actualIsSvg, 'The room avatar needs to be a XML file');
		} else {
			Assert::assertEquals($expectedToBeSvg, $actualIsSvg, 'The room avatar can not be a XML file');
		}
	}

	/**
	 * @When /^the (dark avatar|avatar) svg of room "([^"]*)" (not contains|contains) the string "([^"]*)"(?: \((v1)\))?$/
	 */
	public function theAvatarSvgOfRoomContainsTheString(string $darkOrBright, string $identifier, string $contains, string $string, string $apiVersion = 'v1'): void {
		$darkTheme = $darkOrBright === 'dark avatar';
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room/' . self::$identifierToToken[$identifier] . '/avatar' . ($darkTheme ? '/dark' : ''));
		$content = $this->response->getBody()->getContents();

		try {
			simplexml_load_string($content);
		} catch (\Throwable $th) {
			throw new Exception('The avatar needs to be a XML');
		}

		if ($contains === 'contains') {
			Assert::assertStringContainsString($string, $content);
		} else {
			Assert::assertStringNotContainsString($string, $content);
		}
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
		$recordingServerSharedSecret = 'the secret';
		$this->setAppConfig('spreed', new TableNode([['recording_servers', json_encode(['secret' => $recordingServerSharedSecret])]]));
		$validRandom = md5((string) rand());
		$validChecksum = hash_hmac('sha256', $validRandom . self::$identifierToToken[$identifier], $recordingServerSharedSecret);
		$headers = [
			'TALK_RECORDING_RANDOM' => $validRandom,
			'TALK_RECORDING_CHECKSUM' => $validChecksum,
		];

		$options = ['multipart' => []];
		if ($user !== 'NULL') {
			// When exceeding post_max_size, the owner parameter is not sent:
			// RecordingController::store(): Argument #1 ($owner) must be of type string, null given
			$options['multipart'][] = ['name' => 'owner', 'contents' => $user];
		}

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
		sleep(1); // make sure Postgres manages the order of the messages
	}

	/**
	 * @Then /^read bot ids from OCC$/
	 */
	public function readBotIds(): void {
		$this->invokingTheCommand('talk:bot:list -v --output json');
		$this->theCommandWasSuccessful();
		$json = $this->getLastStdOut();

		$botData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		foreach ($botData as $bot) {
			self::$botNameToId[$bot['name']] = $bot['id'];
			self::$botNameToHash[$bot['name']] = $bot['url_hash'];
			self::$botIdToName[$bot['id']] = $bot['name'];
		}
	}

	/**
	 * @Then /^(setup|remove) bot "([^"]*)" for room "([^"]*)" via OCC$/
	 */
	public function setupOrRemoveBotInRoom(string $action, string $botName, string $identifier): void {
		$this->invokingTheCommand('talk:bot:' . $action . ' ' . self::$botNameToId[$botName] . ' ' . self::$identifierToToken[$identifier]);
		$this->theCommandWasSuccessful();
	}

	/**
	 * @Then /^set state (enabled|disabled|no-setup) for bot "([^"]*)" via OCC$/
	 */
	public function stateUpdateForBot(string $state, string $botName, ?TableNode $body = null): void {
		if ($state === 'enabled') {
			$state = 1;
		} elseif ($state === 'disabled') {
			$state = 0;
		} elseif ($state === 'no-setup') {
			$state = 2;
		}

		$features = '';
		if ($body) {
			$features = array_map(static fn ($map) => $map['feature'], $body->getColumnsHash());
			$features = ' -f ' . implode(' -f ', $features);
		}

		$this->invokingTheCommand('talk:bot:state ' . self::$botNameToId[$botName] . ' ' . $state . $features);
		$this->theCommandWasSuccessful();
	}

	/**
	 * @Then /^Bot "([^"]*)" (sends|removes) a (message|reaction) for room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function botSendsRequest(string $botName, string $sends, string $action, string $identifier, int $status, string $apiVersion, TableNode $body): void {
		$currentUser = $this->setCurrentUser(null);

		$data = $body->getRowsHash();
		$secret = $data['secret'];
		unset($data['secret']);


		if ($action === 'message') {
			$url = '/message';
			$toSign = $data['message'];

			if (isset($data['replyTo'])) {
				$data['replyTo'] = self::$textToMessageId[$data['replyTo']];
			}
		} else {
			$url = '/reaction/' . self::$textToMessageId[$data['messageId']];
			unset($data['messageId']);
			$toSign = $data['reaction'];
		}

		$random = bin2hex(random_bytes(32));
		$hash = hash_hmac('sha256', $random . $toSign, $secret);
		$headers = [
			'X-Nextcloud-Talk-Bot-Random' => $random,
			'X-Nextcloud-Talk-Bot-Signature' => $hash,
		];


		$this->sendRequest(
			$sends === 'sends' ? 'POST' : 'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/bot/' . self::$identifierToToken[$identifier] . $url,
			$data,
			$headers
		);
		$this->assertStatusCode($this->response, $status);

		$this->setCurrentUser($currentUser);
	}

	/**
	 * @Then /^user "([^"]*)" (sets up|removes) bot "([^"]*)" for room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function setupOrRemoveBotViaOCSAPI(string $user, string $action, string $botName, string $identifier, int $status, string $apiVersion): void {
		$this->setCurrentUser($user);

		$this->sendRequest(
			$action === 'sets up' ? 'POST' : 'DELETE',
			'/apps/spreed/api/' . $apiVersion . '/bot/' . self::$identifierToToken[$identifier] . '/' .self::$botNameToId[$botName]
		);
		$this->assertStatusCode($this->response, $status);
	}

	/**
	 * @Then /^user "([^"]*)" shares file from the (first|last) notification to room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 *
	 * @param string $user
	 * @param string $firstLast
	 * @param string $identifier
	 * @param int $status
	 * @param string $apiVersion
	 */
	public function userShareLastNotificationFile(string $user, string $firstLast, string $identifier, int $status, string $apiVersion): void {
		$this->setCurrentUser($user);

		if (empty(self::$lastNotifications)) {
			throw new \RuntimeException('No notification data loaded, call userNotifications() before');
		}

		if ($firstLast === 'last') {
			$lastNotification = end(self::$lastNotifications);
		} else {
			$lastNotification = reset(self::$lastNotifications);
		}

		$data = [
			'fileId' => $lastNotification['messageRichParameters']['file']['id'],
			'timestamp' => (new \DateTime($lastNotification['datetime']))->getTimestamp(),
		];

		$this->sendRequest(
			'POST',
			'/apps/spreed/api/' . $apiVersion . '/recording/' . self::$identifierToToken[$identifier] . '/share-chat',
			$data
		);
		$this->assertStatusCode($this->response, $status);
	}

	/**
	 * @When /^(force run|run) "([^"]*)" background jobs$/
	 */
	public function runReminderBackgroundJobs(string $useForce, string $class): void {
		$this->runOcc(['background-job:list', '--output=json_pretty', '--class=' . $class]);
		$list = json_decode($this->lastStdOut, true, 512, JSON_THROW_ON_ERROR);

		Assert::assertNotEmpty($list, 'List of ' . $class . ' should not be empty');

		foreach ($list as $job) {
			if ($useForce === 'force run') {
				$this->runOcc(['background-job:execute', (string) $job['id'], '--force-execute']);
			} else {
				$this->runOcc(['background-job:execute', (string) $job['id']]);
			}

			if ($this->lastStdErr) {
				throw new \RuntimeException($this->lastStdErr);
			}
		}
	}

	/**
	 * @When /^user "([^"]*)" sets? status to "([^"]*)" with (\d+)(?: \((v1)\))?$/
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
	 * @Then /^client "([^"]*)" requests room list with (\d+) \((v4)\)$/
	 *
	 * @param string $userAgent
	 * @param int $statusCode
	 * @param string $apiVersion
	 */
	public function getRoomListWithSpecificUserAgent(string $userAgent, int $statusCode, string $apiVersion): void {
		$this->sendRequest('GET', '/apps/spreed/api/' . $apiVersion . '/room', null, [
			'USER_AGENT' => $userAgent,
		]);
		$this->assertStatusCode($this->response, $statusCode);
	}

	protected function assertEmptyArrayIsNotAListButADictionary(?TableNode $formData, string $content) {
		if (!$formData instanceof TableNode || empty($formData->getHash())) {
			$data = json_decode($content);
			Assert::assertIsNotArray($data->ocs->data, 'Response ocs.data should be an "object" to represent a JSON dictionary, not a list-array');
		}
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
	 * @When /^last response body (contains|does not contain|starts with|starts not with|ends with|ends not with) "([^"]*)"(| with newlines)$/
	 * @param string $needle
	 */
	public function lastResponseBodyContains(string $comparison, string $needle, string $replaceNWithNewlines) {
		if ($replaceNWithNewlines) {
			$needle = str_replace('\n', "\n", $needle);
		}

		if ($comparison === 'contains') {
			Assert::assertStringContainsString($needle, $this->response->getBody()->getContents());
		} elseif ($comparison === 'does not contain') {
			Assert::assertStringNotContainsString($needle, $this->response->getBody()->getContents());
		} elseif ($comparison === 'starts with') {
			Assert::assertStringStartsWith($needle, $this->response->getBody()->getContents());
		} elseif ($comparison === 'starts not with') {
			Assert::assertStringStartsNotWith($needle, $this->response->getBody()->getContents());
		} elseif ($comparison === 'ends with') {
			Assert::assertStringEndsWith($needle, $this->response->getBody()->getContents());
		} elseif ($comparison === 'ends not with') {
			Assert::assertStringEndsNotWith($needle, $this->response->getBody()->getContents());
		}
	}

	/**
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|array|null $body
	 * @param array $headers
	 */
	public function sendFrontpageRequest($verb, $url, $body = null, array $headers = [], array $options = []) {
		$fullUrl = $this->baseUrl . 'index.php' . $url;
		$this->sendRequestFullUrl($verb, $fullUrl, $body, $headers, $options);
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
	 * @When /^sending "([^"]*)" to "([^"]*)" for xml with$/
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|array|null $body
	 * @param array $headers
	 */
	public function sendXMLRequest($verb, $url, $body = null, array $headers = [], array $options = []) {
		$fullUrl = $this->baseUrl . 'ocs/v2.php' . $url;

		$headers = array_merge([
			'Accept' => 'application/xml',
		], $headers);

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
	 * @param TableNode|array|string|null $body
	 * @param array $headers
	 */
	public function sendRequestFullUrl($verb, $fullUrl, $body = null, array $headers = [], array $options = []) {
		$client = new Client();
		$options = array_merge($options, ['cookies' => $this->getUserCookieJar($this->currentUser)]);
		if ($this->currentUser === 'admin') {
			$options['auth'] = ['admin', 'admin'];
		} elseif ($this->currentUser !== null && !str_starts_with($this->currentUser, 'guest')) {
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

		$options['headers'] = array_merge([
			'OCS-ApiRequest' => 'true',
			'Accept' => 'application/json',
		], $headers);

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
