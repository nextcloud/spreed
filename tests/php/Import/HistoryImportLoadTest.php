<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Import;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Import\IHistoryImporter;
use OCA\Talk\Import\ImportMessage;
use OCA\Talk\Import\ImportParticipant;
use OCA\Talk\Import\ImportReaction;
use OCA\Talk\Import\ImportResult;
use OCA\Talk\Manager;
use OCA\Talk\Model\ThreadMapper;
use OCA\Talk\Service\RoomFormatter;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;

/**
 * Imports a large history into one conversation and measures the write
 * rate and the read queries that grow with it (room list, unread count,
 * chat pages, search, threads).
 *
 * Only runs when TALK_IMPORT_LOAD_MESSAGES is set to the number of
 * messages; TALK_IMPORT_LOAD_REPORT names a JSON file for the numbers.
 */
#[Group('DB')]
class HistoryImportLoadTest extends ImportTestCase {
	private const USERS = 20;
	private const PLACEHOLDERS = 50;

	public function testLoad(): void {
		$total = (int)getenv('TALK_IMPORT_LOAD_MESSAGES');
		if ($total <= 0) {
			$this->markTestSkipped('Set TALK_IMPORT_LOAD_MESSAGES to run the load test');
		}

		$service = $this->createUser('service');
		$users = [];
		for ($i = 0; $i < self::USERS; $i++) {
			$users[] = $this->createUser('u' . $i);
		}
		$room = $this->createGroupRoom($service, 'load', 'Load test');

		$start = new \DateTimeImmutable('2020-01-01T00:00:00Z');
		$report = ['messages' => $total, 'database' => Server::get(\OCP\IConfig::class)->getSystemValueString('dbtype'), 'blocks' => []];
		$blockStart = microtime(true);
		$importStart = $blockStart;
		$written = 0;
		$blockWritten = 0;
		$thread = '';
		$sinceMessage = intdiv($total, 2);

		for ($offset = 0; $offset < $total; $offset += IHistoryImporter::MAX_ENTRIES) {
			$batch = [];
			for ($n = $offset; $n < min($total, $offset + IHistoryImporter::MAX_ENTRIES); $n++) {
				$batch[] = $this->loadMessage($n, $start, $users, $thread);
			}
			$results = $this->importer->importMessages(self::APP, $room, $batch);
			foreach ($results as $result) {
				if ($result->status !== ImportResult::STATUS_OK) {
					$this->fail($result->ref . ': ' . $result->errorCode . ' ' . $result->errorMessage);
				}
			}
			$written += count($results);

			if ($written % 50000 === 0 || $written === $total) {
				$now = microtime(true);
				$report['blocks'][] = [
					'written' => $written,
					'rate' => round(($written - $blockWritten) / max($now - $blockStart, 0.001)),
					'memory_mb' => round(memory_get_usage(true) / 1048576),
				];
				fwrite(STDERR, sprintf("%d messages, %d/s, %d MB\n", $written, end($report['blocks'])['rate'], end($report['blocks'])['memory_mb']));
				$blockStart = $now;
				$blockWritten = $written;
			}
		}
		$report['import_seconds'] = round(microtime(true) - $importStart, 1);
		$report['rate'] = round($total / $report['import_seconds']);

		$report['finish_ms'] = $this->measureOnce(fn () => $this->importer->finish(self::APP, $room));

		$sinceTime = $start->modify('+' . $sinceMessage . ' minutes');
		$participants = [];
		foreach ($users as $i => $userId) {
			$participants[] = new ImportParticipant($userId, historySince: $i === 0 ? $sinceTime : null);
		}
		$report['set_participants_ms'] = $this->measureOnce(fn () => $this->importer->setParticipants(self::APP, $room, $participants));

		$room = $this->freshRoom($room);
		$chatManager = Server::get(ChatManager::class);
		$formatter = Server::get(RoomFormatter::class);
		$manager = Server::get(Manager::class);
		$limited = $this->participant($room, $users[0]);
		$full = $this->participant($room, $users[1]);
		$middle = $this->refsMessageId($room, 'load-' . $sinceMessage);

		$report['queries_ms'] = [
			'room_list' => $this->measure(fn () => $manager->getRoomsForUser($users[1], [], true)),
			'room_format' => $this->measure(fn () => $formatter->formatRoom('json', [], $room, $full)),
			'room_format_history_limited' => $this->measure(fn () => $formatter->formatRoom('json', [], $room, $limited)),
			'unread_count_half' => $this->measure(fn () => $chatManager->getUnreadCount($room, $middle)),
			'chat_newest_page' => $this->measure(fn () => $chatManager->getHistory($room, 0, 100, false)),
			'chat_page_in_the_middle' => $this->measure(fn () => $chatManager->getHistory($room, $middle, 100, false)),
			'chat_oldest_page_history_limited' => $this->measure(fn () => $chatManager->getHistory($room, $middle, 100, false, notBefore: $limited->getHistorySince())),
			'search_rare_word' => $this->measure(fn () => $chatManager->searchForObjectsWithFilters('needle', [(string)$room->getId()], [ChatManager::VERB_MESSAGE], null, null, null, null, 0, 20)),
			'search_rare_word_history_limited' => $this->measure(fn () => $chatManager->searchForObjectsWithFilters('needle', [(string)$room->getId()], [ChatManager::VERB_MESSAGE], null, null, null, null, 0, 20, [(string)$room->getId() => $sinceTime])),
			'threads_recent' => $this->measure(fn () => Server::get(ThreadMapper::class)->getRecentByRoomId($room->getId(), 50)),
			'threads_recent_history_limited' => $this->measure(fn () => Server::get(ThreadMapper::class)->getRecentByRoomId($room->getId(), 50, $sinceTime)),
		];

		$this->assertCount(100, $chatManager->getHistory($room, 0, 100, false));
		$this->assertSame(0, $chatManager->getUnreadCount($room, $full->getAttendee()->getLastReadMessage()));

		fwrite(STDERR, json_encode($report, JSON_PRETTY_PRINT) . "\n");
		$file = getenv('TALK_IMPORT_LOAD_REPORT');
		if (is_string($file) && $file !== '') {
			file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT));
		}

		$report['cleanup_ms'] = $this->measureOnce(fn () => $this->tearDownRooms());
		fwrite(STDERR, 'cleanup_ms: ' . $report['cleanup_ms'] . "\n");
	}

	/**
	 * A mix like a busy channel: one post in 20 starts a thread and the next
	 * four answer it, one in 10 has reactions, one in 25 is by a former
	 * member, one in 2000 is a membership message
	 *
	 * @param list<string> $users
	 */
	private function loadMessage(int $n, \DateTimeImmutable $start, array $users, string &$thread): ImportMessage {
		$created = $start->modify('+' . $n . ' minutes');
		$actor = $n % 25 === 0
			? $this->placeholder('load-ph-' . ($n % self::PLACEHOLDERS), 'Former member ' . ($n % self::PLACEHOLDERS))
			: $this->user($users[$n % count($users)]);
		$ref = 'load-' . $n;

		if ($n % 2000 === 1999) {
			return new ImportMessage($ref, $actor, $created, systemKind: ImportMessage::SYSTEM_USER_ADDED, members: [$this->user($users[($n + 1) % count($users)])]);
		}

		$reactions = [];
		if ($n % 10 === 3) {
			$reactions[] = new ImportReaction($this->user($users[($n + 1) % count($users)]), '👍', $created->modify('+30 seconds'));
			$reactions[] = new ImportReaction($this->user($users[($n + 2) % count($users)]), '🎉', $created->modify('+40 seconds'));
		}
		$text = 'Message ' . $n . ' about the project status, the next steps and who takes care of them'
			. ($n % 1000 === 7 ? ' needle' : '');

		if ($n % 20 === 0) {
			$thread = $ref;
			return new ImportMessage($ref, $actor, $created, $text, threadTitle: 'Topic ' . $n, reactions: $reactions);
		}
		if ($n % 20 <= 4 && $thread !== '') {
			return new ImportMessage($ref, $actor, $created, $text, $thread, reactions: $reactions);
		}
		return new ImportMessage($ref, $actor, $created, $text, reactions: $reactions);
	}

	private function refsMessageId(\OCA\Talk\Room $room, string $ref): int {
		return $this->importer->statMessages(self::APP, $room, [$ref], false)[0]->messageId;
	}

	/**
	 * @return float milliseconds of the fastest of three runs
	 */
	private function measure(callable $callable): float {
		$best = INF;
		for ($i = 0; $i < 3; $i++) {
			$start = hrtime(true);
			$callable();
			$best = min($best, (hrtime(true) - $start) / 1e6);
		}
		return round($best, 1);
	}

	private function measureOnce(callable $callable): float {
		$start = hrtime(true);
		$callable();
		return round((hrtime(true) - $start) / 1e6, 1);
	}

	private function tearDownRooms(): void {
		$roomService = Server::get(\OCA\Talk\Service\RoomService::class);
		$manager = Server::get(Manager::class);
		foreach ($this->rooms as $room) {
			$roomService->deleteRoom($manager->getRoomById($room->getId()));
		}
		$this->rooms = [];
	}
}
