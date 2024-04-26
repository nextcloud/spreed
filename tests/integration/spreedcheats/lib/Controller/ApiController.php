<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\QueryBuilder\IQueryBuilder;
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
		$delete->delete('talk_bots_conversation')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_bots_server')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_bridges')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_commands')
			->where($delete->expr()->neq('app', $delete->createNamedParameter('')))
			->andWhere($delete->expr()->neq('command', $delete->createNamedParameter('help')))
			->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_consent')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_internalsignaling')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_invitations')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_polls')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_poll_votes')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_proxy_messages')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_reminders')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_retry_ocm')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_rooms')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_sessions')->executeStatement();

		$delete = $this->db->getQueryBuilder();
		$delete->delete('share')
			->where($delete->expr()->orX(
				$delete->expr()->eq('share_type', $delete->createNamedParameter(IShare::TYPE_ROOM)),
				$delete->expr()->eq('share_type', $delete->createNamedParameter(11 /*RoomShareProvider::SHARE_TYPE_USERROOM*/))
			))
			->executeStatement();


		$delete = $this->db->getQueryBuilder();
		$delete->delete('preferences')
			->where($delete->expr()->in('configkey', $delete->createNamedParameter(['changelog', 'note_to_self'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($delete->expr()->eq('appid', $delete->createNamedParameter('spreed')))
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
}
