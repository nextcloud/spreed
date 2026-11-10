<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Controller\ChatController;
use OCA\Talk\Controller\ReactionController;
use OCA\Talk\Controller\ThreadController;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Thread;
use OCA\Talk\Notification\Notifier;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Search\MessageSearch;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Http;
use OCP\Comments\IComment;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IManager as INotificationManager;
use OCP\Search\ISearchQuery;
use OCP\Server;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

/**
 * A participant whose history starts later (history_since of the attendee)
 * sees nothing from before in any read path; the other participants see all.
 */
#[Group('DB')]
class HistoryVisibilityTest extends TestCase {
	private const OLD = '2024-01-02T09:00:00Z';
	private const HISTORY_SINCE = '2024-01-04T00:00:00Z';
	private const NEW = '2024-01-05T09:00:00Z';

	private IDBConnection $db;
	private string $prefix;
	/** @var list<string> */
	private array $users = [];
	/** @var list<Room> */
	private array $rooms = [];
	private string $alice;
	private string $bob;
	private string $dave;
	private Room $room;
	/** @var array<string, int> message ID by name */
	private array $ids = [];
	private int $oldFileId;
	private int $newFileId;
	/** @var array<int, int> share ID by file message ID */
	private array $shareIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->prefix = 'hv' . bin2hex(random_bytes(3));
		$this->alice = $this->createUser('alice');
		$this->bob = $this->createUser('bob');
		$this->dave = $this->createUser('dave');
		$this->room = $this->createRoom('History start', [$this->bob, $this->dave]);

		$folder = Server::get(IRootFolder::class)->getUserFolder($this->alice);
		$this->oldFileId = $folder->newFile('old-report.txt', 'old')->getId();
		$this->newFileId = $folder->newFile('new-report.txt', 'new')->getId();

		$chatManager = Server::get(ChatManager::class);
		$alice = $this->participant($this->room, $this->alice);

		$oldPost = $this->send($alice, 'Old secret plan', threadTitle: 'Old thread');
		$this->ids['old-post'] = (int)$oldPost->getId();
		$this->ids['old-reaction'] = (int)Server::get(ReactionManager::class)->addReactionMessage($this->room, Attendee::ACTOR_USERS, $this->dave, $this->dave, $this->ids['old-post'], '👍')->getId();
		$this->ids['old-file'] = $this->shareFile($this->oldFileId);
		$this->ids['old-note'] = (int)$this->send($alice, 'Old secret note')->getId();
		foreach (['old-post', 'old-reaction', 'old-file', 'old-note'] as $name) {
			$this->setMessageTime($this->ids[$name], self::OLD);
		}

		$this->ids['new-reply'] = (int)$this->send($alice, 'New visible reply', $chatManager->getComment($this->room, (string)$this->ids['old-post']))->getId();
		$this->ids['new-post'] = (int)$this->send($alice, 'New visible post', threadTitle: 'New thread')->getId();
		$this->ids['new-file'] = $this->shareFile($this->newFileId);
		foreach (['new-reply', 'new-post', 'new-file'] as $name) {
			$this->setMessageTime($this->ids[$name], self::NEW);
		}

