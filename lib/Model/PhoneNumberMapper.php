<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method PhoneNumber mapRowToEntity(array $row)
 * @method PhoneNumber findEntity(IQueryBuilder $query)
 * @method list<PhoneNumber> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<PhoneNumber>
 */
class PhoneNumberMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_phone_numbers', PhoneNumber::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByPhoneNumber(string $phoneNumber): PhoneNumber {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('phone_number', $query->createNamedParameter($phoneNumber, IQueryBuilder::PARAM_STR)))
			->orderBy('id', 'ASC');

		return $this->findEntity($query);
	}

	/**
	 * @return list<PhoneNumber>
	 */
	public function findByUser(string $userId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('actor_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($query);
	}

	/**
	 * @return list<PhoneNumber>
	 */
	public function findByPhoneNumbers(array $phoneNumbers): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->in('phone_number', $query->createNamedParameter($phoneNumbers, IQueryBuilder::PARAM_STR_ARRAY)));

		return $this->findEntities($query);
	}

	public function deleteByPhoneNumber(string $phoneNumber): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('phone_number', $query->createNamedParameter($phoneNumber, IQueryBuilder::PARAM_STR)));

		$query->executeStatement();
	}

	public function deleteByUser(string $userId): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('actor_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		$query->executeStatement();
	}
}
