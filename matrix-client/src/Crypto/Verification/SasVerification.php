<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Verification;

use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Util\Canonical;

/**
 * State machine of one `m.sas.v1` self-verification (our device vs. another
 * device of the same user) over to-device events. Pure: the host sends the
 * events {@see SasVerification::takeOutgoing()} produces and feeds incoming
 * ones into {@see SasVerification::handle()}.
 *
 * Steps: request → ready → start → accept → key ↔ key → (user compares) → mac ↔ mac → done.
 */
final class SasVerification {
	public const STATE_REQUESTED = 'requested';
	public const STATE_READY = 'ready';
	public const STATE_STARTED = 'started';
	public const STATE_ACCEPTED = 'accepted';
	public const STATE_KEYS_EXCHANGED = 'keys_exchanged';
	public const STATE_MAC_SENT = 'mac_sent';
	public const STATE_DONE = 'done';
	public const STATE_CANCELLED = 'cancelled';

	public string $state = self::STATE_REQUESTED;
	public ?string $theirDeviceId = null;
	public ?string $cancelReason = null;
	/** @var list<array{type: string, content: array<string, mixed>}> */
	private array $outgoing = [];
	private Sas $sas;
	private bool $weStarted = false;
	/** @var array<string, mixed>|null */
	private ?array $startContent = null;
	private ?string $theirCommitment = null;
	private ?string $theirPublicKey = null;
	private ?string $macMethod = null;
	private bool $theirMacVerified = false;
	private bool $ourMacSent = false;
	private ?string $theirEd25519 = null;

	public function __construct(
		public readonly string $transactionId,
		public readonly string $userId,
		public readonly string $ourDeviceId,
		public readonly string $ourEd25519,
		public readonly int $startedAt,
		?Sas $sas = null,
	) {
		$this->sas = $sas ?? new Sas();
	}

	/** Begin: send m.key.verification.request to all our other devices. */
	public static function request(string $transactionId, string $userId, string $ourDeviceId, string $ourEd25519, int $now): self {
		$v = new self($transactionId, $userId, $ourDeviceId, $ourEd25519, $now);
		$v->outgoing[] = ['type' => 'm.key.verification.request', 'content' => [
			'from_device' => $ourDeviceId,
			'methods' => ['m.sas.v1'],
			'timestamp' => $now * 1000,
			'transaction_id' => $transactionId,
		]];
		return $v;
	}

	/**
	 * @return list<array{type: string, content: array<string, mixed>}> events to send to the other device (or all our devices for the request)
	 */
	public function takeOutgoing(): array {
		$out = $this->outgoing;
		$this->outgoing = [];
		return $out;
	}

