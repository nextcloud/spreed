<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Adapter;

use Nextcloud\Matrix\Crypto\CrossSigningKeys;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\CryptoStoreInterface;
use Nextcloud\Matrix\Crypto\DeviceKeys;
use Nextcloud\Matrix\Crypto\Trust;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ICrypto;

/**
 * Database-backed crypto store for one linked account. Every pickle and
 * secret is encrypted with the instance secret (ICrypto) before it is stored.
 */
class CryptoStore implements CryptoStoreInterface {
	public function __construct(
		private readonly IDBConnection $db,
		private readonly ICrypto $crypto,
		private readonly AccountMapper $accountMapper,
		private readonly ITimeFactory $timeFactory,
		private Account $account,
	) {
	}

	private function accountId(): int {
		return $this->account->getId();
	}

	private function enc(string $value): string {
		return $this->crypto->encrypt($value);
	}

	private function dec(?string $value): ?string {
		if ($value === null || $value === '') {
			return null;
		}
		return $this->crypto->decrypt($value);
	}

	public function loadAccount(): ?string {
		return $this->dec($this->account->getOlmAccount());
	}

	public function saveAccount(string $pickle): void {
		$this->account->setOlmAccount($this->enc($pickle));
		$this->account = $this->accountMapper->update($this->account);
	}

