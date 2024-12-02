<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\GuestManager;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Participant;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class GuestController extends AEnvironmentAwareOCSController {

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
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, null, array{}>
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
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		if (!$participant->isGuest()) {
			return new DataResponse(null, Http::STATUS_FORBIDDEN);
		}

		$this->guestManager->updateName($this->getRoom(), $participant, $displayName);

		return new DataResponse(null);
	}
}