	/**
	 * Feed an incoming to-device verification event of this transaction.
	 * @param array<string, mixed> $content
	 * @param callable(string $deviceId): ?string $deviceEd25519 resolves the other device's Ed25519 key (base64)
	 */
	public function handle(string $type, array $content, callable $deviceEd25519): void {
		if ($this->state === self::STATE_DONE || $this->state === self::STATE_CANCELLED) {
			return;
		}
		switch ($type) {
			case 'm.key.verification.ready':
				if ($this->state !== self::STATE_REQUESTED) {
					return;
				}
				$this->theirDeviceId = (string)($content['from_device'] ?? '');
				$this->theirEd25519 = $deviceEd25519($this->theirDeviceId);
				if ($this->theirEd25519 === null || !in_array('m.sas.v1', $content['methods'] ?? [], true)) {
					$this->cancel('m.unknown_method', 'Other device does not support SAS');
					return;
				}
				$this->state = self::STATE_READY;
				$this->sendStart();
				break;

			case 'm.key.verification.start':
				$fromDevice = (string)($content['from_device'] ?? '');
				if ($this->state === self::STATE_STARTED && $this->weStarted) {
					// Both sides started: the lexicographically smaller device id wins
					if (strcmp($fromDevice, $this->ourDeviceId) >= 0) {
						return; // ours wins, ignore theirs
					}
					$this->weStarted = false;
				} elseif ($this->state !== self::STATE_READY && $this->state !== self::STATE_REQUESTED) {
					return;
				}
				$this->theirDeviceId = $fromDevice;
				$this->theirEd25519 ??= $deviceEd25519($fromDevice);
				if ($this->theirEd25519 === null) {
					$this->cancel('m.key_mismatch', 'Unknown device');
					return;
				}
				if (($content['method'] ?? '') !== 'm.sas.v1'
					|| !in_array(Sas::KEY_AGREEMENT, $content['key_agreement_protocols'] ?? [], true)
					|| !in_array(Sas::HASH, $content['hashes'] ?? [], true)
					|| !in_array(Sas::SAS_EMOJI, $content['short_authentication_string'] ?? [], true)) {
					$this->cancel('m.unknown_method', 'Unsupported verification parameters');
					return;
				}
				$macs = $content['message_authentication_codes'] ?? [];
				$this->macMethod = in_array(Sas::MAC_V2, $macs, true) ? Sas::MAC_V2 : (in_array(Sas::MAC_V1, $macs, true) ? Sas::MAC_V1 : null);
				if ($this->macMethod === null) {
					$this->cancel('m.unknown_method', 'Unsupported MAC method');
					return;
				}
				$this->startContent = $content;
				$this->state = self::STATE_ACCEPTED;
				$this->outgoing[] = ['type' => 'm.key.verification.accept', 'content' => [
					'transaction_id' => $this->transactionId,
					'key_agreement_protocol' => Sas::KEY_AGREEMENT,
					'hash' => Sas::HASH,
					'message_authentication_code' => $this->macMethod,
					'short_authentication_string' => [Sas::SAS_DECIMAL, Sas::SAS_EMOJI],
					'commitment' => Sas::commitment($this->sas->getPublicKey(), $content),
				]];
				break;

			case 'm.key.verification.accept':
				if ($this->state !== self::STATE_STARTED || !$this->weStarted) {
					return;
				}
				$mac = (string)($content['message_authentication_code'] ?? '');
				if (($content['key_agreement_protocol'] ?? '') !== Sas::KEY_AGREEMENT || ($content['hash'] ?? '') !== Sas::HASH || !in_array($mac, [Sas::MAC_V2, Sas::MAC_V1], true)) {
					$this->cancel('m.unknown_method', 'Unsupported verification parameters');
					return;
				}
				$this->macMethod = $mac;
				$this->theirCommitment = (string)($content['commitment'] ?? '');
				$this->state = self::STATE_ACCEPTED;
				// The starter sends its key first
				$this->outgoing[] = ['type' => 'm.key.verification.key', 'content' => ['transaction_id' => $this->transactionId, 'key' => $this->sas->getPublicKey()]];
				break;

			case 'm.key.verification.key':
				if ($this->state !== self::STATE_ACCEPTED) {
					return;
				}
				$theirKey = (string)($content['key'] ?? '');
				if ($this->weStarted) {
					// Verify their commitment
					if ($this->theirCommitment === null || !hash_equals(rtrim($this->theirCommitment, '='), rtrim(Sas::commitment($theirKey, (array)$this->startContent), '='))) {
						$this->cancel('m.mismatched_commitment', 'Commitment mismatch');
						return;
					}
				} else {
					// We accepted: reply with our key now
					$this->outgoing[] = ['type' => 'm.key.verification.key', 'content' => ['transaction_id' => $this->transactionId, 'key' => $this->sas->getPublicKey()]];
				}
				$this->theirPublicKey = $theirKey;
				try {
					$this->sas->establish($theirKey);
				} catch (CryptoException) {
					$this->cancel('m.invalid_message', 'Bad key');
					return;
				}
				$this->state = self::STATE_KEYS_EXCHANGED;
				break;

			case 'm.key.verification.mac':
				if ($this->state !== self::STATE_KEYS_EXCHANGED && $this->state !== self::STATE_MAC_SENT) {
					return;
				}
				if (!$this->verifyTheirMac($content)) {
					$this->cancel('m.key_mismatch', 'MAC verification failed');
					return;
				}
				$this->theirMacVerified = true;
				if ($this->ourMacSent) {
					$this->finish();
				}
				break;

			case 'm.key.verification.done':
				if ($this->state === self::STATE_MAC_SENT && $this->theirMacVerified) {
					$this->state = self::STATE_DONE;
				}
				break;

			case 'm.key.verification.cancel':
				$this->state = self::STATE_CANCELLED;
				$this->cancelReason = (string)($content['reason'] ?? $content['code'] ?? 'cancelled');
				break;
		}
	}