	public function loadOlmSessions(string $theirCurve25519): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('session_id', 'pickle')
			->from('talk_matrix_olm_sessions')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('their_curve25519', $qb->createNamedParameter($theirCurve25519)))
			->orderBy('last_used', 'DESC');
		$result = $qb->executeQuery();
		$sessions = [];
		while ($row = $result->fetch()) {
			$sessions[(string)$row['session_id']] = (string)$this->dec($row['pickle']);
		}
		$result->closeCursor();
		return $sessions;
	}

	public function saveOlmSession(string $theirCurve25519, string $sessionId, string $pickle): void {
		$now = $this->timeFactory->getDateTime();
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_matrix_olm_sessions')
			->set('pickle', $qb->createNamedParameter($this->enc($pickle)))
			->set('last_used', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)));
		if ($qb->executeStatement() === 0) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('talk_matrix_olm_sessions')->values([
				'account_id' => $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT),
				'their_curve25519' => $qb->createNamedParameter($theirCurve25519),
				'session_id' => $qb->createNamedParameter($sessionId),
				'pickle' => $qb->createNamedParameter($this->enc($pickle)),
				'last_used' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE),
			]);
			$qb->executeStatement();
		}
	}

	public function loadInboundGroupSession(string $roomId, string $sessionId): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('pickle')
			->from('talk_matrix_megolm_in')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false ? null : $this->dec((string)$value);
	}

	/**
	 * Any linked account's copy of the session (shared-lookup policy, see MatrixConfig).
	 * @return array{pickle: string, accountId: int}|null
	 */
	public function loadInboundGroupSessionFromAnyAccount(string $roomId, string $sessionId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('pickle', 'account_id')
			->from('talk_matrix_megolm_in')
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)))
			->orderBy('first_known_index', 'ASC')
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}
		return ['pickle' => (string)$this->dec($row['pickle']), 'accountId' => (int)$row['account_id']];
	}

	public function saveInboundGroupSession(string $roomId, string $sessionId, string $senderKey, string $pickle, int $firstKnownIndex, array $forwardingChain, string $importedFrom): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_matrix_megolm_in')
			->set('pickle', $qb->createNamedParameter($this->enc($pickle)))
			->set('sender_key', $qb->createNamedParameter($senderKey))
			->set('first_known_index', $qb->createNamedParameter($firstKnownIndex, IQueryBuilder::PARAM_INT))
			->set('forwarding_chains', $qb->createNamedParameter(json_encode($forwardingChain)))
			->set('imported_from', $qb->createNamedParameter($importedFrom))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)));
		if ($qb->executeStatement() === 0) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('talk_matrix_megolm_in')->values([
				'account_id' => $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT),
				'matrix_room_id' => $qb->createNamedParameter($roomId),
				'session_id' => $qb->createNamedParameter($sessionId),
				'sender_key' => $qb->createNamedParameter($senderKey),
				'pickle' => $qb->createNamedParameter($this->enc($pickle)),
				'first_known_index' => $qb->createNamedParameter($firstKnownIndex, IQueryBuilder::PARAM_INT),
				'forwarding_chains' => $qb->createNamedParameter(json_encode($forwardingChain)),
				'imported_from' => $qb->createNamedParameter($importedFrom),
				'created_at' => $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
			]);
			$qb->executeStatement();
		}
	}

	public function loadOutboundGroupSession(string $roomId): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('pickle')
			->from('talk_matrix_megolm_out')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false ? null : $this->dec((string)$value);
	}

	public function saveOutboundGroupSession(string $roomId, string $sessionId, string $pickle, array $sharedWith, int $createdAt, int $messageCount): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_matrix_megolm_out')
			->set('session_id', $qb->createNamedParameter($sessionId))
			->set('pickle', $qb->createNamedParameter($this->enc($pickle)))
			->set('shared_with', $qb->createNamedParameter(json_encode($sharedWith)))
			->set('created_at', $qb->createNamedParameter($createdAt, IQueryBuilder::PARAM_INT))
			->set('message_count', $qb->createNamedParameter($messageCount, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)));
		if ($qb->executeStatement() === 0) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('talk_matrix_megolm_out')->values([
				'account_id' => $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT),
				'matrix_room_id' => $qb->createNamedParameter($roomId),
				'session_id' => $qb->createNamedParameter($sessionId),
				'pickle' => $qb->createNamedParameter($this->enc($pickle)),
				'shared_with' => $qb->createNamedParameter(json_encode($sharedWith)),
				'created_at' => $qb->createNamedParameter($createdAt, IQueryBuilder::PARAM_INT),
				'message_count' => $qb->createNamedParameter($messageCount, IQueryBuilder::PARAM_INT),
			]);
			$qb->executeStatement();
		}
	}

	public function discardOutboundGroupSession(string $roomId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('talk_matrix_megolm_out')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)));
		$qb->executeStatement();
	}

	public function outboundGroupSessionMeta(string $roomId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('shared_with', 'created_at', 'message_count')
			->from('talk_matrix_megolm_out')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($roomId)));
		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}
		$shared = json_decode((string)$row['shared_with'], true);
		return ['sharedWith' => is_array($shared) ? $shared : [], 'createdAt' => (int)$row['created_at'], 'messageCount' => (int)$row['message_count']];
	}

	public function loadDevices(array $userIds): array {
		if ($userIds === []) {
			return [];
		}
		$out = [];
		foreach (array_chunk($userIds, 200) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('mxid', 'device_id', 'keys_json')
				->from('talk_matrix_devices')
				->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->in('mxid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$raw = json_decode((string)$row['keys_json'], true);
				if (!is_array($raw)) {
					continue;
				}
				try {
					$out[(string)$row['mxid']][(string)$row['device_id']] = DeviceKeys::fromArray((string)$row['mxid'], (string)$row['device_id'], $raw);
				} catch (CryptoException) {
					continue;
				}
			}
			$result->closeCursor();
		}
		// A user we queried but who has no devices is still "known": represent with an empty list
		foreach ($this->knownUsers($userIds) as $userId) {
			$out[$userId] ??= [];
		}
		return $out;
	}

	/** Users that have a marker row (device_id '') meaning "list fetched", possibly empty. @return list<string> */
	private function knownUsers(array $userIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('mxid')
			->from('talk_matrix_devices')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('mxid', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));
		return array_map('strval', array_column($qb->executeQuery()->fetchAll(), 'mxid'));
	}

	public function saveDevices(array $devices, array $trust): void {
		$now = $this->timeFactory->getDateTime();
		foreach ($devices as $userId => $userDevices) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('talk_matrix_devices')
				->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($userId)));
			$qb->executeStatement();
			// Marker row so that a user without devices counts as fetched
			$rows = $userDevices === [] ? [['device_id' => '', 'curve' => '', 'ed' => '', 'json' => '{}', 'trust' => Trust::UNKNOWN]] : [];
			foreach ($userDevices as $deviceId => $device) {
				$rows[] = ['device_id' => (string)$deviceId, 'curve' => $device->curve25519, 'ed' => $device->ed25519, 'json' => json_encode($device->raw), 'trust' => $trust[$userId][$deviceId] ?? Trust::UNKNOWN];
			}
			foreach ($rows as $row) {
				$qb = $this->db->getQueryBuilder();
				$qb->insert('talk_matrix_devices')->values([
					'account_id' => $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT),
					'mxid' => $qb->createNamedParameter($userId),
					'device_id' => $qb->createNamedParameter($row['device_id']),
					'curve25519_key' => $qb->createNamedParameter($row['curve']),
					'ed25519_key' => $qb->createNamedParameter($row['ed']),
					'keys_json' => $qb->createNamedParameter($row['json']),
					'trust' => $qb->createNamedParameter($row['trust'], IQueryBuilder::PARAM_INT),
					'stale' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE),
				]);
				$qb->executeStatement();
			}
		}
	}

	public function deviceTrust(string $userId, string $deviceId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('trust')
			->from('talk_matrix_devices')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('device_id', $qb->createNamedParameter($deviceId)));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false ? Trust::UNKNOWN : (int)$value;
	}

	public function setDeviceTrust(string $userId, string $deviceId, int $trust): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_matrix_devices')
			->set('trust', $qb->createNamedParameter($trust, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('device_id', $qb->createNamedParameter($deviceId)));
		$qb->executeStatement();
	}

	public function markDevicesStale(array $userIds): void {
		if ($userIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_matrix_devices')
			->set('stale', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('mxid', $qb->createNamedParameter(array_values($userIds), IQueryBuilder::PARAM_STR_ARRAY)));
		$qb->executeStatement();
	}

	public function staleUsers(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('mxid')
			->from('talk_matrix_devices')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('stale', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		return array_map('strval', array_column($qb->executeQuery()->fetchAll(), 'mxid'));
	}

	public function loadCrossSigning(string $userId): ?CrossSigningKeys {
		$qb = $this->db->getQueryBuilder();
		$qb->select('master_key', 'self_signing_key', 'user_signing_key')
			->from('talk_matrix_cross_signing')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($userId)));
		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}
		return new CrossSigningKeys($userId, $row['master_key'], $row['self_signing_key'], $row['user_signing_key'], $row['self_signing_key'] !== null);
	}

	public function saveCrossSigning(CrossSigningKeys $keys): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('talk_matrix_cross_signing')
			->set('master_key', $qb->createNamedParameter($keys->master))
			->set('self_signing_key', $qb->createNamedParameter($keys->selfSigning))
			->set('user_signing_key', $qb->createNamedParameter($keys->userSigning))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($keys->userId)));
		if ($qb->executeStatement() === 0) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('talk_matrix_cross_signing')->values([
				'account_id' => $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT),
				'mxid' => $qb->createNamedParameter($keys->userId),
				'master_key' => $qb->createNamedParameter($keys->master),
				'self_signing_key' => $qb->createNamedParameter($keys->selfSigning),
				'user_signing_key' => $qb->createNamedParameter($keys->userSigning),
				'updated_at' => $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
			]);
			$qb->executeStatement();
		}
	}

	public function getSecret(string $name): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('value_enc')
			->from('talk_matrix_secrets')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false ? null : $this->dec((string)$value);
	}

	public function setSecret(string $name, ?string $value): void {
		$qb = $this->db->getQueryBuilder();
		if ($value === null) {
			$qb->delete('talk_matrix_secrets')
				->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));
			$qb->executeStatement();
			return;
		}
		$qb->update('talk_matrix_secrets')
			->set('value_enc', $qb->createNamedParameter($this->enc($value)))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));
		if ($qb->executeStatement() === 0) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('talk_matrix_secrets')->values([
				'account_id' => $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT),
				'name' => $qb->createNamedParameter($name),
				'value_enc' => $qb->createNamedParameter($this->enc($value)),
				'updated_at' => $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
			]);
			$qb->executeStatement();
		}
	}

	/** Names of all secrets with a given prefix (e.g. running verifications). @return list<string> */
	public function secretNames(string $prefix): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('name')
			->from('talk_matrix_secrets')
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->like('name', $qb->createNamedParameter($this->db->escapeLikeParameter($prefix) . '%')));
		return array_map('strval', array_column($qb->executeQuery()->fetchAll(), 'name'));
	}

	/** Wipe everything belonging to the account (unlink). */
	public function deleteAll(): void {
		foreach (['talk_matrix_devices', 'talk_matrix_olm_sessions', 'talk_matrix_megolm_in', 'talk_matrix_megolm_out', 'talk_matrix_secrets', 'talk_matrix_cross_signing'] as $table) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)->where($qb->expr()->eq('account_id', $qb->createNamedParameter($this->accountId(), IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
		}
	}
}
