<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\BlockActorService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class BlockController extends AEnvironmentAwareController {
	/** @var BlockActorService */
	protected $blockActorService;
	/** @var IUserSession */
	protected $userSession;

	public function __construct(string $appName,
								IRequest $request,
								BlockActorService $blockActorService,
								IUserSession $userSession
								) {
		parent::__construct($appName, $request);
		$this->blockActorService = $blockActorService;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 */
	public function block(string $type = Attendee::ACTOR_USERS, string $blockedId): DataResponse {
		$this->blockActorService->block(Attendee::ACTOR_USERS, $this->userSession->getUser()->getUID(), $type, $blockedId);
		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 */
	public function unblock(string $type = Attendee::ACTOR_USERS, string $blockedId): DataResponse {
		$this->blockActorService->unblock(Attendee::ACTOR_USERS, $this->userSession->getUser()->getUID(), $type, $blockedId);
		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 */
	public function listBlocked(): DataResponse {
		$list = $this->blockActorService->listBlocked($this->userSession->getUser()->getUID());
		return new DataResponse(array_keys($list), Http::STATUS_OK);
	}
}
