<?php

declare(strict_types=1);
/**
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\SpreedCheats\Controller;

use OCA\Talk\BackgroundJob\ApplyExpireDate;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Share\IShare;

class ApiController extends OCSController {

	/** @var IDBConnection */
	private $db;

	public function __construct(string $appName,
								IRequest $request,
								IDBConnection $db
	) {
		parent::__construct($appName, $request);
		$this->db = $db;
	}

	/**
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function resetSpreed(): DataResponse {
		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_attachments')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_attendees')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_bridges')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_commands')
			->where($delete->expr()->neq('app', $delete->createNamedParameter('')))
			->andWhere($delete->expr()->neq('command', $delete->createNamedParameter('help')))
			->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_internalsignaling')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_invitations')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_rooms')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_polls')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_poll_votes')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_sessions')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('share')
			->where($delete->expr()->orX(
				$delete->expr()->eq('share_type', $delete->createNamedParameter(IShare::TYPE_ROOM)),
				$delete->expr()->eq('share_type', $delete->createNamedParameter(11 /*RoomShareProvider::SHARE_TYPE_USERROOM*/))
			))
			->executeStatement();

		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('notifications')
				->where($delete->expr()->eq('app', $delete->createNamedParameter('spreed')))
				->executeStatement();
		} catch (\Throwable $e) {
			// Ignore
		}

		return new DataResponse();
	}

	/**
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function getExpireDateJob($token): DataResponse {
		$roomId = $this->getRoomIdByToken($token);
		if (!$roomId) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('jobs')
			->where(
				$query->expr()->andX(
					$query->expr()->eq('class', $query->createNamedParameter(ApplyExpireDate::class)),
					$query->expr()->eq('argument', $query->createNamedParameter(json_encode(['room_id' => (int) $roomId])))
				)
			);
		$result = $query->executeQuery();
		$job = $result->fetchOne();
		if ($job) {
			return new DataResponse(['id' => (int) $job]);
		}
		return new DataResponse([], Http::STATUS_NOT_FOUND);
	}

	private function getRoomIdByToken(string $token): ?string {
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('talk_rooms')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));
		$result = $query->executeQuery();
		return $result->fetchOne();
	}
}
