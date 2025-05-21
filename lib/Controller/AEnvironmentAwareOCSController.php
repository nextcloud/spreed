<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OC\AppFramework\Http\Dispatcher;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\OCSController;

abstract class AEnvironmentAwareOCSController extends OCSController {
	protected int $apiVersion = 1;
	protected ?Room $room = null;
	protected ?Participant $participant = null;
	protected ?Invitation $invitation = null;

	public function setAPIVersion(int $apiVersion): void {
		$this->apiVersion = $apiVersion;
	}

	public function getAPIVersion(): int {
		return $this->apiVersion;
	}

	public function setRoom(Room $room): void {
		$this->room = $room;
	}

	public function getRoom(): ?Room {
		return $this->room;
	}

	public function setParticipant(Participant $participant): void {
		$this->participant = $participant;
	}

	public function getParticipant(): ?Participant {
		return $this->participant;
	}

	public function setInvitation(Invitation $invitation): void {
		$this->invitation = $invitation;
	}

	public function getInvitation(): ?Invitation {
		return $this->invitation;
	}

	/**
	 * Following the logic of {@see Dispatcher::executeController}
	 * @return string Either 'json' or 'xml'
	 * @psalm-return 'json'|'xml'
	 */
	public function getResponseFormat(): string {
		// get format from the url format or request format parameter
		$format = $this->request->getParam('format');

		// if none is given try the first Accept header
		if ($format === null) {
			$headers = $this->request->getHeader('accept');
			/**
			 * Default value of
			 * @see OCSController::buildResponse()
			 */
			$format = $this->getResponderByHTTPHeader($headers, 'xml');
		}

		return $format;
	}
}
