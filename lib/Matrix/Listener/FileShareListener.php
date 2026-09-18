<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Listener;

use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\MatrixSendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Model\Attendee;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;

/**
 * When a Nextcloud file is shared into a Matrix conversation (Talk's
 * `file_shared` system message), upload it to the homeserver and post it as
 * a Matrix attachment so Matrix members receive the file, not a Talk link.
 *
 * @template-implements IEventListener<SystemMessageSentEvent>
 */
class FileShareListener implements IEventListener {
	public function __construct(
		private readonly SendService $sendService,
		private readonly AccountService $accountService,
		private readonly EventMapMapper $eventMapMapper,
		private readonly IShareManager $shareManager,
		private readonly IRootFolder $rootFolder,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof SystemMessageSentEvent || !$event->getRoom()->isMatrixConversation()) {
			return;
		}
		$comment = $event->getComment();
		$data = json_decode($comment->getMessage(), true);
		if (!is_array($data) || ($data['message'] ?? '') !== 'file_shared') {
			return;
		}
		if ($comment->getActorType() !== Attendee::ACTOR_USERS) {
			return;
		}
		if ($this->eventMapMapper->findByCommentId((int)$comment->getId()) !== null) {
			return; // already mirrored (echo of a Matrix attachment)
		}
		$account = $this->accountService->getForUser($comment->getActorId());
		if ($account === null || !$account->isActive()) {
			return;
		}
		$file = $this->resolveFile($data['parameters'] ?? [], $comment->getActorId());
		if ($file === null) {
			$this->logger->info('Matrix: shared file for message ' . $comment->getId() . ' could not be resolved');
			return;
		}
		try {
			$this->sendService->sendFile($event->getRoom(), $account, $file, $comment);
		} catch (MatrixSendException $e) {
			$this->logger->warning('Matrix: uploading shared file failed: ' . $e->getError());
		} catch (\Throwable $e) {
			$this->logger->error('Matrix: uploading shared file crashed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	/** @param array<string, mixed> $parameters */
	private function resolveFile(array $parameters, string $userId): ?File {
		try {
			if (isset($parameters['share'])) {
				$share = $this->shareManager->getShareById('ocRoomShare:' . $parameters['share']);
				$node = $share->getNode();
			} elseif (isset($parameters['fileId'])) {
				$node = $this->rootFolder->getUserFolder($userId)->getFirstNodeById((int)$parameters['fileId']);
			} else {
				return null;
			}
		} catch (\Throwable) {
			return null;
		}
		return $node instanceof File ? $node : null;
	}
}
