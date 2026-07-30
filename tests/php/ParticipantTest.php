<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ParticipantTest extends TestCase {
	public static function dataCanStartCallWithGroupRestriction(): array {
		return [
			'no restriction' => [[], Attendee::ACTOR_USERS, [], false, true],
			'member of allowed group' => [['group1', 'group2'], Attendee::ACTOR_USERS, ['group2'], false, true],
			'not a member' => [['group1'], Attendee::ACTOR_USERS, ['group2'], false, false],
			'guests are blocked' => [['group1'], Attendee::ACTOR_GUESTS, [], false, false],
			'email guests are blocked' => [['group1'], Attendee::ACTOR_EMAILS, [], false, false],
			'federated users are blocked' => [['group1'], Attendee::ACTOR_FEDERATED_USERS, [], false, false],
			'host decides for proxy conversations' => [['group1'], Attendee::ACTOR_USERS, [], true, true],
		];
	}

	#[DataProvider('dataCanStartCallWithGroupRestriction')]
	public function testCanStartCallWithGroupRestriction(
		array $allowedGroups,
		string $actorType,
		array $userGroups,
		bool $isFederatedConversation,
		bool $expected,
	): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('isFederatedConversation')->willReturn($isFederatedConversation);

		$attendee = Attendee::fromRow([
			'actor_type' => $actorType,
			'actor_id' => 'user1',
			'participant_type' => $actorType === Attendee::ACTOR_USERS ? Participant::USER : Participant::GUEST,
			'permissions' => Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_CALL_START,
		]);
		$participant = new Participant($room, $attendee, null);

		$serverConfig = $this->createMock(IConfig::class);
		$serverConfig->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'start_calls', (string)Room::START_CALL_EVERYONE)
			->willReturn((string)Room::START_CALL_EVERYONE);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('start_calls_groups')
			->willReturn($allowedGroups);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isInGroup')
			->willReturnCallback(static fn (string $userId, string $groupId): bool => $userId === 'user1' && in_array($groupId, $userGroups, true));

		$this->assertSame($expected, $participant->canStartCall($serverConfig, $appConfig, $groupManager));
	}
	protected function createParticipant(bool $isChannel, int $participantType, int $permissions): Participant {
		$room = $this->createMock(Room::class);
		$room->method('isChannel')->willReturn($isChannel);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);

		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'user',
			'participant_type' => $participantType,
			'permissions' => $permissions,
		]);

		return new Participant($room, $attendee, null);
	}

	protected function createConfig(int $startCalls = Room::START_CALL_EVERYONE): IConfig&MockObject {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn((string)$startCalls);
		return $config;
	}

	public function testChannelRemovesCallPermissionsFromModerators(): void {
		$participant = $this->createParticipant(true, Participant::MODERATOR, Attendee::PERMISSIONS_DEFAULT);
		$permissions = $participant->getPermissions();

		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_START, 'Moderators can not start calls in a channel');
		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_JOIN, 'Moderators can not join calls in a channel');
		$this->assertSame(Attendee::PERMISSIONS_CHAT, $permissions & Attendee::PERMISSIONS_CHAT, 'Moderators can still post in a channel');
	}

	public function testChannelRemovesCallPermissionsFromIndividuallyGrantedUsers(): void {
		$participant = $this->createParticipant(true, Participant::USER, Attendee::PERMISSIONS_CUSTOM
			| Attendee::PERMISSIONS_CHAT
			| Attendee::PERMISSIONS_CALL_JOIN
			| Attendee::PERMISSIONS_CALL_START);
		$permissions = $participant->getPermissions();

		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_START, 'Individually granted call permissions are ignored in a channel');
		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_JOIN, 'Individually granted call permissions are ignored in a channel');
		$this->assertSame(Attendee::PERMISSIONS_CHAT, $permissions & Attendee::PERMISSIONS_CHAT, 'Individually granted chat permission is kept in a channel');
	}

	public function testNonChannelKeepsCallPermissions(): void {
		$participant = $this->createParticipant(false, Participant::MODERATOR, Attendee::PERMISSIONS_DEFAULT);
		$permissions = $participant->getPermissions();

		$this->assertSame(Attendee::PERMISSIONS_CALL_START, $permissions & Attendee::PERMISSIONS_CALL_START);
		$this->assertSame(Attendee::PERMISSIONS_CALL_JOIN, $permissions & Attendee::PERMISSIONS_CALL_JOIN);
	}

	public function testCanStartCallIsFalseInChannelForModerators(): void {
		$participant = $this->createParticipant(true, Participant::MODERATOR, Attendee::PERMISSIONS_DEFAULT);
		$this->assertFalse($participant->canStartCall($this->createConfig(), $this->createMock(IAppConfig::class), $this->createMock(IGroupManager::class)));
	}

	public function testCanStartCallIsFalseInChannelForUsers(): void {
		$participant = $this->createParticipant(true, Participant::USER, Attendee::PERMISSIONS_DEFAULT);
		$this->assertFalse($participant->canStartCall($this->createConfig(), $this->createMock(IAppConfig::class), $this->createMock(IGroupManager::class)));
	}

	public function testCanStartCallIsTrueInNonChannelForModerators(): void {
		$participant = $this->createParticipant(false, Participant::MODERATOR, Attendee::PERMISSIONS_DEFAULT);
		$this->assertTrue($participant->canStartCall($this->createConfig(), $this->createMock(IAppConfig::class), $this->createMock(IGroupManager::class)));
	}
}