	/** Emoji to show once keys are exchanged. @return list<array{emoji: string, name: string}> */
	public function emoji(): array {
		return Emoji::fromSasBytes($this->sasBytes());
	}

	/** @return array{0: int, 1: int, 2: int} */
	public function decimal(): array {
		return Emoji::decimalFromSasBytes($this->sasBytes());
	}

	/** The user confirmed the emoji match: send our MAC (and done if theirs already arrived). */
	public function confirm(): void {
		if ($this->state !== self::STATE_KEYS_EXCHANGED) {
			throw new CryptoException('Nothing to confirm in state ' . $this->state);
		}
		$keyId = 'ed25519:' . $this->ourDeviceId;
		$macs = [$keyId => $this->sas->mac($this->ourEd25519, $this->userId, $this->ourDeviceId, $this->userId, (string)$this->theirDeviceId, $this->transactionId, $keyId, (string)$this->macMethod)];
		$keys = $this->sas->mac(implode(',', array_keys($macs)), $this->userId, $this->ourDeviceId, $this->userId, (string)$this->theirDeviceId, $this->transactionId, 'KEY_IDS', (string)$this->macMethod);
		$this->outgoing[] = ['type' => 'm.key.verification.mac', 'content' => ['transaction_id' => $this->transactionId, 'mac' => $macs, 'keys' => $keys]];
		$this->ourMacSent = true;
		$this->state = self::STATE_MAC_SENT;
		if ($this->theirMacVerified) {
			$this->finish();
		}
	}

	/** The user says the emoji do NOT match. */
	public function reject(): void {
		$this->cancel('m.mismatched_sas', 'Short authentication string mismatch');
	}

	public function cancel(string $code, string $reason): void {
		if ($this->state === self::STATE_CANCELLED || $this->state === self::STATE_DONE) {
			return;
		}
		$this->state = self::STATE_CANCELLED;
		$this->cancelReason = $reason;
		$this->outgoing[] = ['type' => 'm.key.verification.cancel', 'content' => ['transaction_id' => $this->transactionId, 'code' => $code, 'reason' => $reason]];
	}

	public function isFinished(): bool {
		return $this->state === self::STATE_DONE || $this->state === self::STATE_CANCELLED;
	}

	/** Their Ed25519 key, verified by MAC when done. */
	public function getTheirEd25519(): ?string {
		return $this->theirEd25519;
	}

	/** Master key the other side included in its MAC (cross-signing) – trust it once done. */
	public ?string $theirMasterKey = null;

	private function sendStart(): void {
		$this->weStarted = true;
		$this->startContent = [
			'from_device' => $this->ourDeviceId,
			'method' => 'm.sas.v1',
			'transaction_id' => $this->transactionId,
			'key_agreement_protocols' => [Sas::KEY_AGREEMENT],
			'hashes' => [Sas::HASH],
			'message_authentication_codes' => [Sas::MAC_V2, Sas::MAC_V1],
			'short_authentication_string' => [Sas::SAS_DECIMAL, Sas::SAS_EMOJI],
		];
		$this->state = self::STATE_STARTED;
		$this->outgoing[] = ['type' => 'm.key.verification.start', 'content' => $this->startContent];
	}

	private function sasBytes(): string {
		if ($this->theirPublicKey === null) {
			throw new CryptoException('Keys not exchanged yet');
		}
		[$startUser, $startDevice, $startKey, $acceptUser, $acceptDevice, $acceptKey] = $this->weStarted
			? [$this->userId, $this->ourDeviceId, $this->sas->getPublicKey(), $this->userId, (string)$this->theirDeviceId, $this->theirPublicKey]
			: [$this->userId, (string)$this->theirDeviceId, $this->theirPublicKey, $this->userId, $this->ourDeviceId, $this->sas->getPublicKey()];
		return $this->sas->bytes($startUser, $startDevice, $startKey, $acceptUser, $acceptDevice, $acceptKey, $this->transactionId);
	}

