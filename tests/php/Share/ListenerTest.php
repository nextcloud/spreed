<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Share;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Share\Listener;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Files\Node;
use OCP\IUser;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ListenerTest extends TestCase {
	protected Config&MockObject $config;
	protected Manager&MockObject $manager;
	protected RoomShareProvider&MockObject $roomShareProvider;
	protected Listener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->manager = $this->createMock(Manager::class);
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);

		$this->listener = new Listener(
			$this->config,
			$this->manager,
			$this->roomShareProvider,
		);
	}

	private function makeShare(int $type, string $sharedWith): IShare&MockObject {
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn($type);
		$share->method('getSharedWith')->willReturn($sharedWith);
		return $share;
	}

	private function makeUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — ignored for non-room share types
	// -------------------------------------------------------------------------

	public function testOverwriteMountPointIgnoresNonRoomShare(): void {
		$share = $this->makeShare(IShare::TYPE_USER, 'bob');
		$user = $this->makeUser('bob');

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->expects($this->never())->method('setParent');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — flat (legacy) case: parent === placeholder
	// -------------------------------------------------------------------------

	public function testOverwriteMountPointFlatCase(): void {
		$share = $this->makeShare(IShare::TYPE_ROOM, 'token1');
		$user = $this->makeUser('bob');

		$this->config->method('getAttachmentFolder')->with('bob')->willReturn('/Talk');

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn(RoomShareProvider::TALK_FOLDER_PLACEHOLDER);
		$event->expects($this->once())->method('setCreateParent')->with(true);
		$event->expects($this->once())->method('setParent')->with('/Talk');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — nested case, group room (same name for all users)
	// -------------------------------------------------------------------------

	public function testOverwriteMountPointNestedGroupRoom(): void {
		$token = 'grp1';
		$share = $this->makeShare(IShare::TYPE_ROOM, $token);
		$user = $this->makeUser('bob');

		$room = $this->createMock(Room::class);
		$this->config->method('isConversationSubfoldersEnabled')->willReturn(true);
		$this->manager->method('getRoomByToken')->with($token)->willReturn($room);
		$this->config->method('getAttachmentFolder')->with('bob')->willReturn('/Talk');
		$this->config->method('getConversationFolderName')->with($room, 'bob')->willReturn('My Room-grp1');

		$parent = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/My Room-grp1';

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn($parent);
		$event->expects($this->once())->method('setCreateParent')->with(true);
		$event->expects($this->once())->method('setParent')->with('/Talk/My Room-grp1');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — nested case, 1-1 room (display name differs per user)
	// -------------------------------------------------------------------------

	/**
	 * Alice uploads to a 1-1 room. From Alice's perspective the conversation
	 * folder is named after Bob ("Bob-TOKEN"). The share target is therefore
	 * stored as /{TALK_PLACEHOLDER}/Bob-TOKEN/Alice-alice.
	 *
	 * When the mount point is resolved for Bob, the conversation folder must be
	 * named from Bob's perspective ("Alice-TOKEN"), not Alice's ("Bob-TOKEN").
	 */
	public function testOverwriteMountPointNestedOneToOneRoom(): void {
		$token = 'oneone';
		$share = $this->makeShare(IShare::TYPE_ROOM, $token);
		$bob = $this->makeUser('bob');

		$room = $this->createMock(Room::class);
		$this->config->method('isConversationSubfoldersEnabled')->willReturn(true);
		$this->manager->method('getRoomByToken')->with($token)->willReturn($room);
		$this->config->method('getAttachmentFolder')->with('bob')->willReturn('/Talk');
		// From Bob's perspective the 1-1 room is named after Alice.
		$this->config->method('getConversationFolderName')->with($room, 'bob')->willReturn('Alice-oneone');

		// The stored target uses Alice's view of the room name.
		$parent = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/Bob-oneone';

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($bob);
		$event->method('getParent')->willReturn($parent);
		$event->expects($this->once())->method('setCreateParent')->with(true);
		// Must resolve to Bob's view, not Alice's.
		$event->expects($this->once())->method('setParent')->with('/Talk/Alice-oneone');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — nested with user-subfolder segment
	// -------------------------------------------------------------------------

	public function testOverwriteMountPointNestedWithUserSubfolder(): void {
		$token = 'tok2';
		$share = $this->makeShare(IShare::TYPE_ROOM, $token);
		$user = $this->makeUser('carol');

		$room = $this->createMock(Room::class);
		$this->config->method('isConversationSubfoldersEnabled')->willReturn(true);
		$this->manager->method('getRoomByToken')->with($token)->willReturn($room);
		$this->config->method('getAttachmentFolder')->with('carol')->willReturn('/Talk');
		$this->config->method('getConversationFolderName')->with($room, 'carol')->willReturn('Room-tok2');

		// Parent includes user-subfolder segment.
		$parent = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/Room-tok2/Alice-alice';

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn($parent);
		$event->expects($this->once())->method('setCreateParent')->with(true);
		$event->expects($this->once())->method('setParent')->with('/Talk/Room-tok2/Alice-alice');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — room not found falls back to sharer's folder name
	// -------------------------------------------------------------------------

	public function testOverwriteMountPointFallsBackWhenRoomNotFound(): void {
		$token = 'gone';
		$share = $this->makeShare(IShare::TYPE_ROOM, $token);
		$user = $this->makeUser('dave');

		$this->config->method('isConversationSubfoldersEnabled')->willReturn(true);
		$this->manager->method('getRoomByToken')
			->with($token)
			->willThrowException(new RoomNotFoundException());
		$this->config->method('getAttachmentFolder')->with('dave')->willReturn('/Talk');

		$parent = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/OldName-gone';

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn($parent);
		$event->expects($this->once())->method('setCreateParent')->with(true);
		// Falls back to the original folder name from the stored path.
		$event->expects($this->once())->method('setParent')->with('/Talk/OldName-gone');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — conv folder name has no extractable token (legacy)
	// -------------------------------------------------------------------------

	/**
	 * If the conv folder name stored in the target does not end with a valid
	 * token suffix (e.g. a legacy folder named without a token), the listener
	 * must not call getRoomByToken and must use the stored name verbatim.
	 */
	public function testOverwriteMountPointUsesStoredNameWhenTokenNotExtractable(): void {
		$share = $this->makeShare(IShare::TYPE_ROOM, '');
		$user = $this->makeUser('frank');

		$this->config->method('isConversationSubfoldersEnabled')->willReturn(true);
		$this->manager->expects($this->never())->method('getRoomByToken');
		$this->config->method('getAttachmentFolder')->with('frank')->willReturn('/Talk');

		// Folder name has no token-like suffix.
		$parent = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/LegacyFolderName';

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn($parent);
		$event->expects($this->once())->method('setCreateParent')->with(true);
		$event->expects($this->once())->method('setParent')->with('/Talk/LegacyFolderName');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// overwriteMountPoint — unrelated parent (no placeholder) is left alone
	// -------------------------------------------------------------------------

	public function testOverwriteMountPointIgnoresUnrelatedParent(): void {
		$share = $this->makeShare(IShare::TYPE_ROOM, 'tok3');
		$user = $this->makeUser('eve');

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn('/SomeOtherFolder');
		$event->expects($this->never())->method('setParent');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// Feature-flag enforcement
	// -------------------------------------------------------------------------

	/**
	 * When conversation subfolders are disabled, overwriteShareTarget must fall
	 * back to the plain node name and must NOT inspect the attachment folder path.
	 */
	public function testOverwriteShareTargetUsesNodeNameWhenFeatureDisabled(): void {
		$node = $this->createMock(Node::class);
		$node->method('getName')->willReturn('my-subfolder');
		$node->method('getPath')->willReturn('/alice/files/Talk/Room-tok/my-subfolder');

		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_ROOM);
		$share->method('getShareOwner')->willReturn('alice');
		$share->method('getNode')->willReturn($node);

		$this->config->method('isConversationSubfoldersEnabled')->willReturn(false);
		$this->config->expects($this->never())->method('getAttachmentFolder');
		$share->expects($this->once())->method('setTarget')
			->with(RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/my-subfolder');

		$event = $this->createMock(BeforeShareCreatedEvent::class);
		$event->method('getShare')->willReturn($share);

		$this->listener->handle($event);
	}

	/**
	 * When conversation subfolders are disabled, overwriteMountPoint must not
	 * resolve nested placeholder paths — setParent must never be called.
	 */
	public function testOverwriteMountPointSkipsNestedCaseWhenFeatureDisabled(): void {
		$share = $this->makeShare(IShare::TYPE_ROOM, 'tok');
		$user = $this->makeUser('bob');

		$this->config->method('isConversationSubfoldersEnabled')->willReturn(false);
		$this->config->method('getAttachmentFolder')->with('bob')->willReturn('/Talk');

		$parent = RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/Some Room-tok';

		$event = $this->createMock(VerifyMountPointEvent::class);
		$event->method('getShare')->willReturn($share);
		$event->method('getUser')->willReturn($user);
		$event->method('getParent')->willReturn($parent);
		$event->expects($this->never())->method('setParent');

		$this->listener->handle($event);
	}
}
