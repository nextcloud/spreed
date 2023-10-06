<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

use OCA\Talk\GuestManager;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Participant;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class GuestController extends AEnvironmentAwareController {

	public function __construct(
		string $appName,
		IRequest $request,
		private GuestManager $guestManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Set the display name as a guest
	 *
	 * @param string $displayName New display name
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Display name updated successfully
	 * 403: Not a guest
	 * 404: Not a participant
	 */
	#[PublicPage]
	#[RequireParticipant]
	public function setDisplayName(string $displayName): DataResponse {
		$participant = $this->getParticipant();
		if (!$participant instanceof Participant) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$participant->isGuest()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->guestManager->updateName($this->getRoom(), $participant, $displayName);

		return new DataResponse();
	}
}
