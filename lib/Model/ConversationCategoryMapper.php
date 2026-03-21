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
 * @template-extends QBMapper<ConversationCategory>
 */
class ConversationCategoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_conversation_categories', ConversationCategory::class);
	}

	/**
	 * @return ConversationCategory[]
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	public function findById(int $id, string $userId): ConversationCategory {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}

	public function getMaxSortOrder(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sort_order'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();

		return (int)$max;
	}

	/**
	 * Clear a category from all attendees' category_ids JSON arrays when a category is deleted
	 */
	public function clearCategoryFromAttendees(int|string $categoryId, string $userId): void {
		$categoryIdStr = (string)$categoryId;
		$qb = $this->db->getQueryBuilder();
		// Find attendees that have this category in their JSON array
		// Use quoted string match to avoid false positives (e.g. "1" matching "12")
		$qb->select('a.id', 'a.category_ids')
			->from('talk_attendees', 'a')
			->where($qb->expr()->like('a.category_ids', $qb->createNamedParameter('%"' . $categoryIdStr . '"%')))
			->andWhere($qb->expr()->eq('a.actor_type', $qb->createNamedParameter('users')))
			->andWhere($qb->expr()->eq('a.actor_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$categoryIds = json_decode($row['category_ids'], true) ?? [];
			$categoryIds = array_values(array_filter($categoryIds, fn ($id) => (string)$id !== $categoryIdStr));

			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('talk_attendees')
				->set('category_ids', $updateQb->createNamedParameter(
					empty($categoryIds) ? null : json_encode($categoryIds),
					empty($categoryIds) ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR
				))
				->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$updateQb->executeStatement();
		}
		$result->closeCursor();
	}
}
