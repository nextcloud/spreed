<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ThreadAttendee findEntity(IQueryBuilder $query)
 * @method list<ThreadAttendee> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<ThreadAttendee>
 */
class ThreadAttendeeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_thread_attendees', ThreadAttendee::class);
	}
}
