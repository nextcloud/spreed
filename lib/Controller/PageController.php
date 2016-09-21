<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Spreed\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IDBConnection;
use OCP\IRequest;

class PageController extends Controller {
	/** @var string */
	private $userId;
	/** @var IDBConnection */
	private $dbConnection;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $UserId
	 * @param IDBConnection $dbConnection
	 */
	public function __construct($appName,
								IRequest $request,
								$UserId,
								IDBConnection $dbConnection) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->dbConnection = $dbConnection;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		// If the page is newly loaded, remove all sessions and messages that the
		// current user has.
		// FIXME: Move to CSRF protected controller that is called via XHR
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('spreedme_messages')
			->where($qb->expr()->eq('recipient', $qb->createNamedParameter($this->userId)))
			->execute();
		$qb->delete('spreedme_room_participants')
			->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->execute();

		$params = [
			'sessionId' => $this->userId,
		];
		$response = new TemplateResponse($this->appName, 'index', $params);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedConnectDomain('*');
		$csp->addAllowedMediaDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

}
