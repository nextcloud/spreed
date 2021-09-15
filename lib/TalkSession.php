<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk;

use OCP\ISession;

class TalkSession {

	/** @var ISession */
	protected $session;

	public function __construct(ISession $session) {
		$this->session = $session;
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

		if (!isset($values[$token])) {
			return null;
		}
		return $values[$token];
	}

	protected function setValue(string $key, string $token, string $value): void {
		$values = $this->session->get($key);
		if ($values === null) {
			$values = [];
		} else {
			$values = json_decode($values, true);
			if ($values === null) {
				$values = [];
			}
		}


		$values[$token] = $value;
		$this->session->set($key, json_encode($values));
	}

	protected function removeValue(string $key, string $token): void {
		$values = $this->session->get($key);
		if ($values === null) {
			$values = [];
		} else {
			$values = json_decode($values, true);
			if ($values === null) {
				$values = [];
			} else {
				unset($values[$token]);
			}
		}

		$this->session->set($key, json_encode($values));
	}

	public function renewSessionId(): void {
		$this->session->regenerateId(true, true);
	}
}
