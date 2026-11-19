<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Service\AttachmentService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Index links of chat messages sent before link attachments existed.
 * Processes one chunk of messages older than `beforeId` from newest to oldest
 * and queues itself again for the next chunk, until the start of the history is reached.
 */
class IndexLinkAttachments extends QueuedJob {
	public const CHUNK_SIZE = 5000;

	public function __construct(
		ITimeFactory $time,
		private readonly IDBConnection $connection,
		private readonly AttachmentService $attachmentService,
		private readonly AttachmentMapper $attachmentMapper,
		private readonly IJobList $jobList,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{beforeId: int} $argument
	 */
	#[\Override]
	protected function run($argument): void {
		$beforeId = (int)$argument['beforeId'];

		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'object_id', 'actor_type', 'actor_id', 'message', 'creation_timestamp', 'expire_date')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('verb', $query->createNamedParameter(ChatManager::VERB_MESSAGE)))
			->andWhere($query->expr()->lt('id', $query->createNamedParameter($beforeId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'DESC')
			->setMaxResults(static::CHUNK_SIZE);

		$result = $query->executeQuery();
		$rows = $result->fetchAllAssociative();
		$result->closeCursor();

		if (empty($rows)) {
			return;
		}

		foreach ($rows as $row) {
			if (!$this->attachmentService->containsLink((string)$row['message'])) {
				continue;
			}

			$attachment = new Attachment();
			$attachment->setRoomId((int)$row['object_id']);
			$attachment->setActorType((string)$row['actor_type']);
			$attachment->setActorId((string)$row['actor_id']);
			$attachment->setMessageId((int)$row['id']);
			$attachment->setMessageTime((new \DateTime((string)$row['creation_timestamp']))->getTimestamp());
			$attachment->setExpireDate($row['expire_date'] ? new \DateTime((string)$row['expire_date']) : null);
			$attachment->setObjectType(Attachment::TYPE_LINK);
			$this->attachmentMapper->insert($attachment);
		}

		if (count($rows) === static::CHUNK_SIZE) {
			$lastRow = end($rows);
			$this->jobList->add(self::class, ['beforeId' => (int)$lastRow['id']]);
		}
	}
}
