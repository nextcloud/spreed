<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Model\Attendee;
use OCP\Federation\ICloudIdManager;
use OCP\IRequest;

class Authenticator {
	protected bool $resolved = false;
	protected bool $isFederationRequest = false;
	protected bool $isAuthenticatedEmailGuest = false;
	protected string $actorType = '';
	protected string $actorId = '';
	protected string $accessToken = '';
	protected ?Room $room = null;
	protected ?Participant $participant = null;

	public function __construct(
		protected readonly IRequest $request,
		protected readonly ICloudIdManager $cloudIdManager,
		protected readonly TalkSession $talkSession,
	) {
	}

	protected function resolve(): void {
		if ($this->resolved) {
			return;
		}
		$this->resolved = true;

		if ((bool)$this->request->getHeader('x-nextcloud-federation')) {
			$authUser = urldecode($this->request->server['PHP_AUTH_USER'] ?? '');
			try {
				$cloudId = $this->cloudIdManager->resolveCloudId($authUser);
				$this->isFederationRequest = true;
				$this->actorType = Attendee::ACTOR_FEDERATED_USERS;
				$this->actorId = $cloudId->getId();
				$this->accessToken = $this->request->server['PHP_AUTH_PW'] ?? '';
				return;
			} catch (\InvalidArgumentException) {
				// Fall through to other authentication shapes
			}
		}

		$token = $this->request->getParam('token');
		if (is_string($token) && $token !== '') {
			$emailActorId = $this->talkSession->getAuthedEmailActorIdForRoom($token);
			if ($emailActorId !== null) {
				$this->isAuthenticatedEmailGuest = true;
				$this->actorType = Attendee::ACTOR_EMAILS;
				$this->actorId = $emailActorId;
			}
		}
	}

	public function isFederationRequest(): bool {
		$this->resolve();
		return $this->isFederationRequest;
	}

	public function isAuthenticatedEmailGuest(): bool {
		$this->resolve();
		return $this->isAuthenticatedEmailGuest;
	}

	public function isAuthenticatedRequest(): bool {
		$this->resolve();
		return $this->isFederationRequest || $this->isAuthenticatedEmailGuest;
	}

	public function getActorType(): string {
		$this->resolve();
		return $this->actorType;
	}

	public function getActorId(): string {
		$this->resolve();
		return $this->actorId;
	}

	/**
	 * Federation-only alias for {@see getActorId()}. Returns an empty string
	 * when the current request is not a federation request, even if another
	 * authenticated actor (e.g. email guest) is present.
	 */
	public function getCloudId(): string {
		return $this->isFederationRequest() ? $this->getActorId() : '';
	}

	public function getAccessToken(): string {
		$this->resolve();
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
