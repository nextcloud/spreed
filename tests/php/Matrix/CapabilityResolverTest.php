<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix;

use Nextcloud\Matrix\Model\Event;
use Nextcloud\Matrix\Model\Member;
use Nextcloud\Matrix\Model\PowerLevels;
use Nextcloud\Matrix\Model\RoomState;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Sync\CapabilityResolver;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use Test\TestCase;

class CapabilityResolverTest extends TestCase {
	private CapabilityResolver $resolver;

	protected function setUp(): void {
		parent::setUp();
		$this->resolver = new CapabilityResolver();
	}

	private function state(array $powerLevels = [], bool $encrypted = false): RoomState {
		$state = new RoomState('!r:hs');
		$state->apply(new Event('$c', 'm.room.create', '@admin:hs', 1, ['creator' => '@admin:hs', 'room_version' => '10'], ''));
		if ($powerLevels !== []) {
			$state->apply(new Event('$p', 'm.room.power_levels', '@admin:hs', 2, $powerLevels, ''));
		}
		if ($encrypted) {
			$state->apply(new Event('$e', 'm.room.encryption', '@admin:hs', 3, ['algorithm' => 'm.megolm.v1.aes-sha2'], ''));
		}
		return $state;
	}

	public function testRoomCapabilitiesNeverAllowCalls(): void {
		$homeserver = new Homeserver();
		$homeserver->setVersionsJson(json_encode(['versions' => ['v1.3', 'v1.11']]));
		$homeserver->setAllowUpload(false);
		$capabilities = $this->resolver->forRoom($this->state(), $homeserver, true);
		self::assertFalse($capabilities['calls']);
		self::assertFalse($capabilities['lobby']);
		self::assertFalse($capabilities['sipEnabled']);
		self::assertTrue($capabilities['threads']);
		self::assertFalse($capabilities['upload']);
		self::assertTrue($capabilities['isDirect']);
		self::assertFalse($capabilities['encrypted']);
		self::assertSame('10', $capabilities['roomVersion']);
	}

	public function testThreadsRequireSpecVersion(): void {
		$homeserver = new Homeserver();
		$homeserver->setVersionsJson(json_encode(['versions' => ['v1.1', 'v1.2']]));
		self::assertFalse($this->resolver->forRoom($this->state(), $homeserver, false)['threads']);
		$homeserver->setVersionsJson(json_encode(['versions' => ['v1.1'], 'unstable_features' => ['org.matrix.msc3440.stable' => true]]));
		self::assertTrue($this->resolver->forRoom($this->state(), $homeserver, false)['threads']);
	}

	public function testUserCapabilitiesFollowPowerLevels(): void {
		$powerLevels = new PowerLevels(['users' => ['@admin:hs' => 100, '@mod:hs' => 50], 'users_default' => 0, 'events' => ['m.room.name' => 50], 'kick' => 50, 'invite' => 0, 'redact' => 50], '@admin:hs');

		$admin = $this->resolver->forUser($powerLevels, '@admin:hs', Member::JOIN, false);
		self::assertTrue($admin['canSend']);
		self::assertTrue($admin['canRename']);
		self::assertTrue($admin['canKick']);
		self::assertTrue($admin['canPromote']);
		self::assertTrue($admin['isAdmin']);

		$user = $this->resolver->forUser($powerLevels, '@user:hs', Member::JOIN, false);
		self::assertTrue($user['canSend']);
		self::assertTrue($user['canInvite']);
		self::assertFalse($user['canRename']);
		self::assertFalse($user['canKick']);
		self::assertFalse($user['canDeleteOthers']);
		self::assertTrue($user['canDeleteOwn']);
		self::assertFalse($user['isModerator']);

		$invited = $this->resolver->forUser($powerLevels, '@user:hs', Member::INVITE, false);
		self::assertFalse($invited['canSend']);
		self::assertSame('not-joined', $invited['canSendReason']);

		$encrypted = $this->resolver->forUser($powerLevels, '@user:hs', Member::JOIN, true);
		self::assertTrue($encrypted['canSend'], 'encrypted rooms are writable since phase 2');
		self::assertNull($encrypted['canSendReason']);

		$policyBlocked = $this->resolver->forUser($powerLevels, '@user:hs', Member::JOIN, true, false);
		self::assertFalse($policyBlocked['canSend']);
		self::assertSame('e2ee-disabled', $policyBlocked['canSendReason']);
		self::assertSame(Attendee::PERMISSIONS_CUSTOM, $this->resolver->attendeePermissions($powerLevels, '@user:hs', Member::JOIN, true, false));
	}

	public function testParticipantTypeAndPermissions(): void {
		$powerLevels = new PowerLevels(['users' => ['@admin:hs' => 100, '@mod:hs' => 50], 'users_default' => 0, 'events_default' => 0], '@admin:hs');
		self::assertSame(Participant::OWNER, $this->resolver->participantType($powerLevels, '@admin:hs', '@admin:hs'));
		self::assertSame(Participant::MODERATOR, $this->resolver->participantType($powerLevels, '@mod:hs', '@admin:hs'));
		self::assertSame(Participant::USER, $this->resolver->participantType($powerLevels, '@user:hs', '@admin:hs'));

		$permissions = $this->resolver->attendeePermissions($powerLevels, '@user:hs', Member::JOIN, false);
		self::assertSame(Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_CHAT | Attendee::PERMISSIONS_REACT, $permissions);
		self::assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_START);
		self::assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_JOIN);

		$muted = new PowerLevels(['events_default' => 50, 'users_default' => 0], '@admin:hs');
		self::assertSame(Attendee::PERMISSIONS_CUSTOM, $this->resolver->attendeePermissions($muted, '@user:hs', Member::JOIN, false));
		self::assertSame(Attendee::PERMISSIONS_CUSTOM, $this->resolver->attendeePermissions($powerLevels, '@user:hs', Member::LEAVE, false));
		self::assertSame(Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_CHAT | Attendee::PERMISSIONS_REACT, $this->resolver->roomDefaultPermissions());
	}

	public function testMergeAddsUserPartAndRoomId(): void {
		$matrixRoom = new MatrixRoom();
		$matrixRoom->setMatrixRoomId('!r:hs');
		$matrixRoom->setCreator('@admin:hs');
		$matrixRoom->setPowerLevelsArray(['users' => ['@admin:hs' => 100], 'users_default' => 0]);
		$matrixRoom->setCapabilitiesArray(['calls' => false, 'encrypted' => false]);

		$merged = $this->resolver->merge($matrixRoom, '@admin:hs', Member::JOIN);
		self::assertSame('!r:hs', $merged['matrixRoomId']);
		self::assertFalse($merged['calls']);
		self::assertTrue($merged['canSend']);
		self::assertTrue($merged['isAdmin']);

		$anonymous = $this->resolver->merge($matrixRoom, null, Member::LEAVE);
		self::assertArrayNotHasKey('canSend', $anonymous);
	}
}
