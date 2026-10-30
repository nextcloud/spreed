<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Import;

use OCA\Talk\Controller\ChatController;
use OCA\Talk\Controller\ReactionController;
use OCA\Talk\Controller\ThreadController;
use OCA\Talk\Import\ImportParticipant;
use OCA\Talk\Import\ImportReaction;
use OCA\Talk\Import\ImportResult;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Notification\Notifier;
use OCA\Talk\Room;
use OCA\Talk\Search\MessageSearch;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IManager as INotificationManager;
use OCP\Search\ISearchQuery;
use OCP\Server;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\Group;

/**
 * A participant the import gives a later history start sees nothing of the
 * imported history from before in any read path, placeholder authors and
 * quiet mentions included; the other participants see all.
 */
#[Group('DB')]
class ImportedHistoryVisibilityTest extends ImportTestCase {
	private string $alice;
	private string $bob;
	private string $dave;
	private Room $room;
	/** @var array<string, int> message ID by ref */
	private array $ids = [];
	private int $oldFileId;
	private int $newFileId;

	protected function setUp(): void {
		parent::setUp();
		$service = $this->createUser('service');
		$this->alice = $this->createUser('alice');
		$this->bob = $this->createUser('bob');
		$this->dave = $this->createUser('dave');
		$this->room = $this->createGroupRoom($service);

		$folder = Server::get(IRootFolder::class)->getUserFolder($this->alice);
		$this->oldFileId = $folder->newFile('old-report.txt', 'old')->getId();
		$this->newFileId = $folder->newFile('new-report.txt', 'new')->getId();

		$former = $this->placeholder('ph-' . $this->prefix, 'Dana Departed');
		$results = $this->importer->importMessages(self::APP, $this->room, [
			$this->text('old-post', $former, '2024-01-02T09:00:00Z', 'Old secret plan', threadTitle: 'Old thread', reactions: [
				new ImportReaction($this->user($this->alice), '👍', new \DateTimeImmutable('2024-01-02T09:01:00Z')),
			]),
			$this->text('old-file', $this->user($this->alice), '2024-01-02T09:02:00Z', 'Old secret attachment', fileIds: [$this->oldFileId]),
			$this->text('old-mention', $this->user($this->alice), '2024-01-02T09:03:00Z', 'Old secret for @"' . $this->bob . '"'),
			$this->text('new-reply', $this->user($this->alice), '2024-01-05T09:00:00Z', 'New visible reply', 'old-post'),
			$this->text('new-post', $this->user($this->alice), '2024-01-05T09:01:00Z', 'New visible post', threadTitle: 'New thread'),
			$this->text('new-file', $this->user($this->alice), '2024-01-05T09:02:00Z', 'New visible attachment', fileIds: [$this->newFileId]),
		]);
		foreach ($results as $result) {
			$this->assertSame(ImportResult::STATUS_OK, $result->status, $result->ref . ': ' . $result->errorMessage);
			$this->ids[$result->ref] = $result->id;
		}

		$this->importer->setParticipants(self::APP, $this->room, [
			new ImportParticipant($this->alice),
			new ImportParticipant($this->bob, historySince: new \DateTimeImmutable('2024-01-04T00:00:00Z')),
			new ImportParticipant($this->dave),
		]);
		$this->importer->finish(self::APP, $this->room);
		$this->room = $this->freshRoom($this->room);
	}

	private function chatController(string $userId): ChatController {
		$controller = \OC::$server->getRegisteredAppContainer('spreed')->resolve(ChatController::class);
		$controller->setRoom($this->room);
		$controller->setParticipant($this->participant($this->room, $userId));
		return $controller;
	}

	/**
	 * @return list<string> the message texts of a chat response
	 */
	private function texts(array $messages): array {
		return array_values(array_map(static fn (array $message): string => $message['message'], $messages));
	}

