<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Federation\ICloudIdManager;
use OCP\IRequest;

class Authenticator {
	protected ?bool $isFederationRequest = null;
	protected ?string $federationCloudId = null;
	protected ?string $accessToken = null;
	protected ?Room $room = null;
	protected ?Participant $participant = null;

	public function __construct(
		protected IRequest $request,
		protected ICloudIdManager $cloudIdManager,
	) {
	}

	protected function readHeaders(): void {
		$this->isFederationRequest = (bool)$this->request->getHeader('x-nextcloud-federation');
		if (!$this->isFederationRequest) {
			$this->federationCloudId = '';
			$this->accessToken = '';
			return;
		}

		$authUser = $this->request->server['PHP_AUTH_USER'] ?? '';
		$authUser = urldecode($authUser);

		try {
			$cloudId = $this->cloudIdManager->resolveCloudId($authUser);
			$this->federationCloudId = $cloudId->getId();
			$this->accessToken = $this->request->server['PHP_AUTH_PW'] ?? '';
		} catch (\InvalidArgumentException) {
			$this->isFederationRequest = false;
			$this->federationCloudId = '';
			$this->accessToken = '';
		}
	}

	public function isFederationRequest(): bool {
		if ($this->isFederationRequest === null) {
			$this->readHeaders();

			if ($this->isFederationRequest === null) {
				return false;
			}
		}

		return $this->isFederationRequest;
	}

	public function getCloudId(): string {
		if ($this->federationCloudId === null) {
			$this->readHeaders();

			if ($this->federationCloudId === null) {
				return '';
			}
		}

		return $this->federationCloudId;
	}

	public function getAccessToken(): string {
		if ($this->accessToken === null) {
			$this->readHeaders();

			if ($this->accessToken === null) {
				return '';
			}
		}

		return $this->accessToken;
	}

	public function authenticated(Room $room, Participant $participant): void {
		$this->room = $room;
		$this->participant = $participant;
	}

	/**
	 * @throws RoomNotFoundException
	 */
	public function getRoom(): Room {
		if ($this->room === null) {
			throw new RoomNotFoundException();
		}
		return $this->room;
	}

	/**
	 * @throws ParticipantNotFoundException
	 */
	public function getParticipant(): Participant {
		if ($this->participant === null) {
			throw new ParticipantNotFoundException();
		}
		return $this->participant;

	}
}
