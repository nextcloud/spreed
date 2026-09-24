<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use Nextcloud\Matrix\Model\Event;
use Nextcloud\Matrix\Model\InvitedRoom;
use Nextcloud\Matrix\Model\JoinedRoom;
use Nextcloud\Matrix\Model\LeftRoom;
use Nextcloud\Matrix\Model\Member;
use Nextcloud\Matrix\Model\RoomState;
use Nextcloud\Matrix\Model\SyncBatch;
use OCA\Talk\Matrix\Mapping\EventMapper;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Service\InvitationService;
use OCA\Talk\Matrix\Service\NotificationLevelService;
use OCA\Talk\Room;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

/**
 * Consumes {@see SyncBatch}es (from the poller today, from an appservice or
 * HPB worker later) and applies them to Talk.
 */
class IngestionPipeline {
	public function __construct(
		private readonly RoomStateApplier $stateApplier,
		private readonly EventMapper $eventMapper,
		private readonly InvitationService $invitations,
		private readonly NotificationLevelService $notificationLevels,
		private readonly AccountMapper $accountMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly IJobList $jobList,
		private readonly \OCA\Talk\Matrix\Service\CryptoService $cryptoService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{rooms: int, messages: int, invites: int, left: int, failed: int}
	 */
	public function process(Account $account, Homeserver $homeserver, SyncBatch $batch, bool $initial): array {
		$stats = ['rooms' => 0, 'messages' => 0, 'invites' => 0, 'left' => 0, 'failed' => 0];
		$directRooms = $batch->getDirectRooms();
		$pushRules = $batch->getAccountData('m.push_rules');

		// Keys first: to-device room keys may unlock events in this very batch
		$newSessions = [];
		try {
			$newSessions = $this->cryptoService->processSync($account, $homeserver, $batch);
		} catch (\Throwable $e) {
			$this->logger->error('Matrix crypto processing failed: ' . $e->getMessage(), ['exception' => $e]);
		}

		foreach ($batch->invited as $roomId => $invite) {
			try {
				if ($this->invitations->handleInvite($account, $homeserver, $invite)) {
					$stats['invites']++;
				}
			} catch (\Throwable $e) {
				$stats['failed']++;
				$this->logger->error('Matrix invite handling failed for ' . $roomId . ': ' . $e->getMessage(), ['exception' => $e]);
			}
		}

		foreach ($batch->joined as $roomId => $joined) {
			try {
				$stats['messages'] += $this->processJoined($account, $homeserver, $joined, isset($directRooms[$roomId]), $pushRules, $initial);
				$stats['rooms']++;
			} catch (\Throwable $e) {
				$stats['failed']++;
				$this->logger->error('Matrix room sync failed for ' . $roomId . ': ' . $e->getMessage(), ['exception' => $e]);
			}
		}

		foreach ($newSessions as $newSession) {
			$matrixRoom = $this->stateApplier->findMatrixRoom($newSession['roomId']);
			$room = $matrixRoom !== null ? $this->stateApplier->findTalkRoom($matrixRoom) : null;
			if ($room !== null) {
				$stats['messages'] += $this->eventMapper->retryUndecryptable($room, $matrixRoom, $account, $newSession['sessionId']);
			}
		}

		foreach ($batch->left as $roomId => $left) {
			try {
				$this->processLeft($account, $left);
				$stats['left']++;
			} catch (\Throwable $e) {
				$stats['failed']++;
				$this->logger->error('Matrix leave handling failed for ' . $roomId . ': ' . $e->getMessage(), ['exception' => $e]);
			}
		}

		return $stats;
	}

	private function processJoined(Account $account, Homeserver $homeserver, JoinedRoom $joined, bool $isDirect, ?array $pushRules, bool $initial): int {
		$existing = $this->stateApplier->findMatrixRoom($joined->roomId);
		$state = $this->buildState($joined->roomId, $existing, $joined->getStateEvents());
		if ($state->isSpace()) {
			return 0; // Spaces are not conversations
		}
		if ($existing === null && $state->getMembership($account->getMxid()) !== Member::JOIN && $joined->state === [] && $joined->timeline === []) {
			return 0; // Nothing to work with
		}

		[$matrixRoom, $room, $created] = $this->stateApplier->apply(
			$account,
			$homeserver,
			$state,
			$joined->getStateEvents(),
			$joined->getHeroes(),
			$joined->getJoinedMemberCount(),
			$joined->getInvitedMemberCount(),
			$isDirect,
		);

		// The invite (if any) is fulfilled now
		$this->invitations->markAccepted($account, $joined->roomId, $room);

		if ($created || $matrixRoom->getPrevBatch() === null) {
			$matrixRoom->setPrevBatch($joined->prevBatch);
			$matrixRoom->setBackfillDone(false);
			$this->stateApplier->updateMatrixRoom($matrixRoom);
			if ($joined->prevBatch !== null) {
				$this->jobList->add(\OCA\Talk\Matrix\BackgroundJob\MatrixBackfill::class, ['accountId' => $account->getId(), 'matrixRoomId' => $joined->roomId]);
			}
		} elseif ($joined->limited && $joined->prevBatch !== null) {
			// Gap between what we know and this batch: fill it in the background
			$this->jobList->add(\OCA\Talk\Matrix\BackgroundJob\MatrixBackfill::class, ['accountId' => $account->getId(), 'matrixRoomId' => $joined->roomId, 'from' => $joined->prevBatch, 'gap' => true]);
		}

		$this->notificationLevels->applyInitialLevel($room, $account, $matrixRoom, $pushRules, $created);

		$accounts = $this->accountMapper->getByMxids(array_unique(array_map(static fn (Event $e) => $e->sender, $joined->timeline)));
		$messages = 0;
		// Messages of the initial sync are older history: no notifications for those
		$silent = $initial || $created;
		foreach ($joined->timeline as $event) {
			if ($event->isState()) {
				continue;
			}
			$messages += $this->eventMapper->ingest($room, $matrixRoom, $event, $accounts, $silent, $this->cryptoService->isEnabledFor($homeserver) ? $account : null);
		}

		$this->applyReceipts($room, $matrixRoom, $joined->ephemeral, $accounts);
		return $messages;
	}

	private function processLeft(Account $account, LeftRoom $left): void {
		$matrixRoom = $this->stateApplier->findMatrixRoom($left->roomId);
		$this->invitations->markRejected($account, $left->roomId);
		if ($matrixRoom === null) {
			return;
		}
		$membershipEvent = $left->getOwnMembershipEvent($account->getMxid());
		$membership = (string)($membershipEvent?->content['membership'] ?? Member::LEAVE);

		$member = null;
		try {
			$member = $this->memberMapper->get($left->roomId, $account->getMxid());
			$member->setMembership($membership);
			$this->memberMapper->update($member);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
		}

		$room = $this->stateApplier->findTalkRoom($matrixRoom);
		if ($room !== null) {
			$participant = $this->stateApplier->getParticipantForAccount($room, $account);
			if ($participant !== null) {
				$this->stateApplier->removeParticipant($room, $participant, $membership);
			}
		}

		if (!$this->stateApplier->hasLinkedMembers($matrixRoom, $account->getId())) {
			$this->stateApplier->deleteConversation($matrixRoom);
		}
	}

	/**
	 * Existing DB state (members + power levels + basics) as the baseline so a
	 * lazy-loaded incremental sync does not "forget" members.
	 * @param list<Event> $events
	 */
	private function buildState(string $roomId, ?MatrixRoom $existing, array $events): RoomState {
		$state = new RoomState($roomId);
		if ($existing !== null) {
			$this->stateApplier->seedState($state, $existing);
		}
		$state->applyAll($events);
		return $state;
	}

	/**
	 * m.receipt → read markers of Matrix-only and other linked members.
	 * @param list<Event> $ephemeral
	 * @param array<string, Account> $accounts
	 */
	private function applyReceipts(Room $room, MatrixRoom $matrixRoom, array $ephemeral, array $accounts): void {
		foreach ($ephemeral as $event) {
			if ($event->type !== 'm.receipt') {
				continue;
			}
			foreach ($event->content as $eventId => $receipts) {
				if (!is_array($receipts) || !is_array($receipts['m.read'] ?? null)) {
					continue;
				}
				foreach (array_keys($receipts['m.read']) as $mxid) {
					$this->stateApplier->applyReadReceipt($room, $matrixRoom, (string)$mxid, (string)$eventId, $accounts[$mxid] ?? null);
				}
			}
		}
	}
}
