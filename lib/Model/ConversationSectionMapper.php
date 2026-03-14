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
 * @template-extends QBMapper<ConversationSection>
 */
class ConversationSectionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_conversation_sections', ConversationSection::class);
	}

	/**
	 * @return ConversationSection[]
	 */
	public function findByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	public function findById(int $id): ConversationSection {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

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
	 * Clear section_id from all attendees when a section is deleted
	 */
	public function clearSectionFromAttendees(int $sectionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_attendees')
			->set('section_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('section_id', $qb->createNamedParameter($sectionId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}
}
