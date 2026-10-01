<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Share;

use OC\Session\Memory;
use OC\Share20\Share;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Share\RoomShareProvider;
use OCA\Talk\TalkSession;
use OCP\AppFramework\PublicShareController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Server;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class RoomShareProviderTest extends TestCase {
	protected IDBConnection $connection;
	protected ISecureRandom&MockObject $secureRandom;
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected IUserSession&MockObject $userSession;
	protected TalkSession&MockObject $talkSession;
	protected Memory $session;
	protected RoomShareProvider $provider;
	protected Config&MockObject $config;

	/** @var list<int> */
	protected array $shareIds = [];
	/** @var list<int> */
	protected array $fileIds = [];

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->connection = Server::get(IDBConnection::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->session = new Memory();

		$shareManager = $this->createMock(IShareManager::class);
		$shareManager->method('newShare')
			->willReturnCallback(fn () => new Share($this->createMock(IRootFolder::class), $this->createMock(IUserManager::class)));
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')
			->willReturnCallback(fn () => new \DateTime());
		$this->config = $this->createMock(Config::class);

		$this->provider = new RoomShareProvider(
			$this->connection,
			$this->secureRandom,
			$shareManager,
			$this->createMock(IEventDispatcher::class),
			$this->manager,
			$this->participantService,
			$this->createMock(RoomService::class),
			$timeFactory,
			$this->createMock(IL10N::class),
			$this->createMock(IMimeTypeLoader::class),
			$this->createMock(IUserManager::class),
			$this->config,
			$this->session,
			$this->userSession,
			$this->talkSession,
		);
	}

	#[\Override]
	public function tearDown(): void {
		if (!empty($this->shareIds)) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('share')
				->where($delete->expr()->in('id', $delete->createNamedParameter($this->shareIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();
			$this->shareIds = [];
		}
		if (!empty($this->fileIds)) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('filecache')
				->where($delete->expr()->in('fileid', $delete->createNamedParameter($this->fileIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();
			$this->fileIds = [];
		}

		parent::tearDown();
	}

	protected function createShare(
		int $shareType,
		string $shareWith,
		?int $parent = null,
		?string $token = null,
		string $password = '',
		string $owner = 'owner',
		string $initiator = 'owner',
		int $fileSource = 42,
		string $target = '/file.txt',
	): int {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('share')
			->values([
				'share_type' => $insert->createNamedParameter($shareType, IQueryBuilder::PARAM_INT),
				'share_with' => $insert->createNamedParameter($shareWith),
				'uid_owner' => $insert->createNamedParameter($owner),
				'uid_initiator' => $insert->createNamedParameter($initiator),
				'item_type' => $insert->createNamedParameter('file'),
				'file_source' => $insert->createNamedParameter($fileSource, IQueryBuilder::PARAM_INT),
				'file_target' => $insert->createNamedParameter($target),
				'parent' => $insert->createNamedParameter($parent, $parent === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
				'token' => $insert->createNamedParameter($token),
				'password' => $insert->createNamedParameter($password),
			]);
		$insert->executeStatement();

		$shareId = $insert->getLastInsertId();
		$this->shareIds[] = $shareId;
		return $shareId;
	}

	protected function getSharePassword(int $id): ?string {
		$query = $this->connection->getQueryBuilder();
		$query->select('password')
			->from('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$password = $result->fetchOne();
		$result->closeCursor();

		return $password;
	}

	protected function createFile(string $path): int {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('filecache')
			->values([
				'storage' => $insert->createNamedParameter(424242, IQueryBuilder::PARAM_INT),
				'path' => $insert->createNamedParameter($path),
				'path_hash' => $insert->createNamedParameter(md5($path)),
				'name' => $insert->createNamedParameter(basename($path)),
			]);
		$insert->executeStatement();

		$fileId = $insert->getLastInsertId();
		$this->fileIds[] = $fileId;
		return $fileId;
	}

	protected function shareExists(int $id): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();

		return $row !== false;
	}

	/**
	 * Only the received shares of the given users in the given conversation
	 * must be removed, the room shares themselves stay untouched.
	 */
	public function testDeleteReceivedSharesInRoom(): void {
		$roomShare1 = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$roomShare2 = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$otherRoomShare = $this->createShare(IShare::TYPE_ROOM, 'token456');

		$aliceShare1 = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare1);
		$aliceShare2 = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare2);
		$bobShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'bob', $roomShare1);
		$aliceOtherRoomShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $otherRoomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice']);

		$this->assertFalse($this->shareExists($aliceShare1));
		$this->assertFalse($this->shareExists($aliceShare2));
		$this->assertTrue($this->shareExists($bobShare), 'Shares of other users must be kept');
		$this->assertTrue($this->shareExists($aliceOtherRoomShare), 'Shares in other conversations must be kept');

		$this->assertTrue($this->shareExists($roomShare1), 'The room shares must be kept');
		$this->assertTrue($this->shareExists($roomShare2), 'The room shares must be kept');
		$this->assertTrue($this->shareExists($otherRoomShare), 'The room shares must be kept');
	}

	public function testDeleteReceivedSharesInRoomForMultipleUsers(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');

		$aliceShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);
		$bobShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'bob', $roomShare);
		$carolShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'carol', $roomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice', 'bob']);

		$this->assertFalse($this->shareExists($aliceShare));
		$this->assertFalse($this->shareExists($bobShare));
		$this->assertTrue($this->shareExists($carolShare));
	}

	/**
	 * Removing attendees that are not users at all must not delete anything.
	 */
	public function testDeleteReceivedSharesInRoomWithoutUsers(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$aliceShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', []);

		$this->assertTrue($this->shareExists($aliceShare));
		$this->assertTrue($this->shareExists($roomShare));
	}

	/**
	 * A conversation without any share must not delete shares of other
	 * conversations that happen to have the same recipients.
	 */
	public function testDeleteReceivedSharesInRoomWithoutRoomShares(): void {
		$otherRoomShare = $this->createShare(IShare::TYPE_ROOM, 'token456');
		$aliceShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $otherRoomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice']);

		$this->assertTrue($this->shareExists($aliceShare));
	}

	/**
	 * Shares of other types must not be deleted, even when they are children of
	 * the room share and belong to the given user.
	 */
	public function testDeleteReceivedSharesInRoomKeepsOtherShareTypes(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'token123');
		$userShare = $this->createShare(IShare::TYPE_USER, 'alice', $roomShare);
		$userRoomShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);

		$this->provider->deleteReceivedSharesInRoom('token123', ['alice']);

		$this->assertTrue($this->shareExists($userShare));
		$this->assertFalse($this->shareExists($userRoomShare));
	}

	protected function mockPublicRoom(string $passwordHash): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_PUBLIC);
		$room->method('getToken')->willReturn('roomtoken');
		$room->method('hasPassword')->willReturn($passwordHash !== '');
		$room->method('getPassword')->willReturn($passwordHash);
		$this->manager->method('getRoomByToken')->with('roomtoken')->willReturn($room);
	}

	public function testGetShareByTokenWithoutRoomPassword(): void {
		$this->mockPublicRoom('');
		$this->createShare(IShare::TYPE_ROOM, 'roomtoken', null, 'sharetoken');

		$share = $this->provider->getShareByToken('sharetoken');

		$this->assertFalse($share->isPasswordProtected());
		$this->assertNull($this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND));
	}

	public function testGetShareByTokenWithRoomPasswordForNonParticipant(): void {
		$hash = Server::get(IHasher::class)->hash('secret');
		$this->mockPublicRoom($hash);
		$this->createShare(IShare::TYPE_ROOM, 'roomtoken', null, 'sharetoken', $hash);

		$this->participantService->method('getParticipant')
			->willThrowException(new ParticipantNotFoundException());
		$this->participantService->method('getParticipantBySession')
			->willThrowException(new ParticipantNotFoundException());

		$share = $this->provider->getShareByToken('sharetoken');

		$this->assertTrue($share->isPasswordProtected());
		$this->assertTrue($share->isPasswordHashed());
		$this->assertSame($hash, $share->getPassword());
		$this->assertNull($this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND));
		$this->assertNull($this->session->get('public_link_authenticated'));
	}

	public static function dataGetShareByTokenWithRoomPasswordForParticipant(): array {
		return [
			'user' => [true],
			'guest session' => [false],
		];
	}

	#[DataProvider('dataGetShareByTokenWithRoomPasswordForParticipant')]
	public function testGetShareByTokenWithRoomPasswordForParticipant(bool $isUser): void {
		$hash = Server::get(IHasher::class)->hash('secret');
		$this->mockPublicRoom($hash);
		$shareId = $this->createShare(IShare::TYPE_ROOM, 'roomtoken', null, 'sharetoken', $hash);

		if ($isUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
			$this->userSession->method('getUser')->willReturn($user);
			$this->participantService->method('getParticipant')
				->with($this->anything(), 'alice', false)
				->willReturn($this->createMock(Participant::class));
		} else {
			$this->participantService->method('getParticipant')
				->willThrowException(new ParticipantNotFoundException());
			$this->talkSession->method('getSessionForRoom')
				->with('roomtoken')
				->willReturn('guestsession');
			$this->participantService->method('getParticipantBySession')
				->with($this->anything(), 'guestsession')
				->willReturn($this->createMock(Participant::class));
		}

		$this->provider->getShareByToken('sharetoken');
		$this->provider->getShareByToken('sharetoken');

		$this->assertSame(
			['sharetoken' => $hash],
			json_decode($this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND), true),
		);
		$this->assertSame([(string)$shareId], $this->session->get('public_link_authenticated'));
	}

	public function testCreateCopiesRoomPassword(): void {
		$hash = Server::get(IHasher::class)->hash('secret');

		$room = $this->createMock(Room::class);
		$room->method('getReadOnly')->willReturn(Room::READ_WRITE);
		$room->method('getPassword')->willReturn($hash);
		$this->manager->method('getRoomByToken')->with('roomtoken')->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->method('getParticipant')->willReturn($participant);

		$this->secureRandom->method('generate')->willReturn('newsharetoken');

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(4242);

		$share = new Share($this->createMock(IRootFolder::class), $this->createMock(IUserManager::class));
		$share->setSharedWith('roomtoken')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setNode($node)
			->setNodeType('file')
			->setTarget('/file.txt')
			->setPermissions(1);

		$created = $this->provider->create($share);
		$this->shareIds[] = (int)$created->getId();

		$this->assertTrue($created->isPasswordProtected());
		$this->assertSame($hash, $created->getPassword());
		$this->assertSame($hash, $this->getSharePassword((int)$created->getId()));
	}

	public static function dataUpdateCopiesRoomPassword(): array {
		return [
			'room with password' => [true],
			'room without password' => [false],
		];
	}

	#[DataProvider('dataUpdateCopiesRoomPassword')]
	public function testUpdateCopiesRoomPassword(bool $withPassword): void {
		$hash = Server::get(IHasher::class)->hash('secret');
		$this->mockPublicRoom($withPassword ? $hash : '');
		$shareId = $this->createShare(IShare::TYPE_ROOM, 'roomtoken', null, 'sharetoken', $withPassword ? '' : $hash);

		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(42);

		$share = new Share($this->createMock(IRootFolder::class), $this->createMock(IUserManager::class));
		$share->setId((string)$shareId)
			->setSharedWith('roomtoken')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setNode($node)
			->setPermissions(1);

		$this->provider->update($share);

		$this->assertSame($withPassword, $share->isPasswordProtected());
		$this->assertSame($withPassword ? $hash : '', (string)$this->getSharePassword($shareId));
	}

	public function testSetPasswordInRoom(): void {
		$roomShare = $this->createShare(IShare::TYPE_ROOM, 'roomtoken');
		$userRoomShare = $this->createShare(RoomShareProvider::SHARE_TYPE_USERROOM, 'alice', $roomShare);
		$otherRoomShare = $this->createShare(IShare::TYPE_ROOM, 'othertoken');

		$this->provider->setPasswordInRoom('roomtoken', 'hash');

		$this->assertSame('hash', $this->getSharePassword($roomShare));
		$this->assertSame('', (string)$this->getSharePassword($userRoomShare));
		$this->assertSame('', (string)$this->getSharePassword($otherRoomShare));
	}

	public static function dataGetSharedWithByPathWithoutUserRoomShare(): array {
		// alice's own file, reshared into the conversation by bob
		$ownFileReshared = ['owner' => 'alice', 'initiator' => 'bob', 'room' => 'token123'];
		$received = ['owner' => 'bob', 'initiator' => 'bob', 'room' => 'token123'];
		$otherRoom = ['owner' => 'bob', 'initiator' => 'bob', 'room' => 'token456'];

		return [
			'own reshared file before received share' => [['own' => $ownFileReshared, 'received' => $received], ['received']],
			'share of other conversation before received share' => [['other' => $otherRoom, 'received' => $received], ['received']],
		];
	}

	/**
	 * Older shares without a userroom share are found by the target of the room share.
	 *
	 * @param array<string, array{owner: string, initiator: string, room: string}> $shares
	 * @param list<string> $expected
	 */
	#[DataProvider('dataGetSharedWithByPathWithoutUserRoomShare')]
	public function testGetSharedWithByPathWithoutUserRoomShare(array $shares, array $expected): void {
		$this->manager->method('getRoomTokensWithAttachmentsForUser')
			->with('alice')
			->willReturn(['token123']);
		$this->config->method('getAttachmentFolder')
			->with('alice')
			->willReturn('/Talk');

		$shareIds = [];
		foreach ($shares as $key => $share) {
			$shareIds[$key] = $this->createShare(
				IShare::TYPE_ROOM,
				$share['room'],
				owner: $share['owner'],
				initiator: $share['initiator'],
				fileSource: $this->createFile('files/' . $key . '.xml'),
				target: RoomShareProvider::TALK_FOLDER_PLACEHOLDER . '/settings.xml',
			);
		}
		$expectedIds = array_map(fn (string $key): string => (string)$shareIds[$key], $expected);

		$shares = iterator_to_array($this->provider->getSharedWithByPath('alice', IShare::TYPE_ROOM, '/Talk/settings.xml', false, -1, 0), false);
		$this->assertSame($expectedIds, array_map(fn (IShare $share): string => $share->getId(), $shares));
		foreach ($shares as $share) {
			$this->assertSame('/Talk/settings.xml', $share->getTarget());
		}
	}
}