	/** @param array<string, mixed> $content */
	private function verifyTheirMac(array $content): bool {
		$macs = is_array($content['mac'] ?? null) ? $content['mac'] : [];
		$method = (string)$this->macMethod;
		$theirDevice = (string)$this->theirDeviceId;
		$keyIds = array_keys($macs);
		sort($keyIds);
		if (!isset($content['keys']) || !$this->sas->macMatches((string)$content['keys'], implode(',', $keyIds), $this->userId, $theirDevice, $this->userId, $this->ourDeviceId, $this->transactionId, 'KEY_IDS', $method)) {
			return false;
		}
		$deviceKeyId = 'ed25519:' . $theirDevice;
		if (!isset($macs[$deviceKeyId]) || !$this->sas->macMatches((string)$macs[$deviceKeyId], (string)$this->theirEd25519, $this->userId, $theirDevice, $this->userId, $this->ourDeviceId, $this->transactionId, $deviceKeyId, $method)) {
			return false;
		}
		foreach ($macs as $keyId => $mac) {
			if ($keyId === $deviceKeyId || !str_starts_with((string)$keyId, 'ed25519:')) {
				continue;
			}
			// A MAC over a key id whose value is the key itself = the user's master cross-signing key
			$candidate = substr((string)$keyId, strlen('ed25519:'));
			if ($this->sas->macMatches((string)$mac, $candidate, $this->userId, $theirDevice, $this->userId, $this->ourDeviceId, $this->transactionId, (string)$keyId, $method)) {
				$this->theirMasterKey = $candidate;
			}
		}
		return true;
	}

	private function finish(): void {
		$this->outgoing[] = ['type' => 'm.key.verification.done', 'content' => ['transaction_id' => $this->transactionId]];
		$this->state = self::STATE_DONE;
	}

	public function pickle(): string {
		return json_encode([
			'v' => 1, 'txn' => $this->transactionId, 'user' => $this->userId, 'dev' => $this->ourDeviceId, 'ed' => $this->ourEd25519, 'at' => $this->startedAt,
			'state' => $this->state, 'their' => $this->theirDeviceId, 'theirEd' => $this->theirEd25519, 'reason' => $this->cancelReason,
			'sas' => $this->sas->pickle(), 'weStarted' => $this->weStarted, 'start' => $this->startContent, 'commit' => $this->theirCommitment,
			'theirKey' => $this->theirPublicKey, 'mac' => $this->macMethod, 'theirMac' => $this->theirMacVerified, 'ourMac' => $this->ourMacSent, 'master' => $this->theirMasterKey,
			'outgoing' => $this->outgoing,
		], JSON_THROW_ON_ERROR);
	}

	public static function unpickle(string $pickle): self {
		$d = json_decode($pickle, true);
		if (!is_array($d) || ($d['v'] ?? 0) !== 1) {
			throw new CryptoException('Invalid verification pickle');
		}
		$v = new self($d['txn'], $d['user'], $d['dev'], $d['ed'], (int)$d['at'], Sas::unpickle($d['sas']));
		$v->state = $d['state'];
		$v->theirDeviceId = $d['their'];
		$v->theirEd25519 = $d['theirEd'];
		$v->cancelReason = $d['reason'];
		$v->weStarted = (bool)$d['weStarted'];
		$v->startContent = $d['start'];
		$v->theirCommitment = $d['commit'];
		$v->theirPublicKey = $d['theirKey'];
		$v->macMethod = $d['mac'];
		$v->theirMacVerified = (bool)$d['theirMac'];
		$v->ourMacSent = (bool)$d['ourMac'];
		$v->theirMasterKey = $d['master'];
		$v->outgoing = $d['outgoing'] ?? [];
		return $v;
	}
}
