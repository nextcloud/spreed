<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Import;

use OCA\Talk\Import\IHistoryImporter;
use OCA\Talk\Import\ImportActor;
use OCA\Talk\Import\ImportMessage;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\Comments\Events\BeforeCommentUpdatedEvent;
use OCP\Comments\Events\CommentAddedEvent;
use OCP\Comments\Events\CommentDeletedEvent;
use OCP\Comments\Events\CommentUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use Test\TestCase;

/**
 * Real database, real services: the importer as the bridge gets it
 */
abstract class ImportTestCase extends TestCase {
	protected const APP = 'phpunit';

	protected IHistoryImporter $importer;
	protected IDBConnection $db;
	protected string $prefix;
	/** @var list<string> */
	protected array $users = [];
	/** @var list<Room> */
	protected array $rooms = [];
	/** @var list<string> event classes dispatched while recording */
	protected array $recordedEvents = [];
	/** @var array<class-string, callable> */
	private array $listeners = [];

	protected function setUp(): void {
		parent::setUp();
		$this->importer = Server::get(IHistoryImporter::class);
		$this->db = Server::get(IDBConnection::class);
		$this->prefix = 'ti' . bin2hex(random_bytes(3));
	}

	protected function tearDown(): void {
		$this->stopRecordingEvents();

		$roomService = Server::get(RoomService::class);
		$manager = Server::get(Manager::class);
		foreach ($this->rooms as $room) {
			try {
				$roomService->deleteRoom($manager->getRoomById($room->getId()));
			} catch (\Throwable) {
			}
		}

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_import_refs')
			->where($delete->expr()->eq('app', $delete->createNamedParameter(self::APP)));
		$delete->executeStatement();

		$userManager = Server::get(IUserManager::class);
		foreach ($this->users as $userId) {
			$userManager->get($userId)?->delete();
		}

		parent::tearDown();
	}

	protected function createUser(string $name): string {
		$userId = $this->prefix . '-' . $name;
		Server::get(IUserManager::class)->createUser($userId, 'Talk-Import-Test-' . $userId);
		$this->users[] = $userId;
		return $userId;
	}

	protected function createGroupRoom(string $owner, string $ref = 'room', string $name = 'Imported channel', bool $readOnly = false): Room {
		$room = $this->importer->ensureConversation(self::APP, $this->prefix . '/' . $ref, Room::TYPE_GROUP, $name, '',
			new \DateTimeImmutable('2024-01-01T08:00:00Z'), $readOnly, '', '', $owner);
		$this->rooms[] = $room;
		return $room;
	}

	protected function user(string $userId): ImportActor {
		return new ImportActor('users', $userId);
	}

	protected function placeholder(string $id, string $name): ImportActor {
		return new ImportActor('imported', $id, $name);
	}

	protected function text(string $ref, ImportActor $actor, string $time, string $text, string $parentRef = '', string $threadTitle = '', array $reactions = [], array $fileIds = []): ImportMessage {
		return new ImportMessage($ref, $actor, new \DateTimeImmutable($time), $text, $parentRef, $threadTitle, reactions: $reactions, fileIds: $fileIds);
	}

	/**
	 * A participant read from the database, not from a cache
	 */
	protected function participant(Room $room, string $userId): Participant {
		$room = Server::get(Manager::class)->getRoomForUserByToken($room->getToken(), $userId);
		return Server::get(ParticipantService::class)->getParticipant($room, $userId, false);
	}

	protected function freshRoom(Room $room): Room {
		return Server::get(Manager::class)->getRoomById($room->getId());
	}

	protected function countRows(string $table, ?callable $where = null): int {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from($table);
		if ($where !== null) {
			$where($query);
		}
		$result = $query->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	protected function countComments(Room $room): int {
		return $this->countRows('comments', static function ($query) use ($room): void {
			$query->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
				->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$room->getId())));
		});
	}

	/**
	 * Records every Talk event and the comment and share events of the
	 * server, which carry live chat to notifications, push, bots, webhooks,
	 * signaling, activity and federation
	 */
	protected function startRecordingEvents(): void {
		$classes = [
			CommentAddedEvent::class,
			CommentUpdatedEvent::class,
			BeforeCommentUpdatedEvent::class,
			CommentDeletedEvent::class,
			ShareCreatedEvent::class,
			BeforeShareCreatedEvent::class,
		];
		foreach (new \DirectoryIterator(__DIR__ . '/../../../lib/Events') as $file) {
			if ($file->isDot()) {
				continue;
			}
			$class = 'OCA\\Talk\\Events\\' . substr($file->getFilename(), 0, -4);
			if (!(new \ReflectionClass($class))->isAbstract()) {
				$classes[] = $class;
			}
		}

		$dispatcher = Server::get(IEventDispatcher::class);
		foreach ($classes as $class) {
			$listener = function (Event $event): void {
				$this->recordedEvents[] = $event::class;
			};
			$dispatcher->addListener($class, $listener);
			$this->listeners[$class] = $listener;
		}
	}

	protected function stopRecordingEvents(): void {
		$dispatcher = Server::get(IEventDispatcher::class);
		foreach ($this->listeners as $class => $listener) {
			$dispatcher->removeListener($class, $listener);
		}
		$this->listeners = [];
	}

	/**
	 * Rows of the tables live chat writes to besides the messages
	 *
	 * @return array<string, int>
	 */
	protected function countSideEffects(): array {
		$counts = [];
		foreach (['notifications', 'notifications_pushhash', 'activity', 'activity_mq', 'talk_internalsignaling', 'talk_retry_ocm', 'talk_proxy_messages', 'talk_sessions', 'talk_thread_attendees'] as $table) {
			if ($this->db->tableExists($table)) {
				$counts[$table] = $this->countRows($table);
			}
		}
		return $counts;
	}
}