		$this->setHistorySince($this->room, $this->bob, self::HISTORY_SINCE);
		$this->room = $this->freshRoom($this->room);
	}

	protected function tearDown(): void {
		$roomService = Server::get(RoomService::class);
		foreach ($this->rooms as $room) {
			try {
				$roomService->deleteRoom($this->freshRoom($room));
			} catch (\Throwable) {
			}
		}

		$userManager = Server::get(IUserManager::class);
		foreach ($this->users as $userId) {
			$userManager->get($userId)?->delete();
		}

		parent::tearDown();
	}

	private function createUser(string $name): string {
		$userId = $this->prefix . '-' . $name;
		Server::get(IUserManager::class)->createUser($userId, 'Talk-History-Test-' . $userId);
		$this->users[] = $userId;
		return $userId;
	}

	/**
	 * A group conversation of alice with the given users
	 *
	 * @param list<string> $userIds
	 */
	private function createRoom(string $name, array $userIds): Room {
		$room = Server::get(RoomService::class)->createConversation(Room::TYPE_GROUP, $name, Server::get(IUserManager::class)->get($this->alice));
		$this->rooms[] = $room;
		Server::get(ParticipantService::class)->addUsers($room, array_map(static fn (string $userId): array => [
			'actorType' => Attendee::ACTOR_USERS,
			'actorId' => $userId,
			'displayName' => $userId,
		], $userIds));
		return $this->freshRoom($room);
	}

	private function send(Participant $participant, string $text, ?IComment $replyTo = null, string $threadTitle = ''): IComment {
		$comment = Server::get(ChatManager::class)->sendMessage(
			$this->freshRoom($participant->getRoom()),
			$participant,
			Attendee::ACTOR_USERS,
			$participant->getAttendee()->getActorId(),
			$text,
			new \DateTime(),
			$replyTo,
			threadId: $threadTitle !== '' ? Thread::THREAD_CREATE : Thread::THREAD_NONE,
			threadTitle: $threadTitle,
		);
		if ($threadTitle !== '') {
			Server::get(ThreadService::class)->createThread($participant->getRoom(), (int)$comment->getId(), $threadTitle);
		}
		return $comment;
	}

	/**
	 * Shares alice's file into the conversation
	 *
	 * @return int the ID of the file message
	 */
	private function shareFile(int $fileId): int {
		$shareManager = Server::get(IShareManager::class);
		$share = $shareManager->newShare();
		$share->setNode(Server::get(IRootFolder::class)->getUserFolder($this->alice)->getFirstNodeById($fileId))
			->setShareType(IShare::TYPE_ROOM)
			->setSharedWith($this->room->getToken())
			->setSharedBy($this->alice)
			->setShareOwner($this->alice)
			->setPermissions(Constants::PERMISSION_READ);
		$share = $shareManager->createShare($share);

		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$this->room->getId())))
			->andWhere($query->expr()->like('message', $query->createNamedParameter('%"share":"' . $share->getId() . '"%')));
		$result = $query->executeQuery();
		$messageId = (int)$result->fetchOne();
		$result->closeCursor();
		$this->assertGreaterThan(0, $messageId, 'the share posted its file message');

		$this->shareIds[$messageId] = (int)$share->getId();
		return $messageId;
	}

	/**
	 * Moves a message, its attachment entry and its share to the given time
	 */
	private function setMessageTime(int $messageId, string $time): void {
		$dateTime = new \DateTime($time);

		$update = $this->db->getQueryBuilder();
		$update->update('comments')
			->set('creation_timestamp', $update->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($update->expr()->eq('id', $update->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$update = $this->db->getQueryBuilder();
		$update->update('talk_attachments')
			->set('message_time', $update->createNamedParameter($dateTime->getTimestamp(), IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('message_id', $update->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		if (isset($this->shareIds[$messageId])) {
			$update = $this->db->getQueryBuilder();
			$update->update('share')
				->set('stime', $update->createNamedParameter($dateTime->getTimestamp(), IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter($this->shareIds[$messageId], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
		}
	}

	private function setHistorySince(Room $room, string $userId, string $time): void {
		$attendee = $this->participant($room, $userId)->getAttendee();
		$attendee->setHistorySince((new \DateTimeImmutable($time))->getTimestamp());
		Server::get(AttendeeMapper::class)->update($attendee);
	}

	/**
	 * A participant read from the database, not from a cache
	 */
	private function participant(Room $room, string $userId): Participant {
		$room = Server::get(Manager::class)->getRoomForUserByToken($room->getToken(), $userId);
		return Server::get(ParticipantService::class)->getParticipant($room, $userId, false);
	}

	private function freshRoom(Room $room): Room {
		return Server::get(Manager::class)->getRoomById($room->getId());
	}

	private function chatController(string $userId): ChatController {
		$controller = \OC::$server->getRegisteredAppContainer('spreed')->resolve(ChatController::class);
		$controller->setRoom($this->room);
		$controller->setParticipant($this->participant($this->room, $userId));
		return $controller;
	}

	/**
	 * @return list<int> the IDs of the given messages in a chat response, in its order
	 */
	private function messageIds(array $messages, array $names): array {
		$ids = array_map(fn (string $name): int => $this->ids[$name], $names);
		return array_values(array_filter(array_column($messages, 'id'), static fn (int $id): bool => in_array($id, $ids, true)));
	}

	/**
	 * @return list<int>
	 */
	private function idsOf(string ...$names): array {
		return array_map(fn (string $name): int => $this->ids[$name], $names);
	}

	public function testChatHistory(): void {
		$all = array_keys($this->ids);

		$bobs = $this->chatController($this->bob)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$this->assertSame($this->idsOf('new-file', 'new-post', 'new-reply'), $this->messageIds($bobs, $all));

		$daves = $this->chatController($this->dave)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$this->assertSame($this->idsOf('new-file', 'new-post', 'new-reply', 'old-note', 'old-file', 'old-reaction', 'old-post'), $this->messageIds($daves, $all));

		$future = $this->chatController($this->bob)->receiveMessages(1, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$this->assertSame($this->idsOf('new-reply', 'new-post', 'new-file'), $this->messageIds($future, $all));
	}

	public function testMessageContext(): void {
		$bobs = $this->chatController($this->bob)->getMessageContext($this->ids['old-note'], 50)->getData() ?? [];
		$this->assertSame($this->idsOf('new-reply', 'new-post', 'new-file'), $this->messageIds($bobs, array_keys($this->ids)));

		$daves = $this->chatController($this->dave)->getMessageContext($this->ids['old-note'], 50)->getData() ?? [];
		$this->assertContains($this->ids['old-note'], array_column($daves, 'id'));
	}

	public function testParentAndThreadOfLaterReplies(): void {
		$bobs = $this->chatController($this->bob)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$reply = array_values(array_filter($bobs, fn (array $m): bool => $m['id'] === $this->ids['new-reply']))[0];
		$this->assertSame(['id' => $this->ids['old-post'], 'deleted' => true], $reply['parent']);
		$this->assertArrayNotHasKey('threadTitle', $reply);

		$daves = $this->chatController($this->dave)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$reply = array_values(array_filter($daves, fn (array $m): bool => $m['id'] === $this->ids['new-reply']))[0];
		$this->assertSame('Old secret plan', $reply['parent']['message']);
		$this->assertSame('Old thread', $reply['threadTitle']);
	}

	public function testSearch(): void {
		$search = Server::get(MessageSearch::class);
		$userManager = Server::get(IUserManager::class);

		$this->assertSame([], $search->search($userManager->get($this->bob), $this->query('secret'))->jsonSerialize()['entries']);
		$this->assertCount(2, $search->search($userManager->get($this->dave), $this->query('secret'))->jsonSerialize()['entries']);
		$this->assertCount(2, $search->search($userManager->get($this->bob), $this->query('visible'))->jsonSerialize()['entries']);
	}

	private function query(string $term): ISearchQuery {
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn($term);
		$query->method('getFilter')->willReturn(null);
		$query->method('getCursor')->willReturn(0);
		$query->method('getLimit')->willReturn(20);
		$query->method('getRoute')->willReturn('');
		$query->method('getRouteParameters')->willReturn([]);
		return $query;
	}

	public function testSharedItems(): void {
		$bobs = $this->chatController($this->bob)->getObjectsSharedInRoom(Attachment::TYPE_FILE)->getData();
		$this->assertSame([$this->ids['new-file']], array_map(intval(...), array_keys($bobs)));

		$overview = $this->chatController($this->bob)->getObjectsSharedInRoomOverview()->getData();
		$this->assertSame([$this->ids['new-file']], array_column($overview[Attachment::TYPE_FILE], 'id'));

		$daves = $this->chatController($this->dave)->getObjectsSharedInRoom(Attachment::TYPE_FILE)->getData();
		$this->assertCount(2, $daves);
	}

	public function testThreads(): void {
		$controller = \OC::$server->getRegisteredAppContainer('spreed')->resolve(ThreadController::class);
		$controller->setRoom($this->room);
		$controller->setParticipant($this->participant($this->room, $this->bob));

		$titles = array_map(static fn (array $thread): string => $thread['thread']['title'], $controller->getRecentActiveThreads()->getData());
		$this->assertSame(['New thread'], $titles);
		$this->assertSame(Http::STATUS_NOT_FOUND, $controller->getThread($this->ids['old-post'])->getStatus());
		$this->assertSame(Http::STATUS_OK, $controller->getThread($this->ids['new-post'])->getStatus());

		$controller->setParticipant($this->participant($this->room, $this->dave));
		$this->assertCount(2, $controller->getRecentActiveThreads()->getData());
		$this->assertSame(Http::STATUS_OK, $controller->getThread($this->ids['old-post'])->getStatus());
	}

	public function testReactions(): void {
		$controller = \OC::$server->getRegisteredAppContainer('spreed')->resolve(ReactionController::class);
		$controller->setRoom($this->room);

		$controller->setParticipant($this->participant($this->room, $this->bob));
		$this->assertSame(Http::STATUS_NOT_FOUND, $controller->getReactions($this->ids['old-post'], null)->getStatus());

		$controller->setParticipant($this->participant($this->room, $this->dave));
		$response = $controller->getReactions($this->ids['old-post'], null);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertArrayHasKey('👍', $response->getData());
	}

	public function testNotificationOfOldMessage(): void {
		$notification = Server::get(INotificationManager::class)->createNotification();
		$notification->setApp('spreed')
			->setUser($this->bob)
			->setObject('chat', $this->room->getToken())
			->setSubject('reaction', ['userType' => 'users', 'userId' => $this->alice, 'reaction' => '👍'])
			->setMessage('reaction', ['commentId' => (string)$this->ids['old-note']])
			->setDateTime(new \DateTime());

		$this->expectException(AlreadyProcessedException::class);
		Server::get(Notifier::class)->prepare($notification, 'en');
	}

	public function testNotificationOfVisibleMessage(): void {
		$notification = Server::get(INotificationManager::class)->createNotification();
		$notification->setApp('spreed')
			->setUser($this->dave)
			->setObject('chat', $this->room->getToken())
			->setSubject('reaction', ['userType' => 'users', 'userId' => $this->alice, 'reaction' => '👍'])
			->setMessage('reaction', ['commentId' => (string)$this->ids['old-note']])
			->setDateTime(new \DateTime());

		$prepared = Server::get(Notifier::class)->prepare($notification, 'en');
		$this->assertNotSame('', $prepared->getParsedSubject());
	}

	public function testRoomListLastMessage(): void {
		$formatter = Server::get(RoomFormatter::class);
		$bobs = $formatter->formatRoom('json', [], $this->room, $this->participant($this->room, $this->bob));
		$this->assertSame($this->ids['new-file'], $bobs['lastMessage']['id']);

		$archive = $this->createRoom('Archive', [$this->bob, $this->dave]);
		$message = $this->send($this->participant($archive, $this->alice), 'Old secret only');
		$this->setMessageTime((int)$message->getId(), self::OLD);
		Server::get(RoomService::class)->setLastMessage($this->freshRoom($archive), Server::get(ChatManager::class)->getComment($archive, $message->getId()));
		$this->setHistorySince($archive, $this->bob, self::HISTORY_SINCE);
		$archive = $this->freshRoom($archive);

		$bobs = $formatter->formatRoom('json', [], $archive, $this->participant($archive, $this->bob));
		$this->assertEmpty($bobs['lastMessage'] ?? []);

		$daves = $formatter->formatRoom('json', [], $archive, $this->participant($archive, $this->dave));
		$this->assertSame('Old secret only', $daves['lastMessage']['message']);
	}

	public function testFileShares(): void {
		$provider = Server::get(RoomShareProvider::class);

		$fileIds = static fn (array $shares): array => array_values(array_map(static fn (IShare $share): int => $share->getNodeId(), $shares));
		$this->assertSame([$this->newFileId], $fileIds($provider->getSharedWith($this->bob, IShare::TYPE_ROOM, null, -1, 0)));
		$this->assertEqualsCanonicalizing([$this->oldFileId, $this->newFileId], $fileIds($provider->getSharedWith($this->dave, IShare::TYPE_ROOM, null, -1, 0)));

		$oldShare = array_values(array_filter($provider->getSharedWith($this->dave, IShare::TYPE_ROOM, null, -1, 0), fn (IShare $share): bool => $share->getNodeId() === $this->oldFileId))[0];
		$users = array_map(static fn ($user): string => $user->getUID(), iterator_to_array($provider->getUsersForShare($oldShare)));
		$this->assertContains($this->dave, $users);
		$this->assertNotContains($this->bob, $users);

		$this->expectException(ShareNotFound::class);
		$provider->getShareById($oldShare->getId(), $this->bob);
	}
}
