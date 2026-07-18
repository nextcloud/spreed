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
}
