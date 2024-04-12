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
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
class AttendeeMapperTest extends TestCase {
	protected ?AttendeeMapper $attendeeMapper = null;


	public function setUp(): void {
		parent::setUp();

		$this->attendeeMapper = new AttendeeMapper(
			\OCP\Server::get(IDBConnection::class)
		);
	}

	public static function dataModifyPermissions(): array {
		return [
			0 => [
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
				],
				Attendee::PERMISSIONS_MODIFY_SET,
				Attendee::PERMISSIONS_CALL_START,
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
				],
			],
			1 => [
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
				],
				Attendee::PERMISSIONS_MODIFY_SET,
				Attendee::PERMISSIONS_CALL_START,
				[
					[
						'actor_type' => Attendee::ACTOR_CIRCLES,
						'actor_id' => 'c1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_GROUPS,
						'actor_id' => 'g1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
				],
			],
			2 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
				],
				Attendee::PERMISSIONS_MODIFY_SET,
				Attendee::PERMISSIONS_CALL_START,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
				],
			],
			3 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
				],
				Attendee::PERMISSIONS_MODIFY_ADD,
				Attendee::PERMISSIONS_CALL_START,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_VIDEO + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
				],
			],
			4 => [
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_VIDEO + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_CALL_START,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u3',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
				],
				Attendee::PERMISSIONS_MODIFY_REMOVE,
				Attendee::PERMISSIONS_CALL_START,
				[
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'o1',
						'participant_type' => Participant::OWNER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'm1',
						'participant_type' => Participant::MODERATOR,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u1',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u2',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM,
					],
					[
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'u3',
						'participant_type' => Participant::USER,
						'permissions' => Attendee::PERMISSIONS_CUSTOM + Attendee::PERMISSIONS_PUBLISH_AUDIO + Attendee::PERMISSIONS_PUBLISH_VIDEO,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider dataModifyPermissions
	 */
	public function testModifyPermissions(array $attendees, string $mode, int $permission, array $expected): void {
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
			$attendee->setPermissions($attendeeData['permissions']);
			$this->attendeeMapper->insert($attendee);
		}

		$this->attendeeMapper->modifyPermissions($roomId, $mode, $permission);

		foreach ($expected as $attendeeData) {
			$attendee = $this->attendeeMapper->findByActor($roomId, $attendeeData['actor_type'], $attendeeData['actor_id']);

			$this->assertEquals(
				$attendeeData['permissions'],
				$attendee->getPermissions(),
				'Permissions mismatch for ' . $attendeeData['actor_type'] . '#' . $attendeeData['actor_id']
			);
			$this->attendeeMapper->delete($attendee);
		}
	}
}