	public function testChatHistory(): void {
		$bobs = $this->chatController($this->bob)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$this->assertSame(['New visible attachment', 'New visible post', 'New visible reply'], $this->texts($bobs));

		$alices = $this->chatController($this->alice)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$this->assertCount(6, array_filter($alices, static fn (array $m): bool => $m['systemMessage'] !== 'reaction'));
		$this->assertCount(1, array_filter($alices, static fn (array $m): bool => $m['systemMessage'] === 'reaction'));

		$future = $this->chatController($this->bob)->receiveMessages(1, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$this->assertSame(['New visible reply', 'New visible post', 'New visible attachment'], $this->texts($future));
	}

	public function testMessageContext(): void {
		$response = $this->chatController($this->bob)->getMessageContext($this->ids['old-mention'], 50);
		$texts = $this->texts($response->getData() ?? []);
		$this->assertNotContains('Old secret for {mention-user1}', $texts);
		$this->assertSame(['New visible reply', 'New visible post', 'New visible attachment'], $texts);
	}

	public function testParentAndThreadOfLaterReplies(): void {
		$bobs = $this->chatController($this->bob)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$reply = array_values(array_filter($bobs, fn (array $m): bool => $m['id'] === $this->ids['new-reply']))[0];
		$this->assertSame(['id' => $this->ids['old-post'], 'deleted' => true], $reply['parent']);
		$this->assertArrayNotHasKey('threadTitle', $reply);

		$alices = $this->chatController($this->alice)->receiveMessages(0, 100, 0, 0, 0, 0, 0, 1, 0)->getData();
		$reply = array_values(array_filter($alices, fn (array $m): bool => $m['id'] === $this->ids['new-reply']))[0];
		$this->assertSame('Old secret plan', $reply['parent']['message']);
		$this->assertSame('imported', $reply['parent']['actorType']);
		$this->assertSame('ph-' . $this->prefix, $reply['parent']['actorId']);
		$this->assertSame('Dana Departed', $reply['parent']['actorDisplayName']);
		$this->assertFalse($reply['parent']['isReplyable'], 'people without an account can not be answered');
		$this->assertSame('Old thread', $reply['threadTitle']);
	}

	public function testSearch(): void {
		$search = Server::get(MessageSearch::class);
		$userManager = Server::get(IUserManager::class);

		$this->assertSame([], $this->searchTitles($search->search($userManager->get($this->bob), $this->query('secret'))->jsonSerialize()['entries']));
		$this->assertCount(3, $search->search($userManager->get($this->dave), $this->query('secret'))->jsonSerialize()['entries']);
		$this->assertCount(3, $search->search($userManager->get($this->bob), $this->query('visible'))->jsonSerialize()['entries']);
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

	private function searchTitles(array $entries): array {
		return array_map(static fn ($entry): string => $entry->jsonSerialize()['subline'], $entries);
	}

	public function testSharedItems(): void {
		$bobs = $this->chatController($this->bob)->getObjectsSharedInRoom(Attachment::TYPE_FILE)->getData();
		$this->assertSame([(string)$this->ids['new-file']], array_map(strval(...), array_keys($bobs)));

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

	public function testMentionsAndNotifications(): void {
		$attendee = $this->participant($this->room, $this->bob)->getAttendee();
		$this->assertSame(0, $attendee->getLastMentionMessage());
		$this->assertSame(0, $attendee->getLastMentionDirect());
		if ($this->db->tableExists('notifications')) {
			$this->assertSame(0, $this->countRows('notifications', fn ($query) => $query->where($query->expr()->eq('user', $query->createNamedParameter($this->bob)))));
		}

		// A notification about an old message, e.g. a reaction to it, is dropped for bob
		$notifier = Server::get(Notifier::class);
		$notificationManager = Server::get(INotificationManager::class);
		$notification = $notificationManager->createNotification();
		$notification->setApp('spreed')
			->setUser($this->bob)
			->setObject('chat', $this->room->getToken())
			->setSubject('reaction', ['userType' => 'users', 'userId' => $this->alice, 'reaction' => '👍'])
			->setMessage('reaction', ['commentId' => (string)$this->ids['old-mention']])
			->setDateTime(new \DateTime());

		$this->expectException(AlreadyProcessedException::class);
		$notifier->prepare($notification, 'en');
	}

	public function testNotificationOfVisibleMessage(): void {
		$notification = Server::get(INotificationManager::class)->createNotification();
		$notification->setApp('spreed')
			->setUser($this->dave)
			->setObject('chat', $this->room->getToken())
			->setSubject('reaction', ['userType' => 'users', 'userId' => $this->alice, 'reaction' => '👍'])
			->setMessage('reaction', ['commentId' => (string)$this->ids['old-mention']])
			->setDateTime(new \DateTime());

		$prepared = Server::get(Notifier::class)->prepare($notification, 'en');
		$this->assertNotSame('', $prepared->getParsedSubject());
	}

	public function testRoomListLastMessage(): void {
		$service = $this->createUser('service2');
		$archive = $this->createGroupRoom($service, 'archive', 'Archive');
		$this->importer->importMessages(self::APP, $archive, [
			$this->text('only', $this->user($service), '2024-01-02T09:00:00Z', 'Old secret only'),
		]);
		$this->importer->setParticipants(self::APP, $archive, [
			new ImportParticipant($this->bob, historySince: new \DateTimeImmutable('2024-01-04T00:00:00Z')),
			new ImportParticipant($this->dave),
		]);
		$this->importer->finish(self::APP, $archive);
		$archive = $this->freshRoom($archive);

		$formatter = Server::get(RoomFormatter::class);
		$bobs = $formatter->formatRoom('json', [], $archive, $this->participant($archive, $this->bob));
		$this->assertEmpty($bobs['lastMessage'] ?? []);
		$this->assertSame(0, $bobs['unreadMessages']);

		$daves = $formatter->formatRoom('json', [], $archive, $this->participant($archive, $this->dave));
		$this->assertSame('Old secret only', $daves['lastMessage']['message']);

		$bobs = $formatter->formatRoom('json', [], $this->room, $this->participant($this->room, $this->bob));
		$this->assertSame($this->ids['new-file'], $bobs['lastMessage']['id']);
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

		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);
		$provider->getShareById($oldShare->getId(), $this->bob);
	}
}
