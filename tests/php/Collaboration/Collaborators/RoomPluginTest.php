<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Collaboration\Collaborators;

use OCA\Talk\Collaboration\Collaborators\RoomPlugin;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RoomPluginTest extends TestCase {
	protected ParticipantService&MockObject $participantService;
	protected ?Manager $manager = null;
	protected ?IUserSession $userSession = null;
	protected ?IUser $user = null;
	protected ?ISearchResult $searchResult = null;
	protected ?RoomPlugin $plugin = null;

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('user0');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->searchResult = $this->createMock(ISearchResult::class);

		$this->plugin = new RoomPlugin(
			$this->manager,
			$this->participantService,
			$this->userSession
		);
	}

	private function newRoom(int $type, string $token, string $name, int $permissions = Attendee::PERMISSIONS_MAX_DEFAULT): Room {
		$room = $this->createMock(Room::class);
		$participant = $this->createMock(Participant::class);

		$room->expects($this->any())
			->method('getType')
			->willReturn($type);

		$room->expects($this->any())
			->method('getToken')
			->willReturn($token);

		$room->expects($this->any())
			->method('getDisplayName')
			->willReturn($name);

		$this->participantService->expects($this->any())
			->method('getParticipant')
			->willReturn($participant);

		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn($permissions);

		return $room;
	}

	private static function newResult(string $label, string $shareWith): array {
		return [
			'label' => $label,
			'value' => [
				'shareType' => IShare::TYPE_ROOM,
				'shareWith' => $shareWith
			]
		];
	}

	public static function dataSearch(): array {
		return [
			// Empty search term with no rooms
			['', 2, 0, [], [], [], false],

			// Empty search term with rooms
			['', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name'],
			], [], [], false],

			// Search term with no matches
			['Unmatched search term', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Unmatched name'],
			], [], [], false],

			// Search term with single wide match
			['room', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken2', 'Unmatched name'],
			], [], [
				self::newResult('Room name', 'roomToken'),
			], false],

			// Chats without chat permission are not returned
			['room', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name', Attendee::PERMISSIONS_MAX_DEFAULT ^ Attendee::PERMISSIONS_CHAT],
			], [], [], false],

			// Search term with single exact match
			['room name', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Unmatched name'],
				[Room::TYPE_GROUP, 'roomToken2', 'Room name'],
			], [
				self::newResult('Room name', 'roomToken2'),
			], [], false],

			// Search term with single exact match and single wide match
			['room name', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name that also matches'],
				[Room::TYPE_GROUP, 'roomToken2', 'Room name'],
			], [
				self::newResult('Room name', 'roomToken2'),
			], [
				self::newResult('Room name that also matches', 'roomToken'),
			], false],

			// Search term matching one-to-one rooms (not possible in practice
			// as one-to-one rooms do not have a name, but it would be if they
			// had, so it is included here for completeness).
			['room name', 2, 0, [
				[Room::TYPE_ONE_TO_ONE, 'roomToken', 'Room name that also matches'],
				[Room::TYPE_ONE_TO_ONE, 'roomToken2', 'Room name'],
			], [
				self::newResult('Room name', 'roomToken2'),
			], [
				self::newResult('Room name that also matches', 'roomToken'),
			], false],

			// Search term matching public rooms
			['room name', 2, 0, [
				[Room::TYPE_PUBLIC, 'roomToken', 'Room name that also matches'],
				[Room::TYPE_PUBLIC, 'roomToken2', 'Room name'],
			], [
				self::newResult('Room name', 'roomToken2'),
			], [
				self::newResult('Room name that also matches', 'roomToken'),
			], false],

			// Search term with several wide matches
			['room', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken2', 'Another room name'],
				[Room::TYPE_GROUP, 'roomToken3', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken4', 'Another room name'],
			], [], [
				self::newResult('Room name', 'roomToken'),
				self::newResult('Another room name', 'roomToken2'),
				self::newResult('Room name', 'roomToken3'),
				self::newResult('Another room name', 'roomToken4'),
			], false],

			// Search term with several exact matches
			['room name', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken2', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken3', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken4', 'Room name'],
			], [
				self::newResult('Room name', 'roomToken'),
				self::newResult('Room name', 'roomToken2'),
				self::newResult('Room name', 'roomToken3'),
				self::newResult('Room name', 'roomToken4'),
			], [], false],

			// Search term with several matches
			['room name', 2, 0, [
				[Room::TYPE_GROUP, 'roomToken', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken2', 'Unmatched name'],
				[Room::TYPE_GROUP, 'roomToken3', 'Another room name'],
				[Room::TYPE_GROUP, 'roomToken4', 'Room name'],
				[Room::TYPE_ONE_TO_ONE, 'roomToken5', 'Room name'],
				[Room::TYPE_PUBLIC, 'roomToken6', 'Room name'],
				[Room::TYPE_GROUP, 'roomToken7', 'Another unmatched name'],
				[Room::TYPE_ONE_TO_ONE, 'roomToken8', 'Another unmatched name'],
				[Room::TYPE_PUBLIC, 'roomToken9', 'Another unmatched name'],
				[Room::TYPE_ONE_TO_ONE, 'roomToken10', 'Another room name'],
				[Room::TYPE_PUBLIC, 'roomToken11', 'Another room name'],
			], [
				self::newResult('Room name', 'roomToken'),
				self::newResult('Room name', 'roomToken4'),
				self::newResult('Room name', 'roomToken5'),
				self::newResult('Room name', 'roomToken6'),
			], [
				self::newResult('Another room name', 'roomToken3'),
				self::newResult('Another room name', 'roomToken10'),
				self::newResult('Another room name', 'roomToken11'),
			], false],
		];
	}

	#[DataProvider('dataSearch')]
	public function testSearch(
		string $searchTerm,
		int $limit,
		int $offset,
		array $roomsForParticipant,
		array $expectedMatchesExact,
		array $expectedMatches,
		bool $expectedHasMoreResults,
	) {
		$rooms = [];
		foreach ($roomsForParticipant as $roomData) {
			$rooms[] = call_user_func_array([$this, 'newRoom'], $roomData);
		}

		$this->manager->expects($this->any())
			->method('getRoomsForUser')
			->with('user0')
			->willReturn($rooms);

		$this->searchResult->expects($this->any())
			->method('addResultSet')
			->with(
				$this->callback(
					function (SearchResultType $searchResultType) {
						return $searchResultType->getLabel() === 'rooms';
					}
				),
				$expectedMatches,
				$expectedMatchesExact
			);

		$hasMoreResults = $this->plugin->search($searchTerm, $limit, $offset, $this->searchResult);

		$this->assertSame($expectedHasMoreResults, $hasMoreResults);
	}
}
