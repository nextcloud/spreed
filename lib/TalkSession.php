<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OCP\ISession;

class TalkSession {

	public function __construct(
		protected ISession $session,
	) {
	}

	/**
	 * @return string[]
	 */
	public function getAllActiveSessions(): array {
		$sessions = $this->getValues('spreed-session');
		return array_values($sessions);
	}

	public function getSessionForRoom(string $token): ?string {
		return $this->getValue('spreed-session', $token);
	}

	public function setSessionForRoom(string $token, string $sessionId): void {
		$this->setValue('spreed-session', $token, $sessionId);
	}

	public function removeSessionForRoom(string $token): void {
		$this->removeValue('spreed-session', $token);
	}

	public function getGuestActorIdForRoom(string $token): ?string {
		return $this->getValue('spreed-guest-id', $token);
	}

	public function setGuestActorIdForRoom(string $token, string $actorId): void {
		$this->setValue('spreed-guest-id', $token, $actorId);
	}

	public function removeGuestActorIdForRoom(string $token): void {
		$this->removeValue('spreed-guest-id', $token);
	}

	public function getAuthedEmailActorIdForRoom(string $token): ?string {
		return $this->getValue('spreed-authed-email', $token);
	}

	public function setAuthedEmailActorIdForRoom(string $token, string $actorId): void {
		$this->setValue('spreed-authed-email', $token, $actorId);
	}

	public function getFileShareTokenForRoom(string $roomToken): ?string {
		return $this->getValue('spreed-file-share-token', $roomToken);
	}

	public function setFileShareTokenForRoom(string $roomToken, string $shareToken): void {
		$this->setValue('spreed-file-share-token', $roomToken, $shareToken);
	}

	public function removeFileShareTokenForRoom(string $roomToken): void {
		$this->removeValue('spreed-file-share-token', $roomToken);
	}

	public function getPasswordForRoom(string $token): ?string {
		return $this->getValue('spreed-password', $token);
	}

	public function setPasswordForRoom(string $token, string $password): void {
		$this->setValue('spreed-password', $token, $password);
	}

	public function removePasswordForRoom(string $token): void {
		$this->removeValue('spreed-password', $token);
	}

	protected function getValues(string $key): array {
		$values = $this->session->get($key);
		if ($values === null) {
			return [];
		}

		$values = json_decode($values, true);
		if ($values === null) {
			return [];
		}

		return $values;
	}

	protected function getValue(string $key, string $token): ?string {
		$values = $this->getValues($key);
		return $values[$token] ?? null;
	}

	protected function setValue(string $key, string $token, string $value): void {
		$reopened = $this->session->reopen();

		$values = $this->getValues($key);
		$values[$token] = $value;
		$this->session->set($key, json_encode($values));


		if ($reopened) {
			$this->session->close();
		}
	}

	protected function removeValue(string $key, string $token): void {
		$reopened = $this->session->reopen();

		$values = $this->getValues($key);
		unset($values[$token]);
		$this->session->set($key, json_encode($values));

		if ($reopened) {
			$this->session->close();
		}
	}

	public function renewSessionId(): void {
		$this->session->regenerateId(true, true);
	}
}
