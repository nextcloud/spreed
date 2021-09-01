<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Participant;
use OCP\AppFramework\Db\DoesNotExistException;
use Test\TestCase;

/**
 * @group DB
 */
class AttendeeMapperTest extends TestCase {

	/** @var AttendeeMapper */
	protected $attendeeMapper;


	public function setUp(): void {
		parent::setUp();

		$this->attendeeMapper = new AttendeeMapper(
			\OC::$server->getDatabaseConnection()
		);
	}

	public function dataModifyPublishingPermissions(): array {
		return [
			0 => [
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
				],
				Participant::PERMISSIONS_SET,
				Participant::FLAG_IN_CALL,
				false,
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
				],
			],
			1 => [
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
				],
				Participant::PERMISSIONS_SET,
				Participant::FLAG_IN_CALL,
				true,
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
				],
			],
			2 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
				],
				Participant::PERMISSIONS_SET,
				Participant::FLAG_IN_CALL,
				false,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
				],
			],
			3 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
				],
				Participant::PERMISSIONS_SET,
				Participant::FLAG_IN_CALL,
				true,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
				],
			],
			4 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
				],
				Participant::PERMISSIONS_ADD,
				Participant::FLAG_IN_CALL,
				true,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO + Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_VIDEO + Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
				],
			],
			5 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO + Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_VIDEO + Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_IN_CALL,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u3',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
				],
				Participant::PERMISSIONS_REMOVE,
				Participant::FLAG_IN_CALL,
				true,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'publishing_permissions' => Participant::FLAG_WITH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_DISCONNECTED,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u3',
						'participant_type' => Participant::USER,
						'publishing_permissions' => Participant::FLAG_WITH_AUDIO + Participant::FLAG_WITH_VIDEO,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider dataModifyPublishingPermissions
	 * @param array $attendees
	 * @param string $mode
	 * @param int $permission
	 * @param bool $includeModerators
	 * @param array $expected
	 */
	public function testModifyPublishingPermissions(array $attendees, string $mode, int $permission, bool $includeModerators, array $expected): void {
		$roomId = 12345678;

		foreach ($attendees as $attendeeData) {
			try {
				$attendee = $this->attendeeMapper->findByActor($roomId, $attendeeData['actor_type'], $attendeeData['actor_id']);
				$this->attendeeMapper->delete($attendee);
			} catch (DoesNotExistException $e) {
			}

			$attendee = new Attendee();
			$attendee->setRoomId($roomId);
			$attendee->setActorType($attendeeData['actor_type']);
			$attendee->setActorId($attendeeData['actor_id']);
			$attendee->setParticipantType($attendeeData['participant_type']);
			$attendee->setPublishingPermissions($attendeeData['publishing_permissions']);
			$this->attendeeMapper->insert($attendee);
		}

		$this->attendeeMapper->modifyPublishingPermissions($roomId, $mode, $permission, $includeModerators);

		foreach ($expected as $attendeeData) {
			$attendee = $this->attendeeMapper->findByActor($roomId, $attendeeData['actor_type'], $attendeeData['actor_id']);

			$this->assertEquals(
				$attendeeData['publishing_permissions'],
				$attendee->getPublishingPermissions(),
				'Publishing permissions mismatch for ' . $attendeeData['actor_type'] . '#' . $attendeeData['actor_id']
			);
			$this->attendeeMapper->delete($attendee);
		}
	}
}
