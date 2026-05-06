<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ConversationTag>
 */
class ConversationTagMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_conversation_tags', ConversationTag::class);
	}

	/**
	 * @return list<ConversationTag>
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	public function findById(string $id, string $userId): ConversationTag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}

	/**
	 * Clear a tag from all attendees' tag_ids JSON arrays when a tag is deleted
	 */
	public function clearTagFromAttendees(string $tagId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		// Find attendees that have this tag in their JSON array
		// Use quoted string match to avoid false positives (e.g. "1" matching "12")
		$qb->select('a.id', 'a.tag_ids')
			->from('talk_attendees', 'a')
			->where($qb->expr()->like('a.tag_ids', $qb->createNamedParameter('%"' . $tagId . '"%')))
			->andWhere($qb->expr()->eq('a.actor_type', $qb->createNamedParameter('users')))
			->andWhere($qb->expr()->eq('a.actor_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			/** @var list<string|int> $tagIds */
			$tagIds = json_decode($row['tag_ids'], true) ?? [];
			$tagIds = array_values(array_filter($tagIds, fn ($id) => (string)$id !== $tagId));

			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('talk_attendees')
				->set('tag_ids', $updateQb->createNamedParameter(
					empty($tagIds) ? null : json_encode($tagIds),
					empty($tagIds) ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR
				))
				->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$updateQb->executeStatement();
		}
		$result->closeCursor();
	}
}
