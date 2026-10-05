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
use OCA\Talk\Events\AttendeesRemovedEvent;
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
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserManager;
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
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);
		$this->mountManager = $this->createMock(IMountManager::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new Listener(
			$this->config,
			$this->roomShareProvider,
			$this->mountManager,
			$this->mountProviderCollection,
			$this->userMountCache,
			$this->logger,
		);
	}

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

	private function makeUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
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
