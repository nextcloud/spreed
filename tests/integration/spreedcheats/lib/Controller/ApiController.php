<?php
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

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Share\IShare;

class ApiController extends OCSController {

	/** @var IDBConnection */
	private $db;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IDBConnection $db
	 */
	public function __construct($appName, IRequest $request, IDBConnection $db) {
		parent::__construct($appName, $request);
		$this->db = $db;
	}

	/**
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function resetSpreed() {
		$query = $this->db->getQueryBuilder();
		$query->delete('talk_signaling')->execute();

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_rooms')->execute();

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_attendees')->execute();

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_sessions')->execute();

		$query = $this->db->getQueryBuilder();
		$query->delete('share')
			->where($query->expr()->orX(
				$query->expr()->eq('share_type', $query->createNamedParameter(IShare::TYPE_ROOM)),
				$query->expr()->eq('share_type', $query->createNamedParameter(11 /*RoomShareProvider::SHARE_TYPE_USERROOM*/))
			))
			->execute();

		return new DataResponse();
	}
}
