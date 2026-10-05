<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Share;

use OC\Share20\Share;
use OCA\Files_Sharing\Event\ShareMountedEvent;
use OCA\Files_Sharing\SharedMount;
use OCA\Talk\Config;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Share\Listener;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Files\Cache\ICache;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ListenerTest extends TestCase {
	/** Node id of the shared file */
	protected const SHARED_NODE_ID = 42;
	/** Node id of another file with the same name */
	protected const OTHER_NODE_ID = 23;

	protected Config&MockObject $config;
	protected Manager&MockObject $manager;
	protected RoomShareProvider&MockObject $roomShareProvider;
	protected IMountManager&MockObject $mountManager;
	protected IMountProviderCollection&MockObject $mountProviderCollection;
	protected IUserMountCache&MockObject $userMountCache;
	protected LoggerInterface&MockObject $logger;
	protected Listener $listener;
	/** @var list<string> */
	protected array $mountPoints = [];

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->manager = $this->createMock(Manager::class);
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);
		$this->mountManager = $this->createMock(IMountManager::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new Listener(
			$this->config,
			$this->manager,
			$this->roomShareProvider,
			$this->mountManager,
			$this->mountProviderCollection,
			$this->userMountCache,
			$this->logger,
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

		$room = $this->createStub(Room::class);
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

		$room = $this->createStub(Room::class);
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

		$room = $this->createStub(Room::class);
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

	// -------------------------------------------------------------------------
	// roomAttendeesRemovedEvent
	// -------------------------------------------------------------------------

	private function makeAttendee(string $actorType, string $actorId): Attendee {
		$attendee = new Attendee();
		$attendee->setActorType($actorType);
		$attendee->setActorId($actorId);
		return $attendee;
	}

	private function makeRoom(string $token): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn($token);
		return $room;
	}

	/**
	 * Only user attendees can have received shares, so the other actor types
	 * must not be forwarded to the share provider. The remaining actor ids must
	 * be passed on as a list, not with the keys of the original array.
	 */
	public function testRoomAttendeesRemovedOnlyHandlesUserAttendees(): void {
		$room = $this->makeRoom('token123');
		$event = new AttendeesRemovedEvent($room, [
			$this->makeAttendee(Attendee::ACTOR_GROUPS, 'group1'),
			$this->makeAttendee(Attendee::ACTOR_USERS, 'alice'),
			$this->makeAttendee(Attendee::ACTOR_GUESTS, 'guest1'),
			$this->makeAttendee(Attendee::ACTOR_USERS, 'bob'),
			$this->makeAttendee(Attendee::ACTOR_CIRCLES, 'circle1'),
			$this->makeAttendee(Attendee::ACTOR_FEDERATED_USERS, 'carol@remote.test'),
		]);

		$this->roomShareProvider->expects($this->once())
			->method('deleteReceivedSharesInRoom')
			->with('token123', ['alice', 'bob']);

		$this->listener->handle($event);
	}

	public function testRoomAttendeesRemovedWithoutUserAttendees(): void {
		$room = $this->makeRoom('token123');
		$event = new AttendeesRemovedEvent($room, [
			$this->makeAttendee(Attendee::ACTOR_GUESTS, 'guest1'),
		]);

		$this->roomShareProvider->expects($this->once())
			->method('deleteReceivedSharesInRoom')
			->with('token123', []);

		$this->listener->handle($event);
	}

	public function testRoomAttendeesRemovedWithoutAttendees(): void {
		$room = $this->makeRoom('token123');
		$event = new AttendeesRemovedEvent($room, []);

		$this->roomShareProvider->expects($this->once())
			->method('deleteReceivedSharesInRoom')
			->with('token123', []);

		$this->listener->handle($event);
	}

	public function testRoomPasswordChangeUpdatesShares(): void {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('token1');
		$room->method('getPassword')->willReturn('hash');

		$this->roomShareProvider->expects($this->once())
			->method('setPasswordInRoom')
			->with('token1', 'hash');

		$this->listener->handle(new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_PASSWORD, 'secret'));
	}

	public function testOtherRoomChangesDoNotUpdateShares(): void {
		$room = $this->createMock(Room::class);

		$this->roomShareProvider->expects($this->never())
			->method('setPasswordInRoom');

		$this->listener->handle(new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_NAME, 'new name'));
	}

	// -------------------------------------------------------------------------
	// resolveMountPoint — shares without userroom share
	// -------------------------------------------------------------------------

	private function makeRoomShare(string $target, int $nodeId = self::SHARED_NODE_ID, int $shareType = IShare::TYPE_ROOM): Share {
		$share = new Share($this->createMock(IRootFolder::class), $this->createMock(IUserManager::class));
		$share->setId('1')
			->setShareType($shareType)
			->setNodeId($nodeId)
			->setTarget($target);
		return $share;
	}

	/**
	 * @param list<Share> $groupedShares
	 */
	private function makeShareMountedEvent(Share $share, array $groupedShares, string $uid = 'alice'): ShareMountedEvent {
		$mount = $this->createMock(SharedMount::class);
		$mount->method('getShare')->willReturn($share);
		$mount->method('getUser')->willReturn($this->makeUser($uid));
		$mount->method('getGroupedShares')->willReturn($groupedShares);
		$mount->method('setMountPoint')
			->willReturnCallback(function (string $mountPoint): void {
				$this->mountPoints[] = $mountPoint;
			});
		return new ShareMountedEvent($mount);
	}

	/**
	 * @param list<string> $existingFiles internal paths in the home storage
	 */
	private function makeHomeMount(array $existingFiles): IMountPoint&MockObject {
		$cache = $this->createMock(ICache::class);
		$cache->method('inCache')
			->willReturnCallback(fn (string $path): bool => in_array($path, $existingFiles, true));
		$storage = $this->createMock(IStorage::class);
		$storage->method('getCache')->willReturn($cache);
		$homeMount = $this->createMock(IMountPoint::class);
		$homeMount->method('getStorage')->willReturn($storage);
		return $homeMount;
	}

	/**
	 * @param array<string, int> $rootIds root ids of the cached mounts by mount point
	 */
	private function mockCachedMounts(array $rootIds): void {
		$this->userMountCache->method('getMountAtPath')
			->willReturnCallback(function (IUser $user, string $mountPoint) use ($rootIds): ?ICachedMountInfo {
				if (!isset($rootIds[$mountPoint])) {
					return null;
				}
				$mount = $this->createMock(ICachedMountInfo::class);
				$mount->method('getRootId')->willReturn($rootIds[$mountPoint]);
				return $mount;
			});
	}

	public static function dataResolveMountPoint(): array {
		return [
			'attachment folder' => ['/Talk', [], [], '/Talk/welcome.txt'],
			'root folder as attachment folder' => ['/', [], [], '/welcome.txt'],
			'existing file' => ['/Talk', ['files/Talk/welcome.txt'], [], '/Talk/welcome (2).txt'],
			'existing files' => ['/Talk', ['files/Talk/welcome.txt', 'files/Talk/welcome (2).txt'], [], '/Talk/welcome (3).txt'],
			'existing file in root folder' => ['/', ['files/welcome.txt'], [], '/welcome (2).txt'],
			'mount of another file' => ['/Talk', [], ['/alice/files/Talk/welcome.txt/' => self::OTHER_NODE_ID], '/Talk/welcome (2).txt'],
			'mount of the same file' => ['/Talk', [], ['/alice/files/Talk/welcome.txt/' => self::SHARED_NODE_ID], '/Talk/welcome.txt'],
			'existing name without extension' => ['/Talk', ['files/Talk/other'], [], '/Talk/other (2)', 'other'],
		];
	}

	/**
	 * @param list<string> $existingFiles
	 * @param array<string, int> $cachedMounts
	 */
	#[DataProvider('dataResolveMountPoint')]
	public function testResolveMountPoint(string $attachmentFolder, array $existingFiles, array $cachedMounts, string $expected, string $name = 'welcome.txt'): void {
		$this->config->method('getAttachmentFolder')
			->with('alice')
			->willReturn($attachmentFolder);
		$this->mountManager->method('getAll')
			->willReturn(['/alice/' => $this->makeHomeMount($existingFiles)]);
		$this->mockCachedMounts($cachedMounts);

		$share = $this->makeRoomShare('/{TALK_PLACEHOLDER}/' . $name);
		$groupedShare = $this->makeRoomShare('/{TALK_PLACEHOLDER}/' . $name);
		$this->roomShareProvider->expects($this->once())
			->method('move')
			->with($groupedShare, 'alice');

		$this->listener->handle($this->makeShareMountedEvent($share, [$groupedShare]));

		$this->assertSame($expected, $share->getTarget());
		$this->assertSame($expected, $groupedShare->getTarget());
		$this->assertSame(['/alice/files' . $expected . '/'], $this->mountPoints);
	}

	public static function dataResolveMountPointIgnoresShare(): array {
		return [
			'user share' => [IShare::TYPE_USER, '/{TALK_PLACEHOLDER}/welcome.txt'],
			'room share with userroom share' => [IShare::TYPE_ROOM, '/Talk/welcome.txt'],
		];
	}

	#[DataProvider('dataResolveMountPointIgnoresShare')]
	public function testResolveMountPointIgnoresShare(int $shareType, string $target): void {
		$share = $this->makeRoomShare($target, shareType: $shareType);
		$this->roomShareProvider->expects($this->never())
			->method('move');

		$this->listener->handle($this->makeShareMountedEvent($share, [$share]));

		$this->assertSame($target, $share->getTarget());
		$this->assertSame([], $this->mountPoints);
	}

	public function testResolveMountPointOnlyStoresGroupedRoomSharesWithPlaceholder(): void {
		$this->config->method('getAttachmentFolder')->willReturn('/Talk');
		$this->mountManager->method('getAll')->willReturn(['/alice/' => $this->makeHomeMount([])]);

		$share = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt');
		$withPlaceholder = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt');
		$withUserRoomShare = $this->makeRoomShare('/Talk/moved.txt');
		$userShare = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt', shareType: IShare::TYPE_USER);
		$this->roomShareProvider->expects($this->once())
			->method('move')
			->with($withPlaceholder, 'alice');

		$this->listener->handle($this->makeShareMountedEvent($share, [$withPlaceholder, $withUserRoomShare, $userShare]));

		$this->assertSame('/Talk/moved.txt', $withUserRoomShare->getTarget());
		$this->assertSame('/{TALK_PLACEHOLDER}/welcome.txt', $userShare->getTarget());
	}

	/**
	 * Targets resolved in the same request are not in the mount cache yet
	 */
	public function testResolveMountPointSameNameInOneRequest(): void {
		$this->config->method('getAttachmentFolder')->willReturn('/Talk');
		$this->mountManager->method('getAll')->willReturn(['/alice/' => $this->makeHomeMount([])]);

		$first = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt', self::SHARED_NODE_ID);
		$other = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt', self::OTHER_NODE_ID);
		$firstAgain = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt', self::SHARED_NODE_ID);

		$this->listener->handle($this->makeShareMountedEvent($first, [$first]));
		$this->listener->handle($this->makeShareMountedEvent($other, [$other]));
		$this->listener->handle($this->makeShareMountedEvent($firstAgain, [$firstAgain]));

		$this->assertSame('/Talk/welcome.txt', $first->getTarget());
		$this->assertSame('/Talk/welcome (2).txt', $other->getTarget());
		$this->assertSame('/Talk/welcome.txt', $firstAgain->getTarget());
	}

	public function testResolveMountPointSameNameForDifferentUsers(): void {
		$this->config->method('getAttachmentFolder')->willReturn('/Talk');
		$this->mountManager->method('getAll')->willReturn([
			'/alice/' => $this->makeHomeMount([]),
			'/bob/' => $this->makeHomeMount([]),
		]);

		$aliceShare = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt', self::SHARED_NODE_ID);
		$bobShare = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt', self::OTHER_NODE_ID);

		$this->listener->handle($this->makeShareMountedEvent($aliceShare, [$aliceShare]));
		$this->listener->handle($this->makeShareMountedEvent($bobShare, [$bobShare], 'bob'));

		$this->assertSame('/Talk/welcome.txt', $aliceShare->getTarget());
		$this->assertSame('/Talk/welcome.txt', $bobShare->getTarget());
	}

	public function testResolveMountPointWithoutHomeMountSetUp(): void {
		$this->config->method('getAttachmentFolder')->willReturn('/Talk');
		$this->mountManager->method('getAll')->willReturn([]);
		$this->mountProviderCollection->expects($this->once())
			->method('getHomeMountForUser')
			->willReturn($this->makeHomeMount(['files/Talk/welcome.txt']));

		$share = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt');
		$this->listener->handle($this->makeShareMountedEvent($share, [$share]));

		$this->assertSame('/Talk/welcome (2).txt', $share->getTarget());
	}

	public function testResolveMountPointKeepsMountOnError(): void {
		$this->config->method('getAttachmentFolder')->willReturn('/Talk');
		$this->mountManager->method('getAll')->willThrowException(new \RuntimeException());
		$this->roomShareProvider->expects($this->never())
			->method('move');
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('room share 1'), $this->callback(fn (array $context): bool => $context['exception'] instanceof \RuntimeException));

		$share = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt');
		$this->listener->handle($this->makeShareMountedEvent($share, [$share]));

		$this->assertSame('/{TALK_PLACEHOLDER}/welcome.txt', $share->getTarget());
		$this->assertSame([], $this->mountPoints);
	}

	public function testResolveMountPointKeepsResolvedMountWhenStoringFails(): void {
		$this->config->method('getAttachmentFolder')->willReturn('/Talk');
		$this->mountManager->method('getAll')->willReturn(['/alice/' => $this->makeHomeMount([])]);
		$this->roomShareProvider->method('move')
			->willThrowException(new \RuntimeException());
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('room share 1'), $this->callback(fn (array $context): bool => $context['exception'] instanceof \RuntimeException));

		$share = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt');
		$groupedShare = $this->makeRoomShare('/{TALK_PLACEHOLDER}/welcome.txt');
		$this->listener->handle($this->makeShareMountedEvent($share, [$groupedShare]));

		$this->assertSame('/Talk/welcome.txt', $share->getTarget());
		$this->assertSame(['/alice/files/Talk/welcome.txt/'], $this->mountPoints);
	}
}
