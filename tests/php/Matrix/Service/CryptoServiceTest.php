<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use OC\Memcache\ArrayCache;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\DeviceKeys;
use OCA\Talk\Matrix\Client\Crypto\Megolm\InboundSession;
use OCA\Talk\Matrix\Client\Crypto\Megolm\OutboundSession;
use OCA\Talk\Matrix\Client\Crypto\MegolmEnvelope;
use OCA\Talk\Matrix\Client\Crypto\Olm\Account as OlmAccount;
use OCA\Talk\Matrix\Client\Crypto\Olm\Session;
use OCA\Talk\Matrix\Client\Crypto\OlmEnvelope;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\InboundGroupSession;
use OCA\Talk\Matrix\Model\InboundGroupSessionMapper;
use OCA\Talk\Matrix\Model\MatrixDevice;
use OCA\Talk\Matrix\Model\MatrixDeviceMapper;
use OCA\Talk\Matrix\Model\OlmSession;
use OCA\Talk\Matrix\Model\OlmSessionMapper;
use OCA\Talk\Matrix\Model\OutboundGroupSession;
use OCA\Talk\Matrix\Model\OutboundGroupSessionMapper;
use OCA\Talk\Matrix\Service\CryptoService;
use OCA\Talk\Matrix\Service\ExclusiveLock;
use OCA\Talk\Matrix\Service\VerificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;
use OCP\Lock\ILockingProvider;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CryptoServiceTest extends TestCase {
	private Client&MockObject $client;
	private MatrixDeviceMapper&MockObject $deviceMapper;
	private VerificationService&MockObject $verificationService;
	private Account $account;
	/** @var array<string, array<string, array<string, mixed>>> Published device keys indexed by user and device */
	private array $published = [];
	/** @var list<array{0: array, 1: array, 2: array}> */
	private array $uploads = [];
	/** @var array<string, MatrixDevice> */
	private array $devices = [];
	/** @var list<OlmSession> */
	private array $olmSessions = [];
	/** @var array<string, InboundGroupSession> */
	private array $groupSessions = [];
	/** @var array<string, OutboundGroupSession> */
	private array $outboundSessions = [];
	/** @var array<string, array<string, array<string, mixed>>> Claimable one-time keys indexed by user and device */
	private array $oneTimeKeys = [];
	/** @var list<array{0: string, 1: array}> */
	private array $toDevice = [];
	private CryptoService $service;

	protected function setUp(): void {
		parent::setUp();
		if (!function_exists('sodium_crypto_sign_verify_detached')) {
			$this->markTestSkipped('The sodium extension is not available');
		}

		$this->account = Account::fromRow(['id' => '7', 'mxid' => '@alice:hs', 'device_id' => 'ALICE']);
		$this->client = $this->createMock(Client::class);
		$this->client->method('uploadKeys')->willReturnCallback(function (array $deviceKeys, array $oneTimeKeys, array $fallbackKeys): array {
			$this->uploads[] = [$deviceKeys, $oneTimeKeys, $fallbackKeys];
			return [];
		});
		$this->client->method('queryKeys')->willReturnCallback(fn (array $userIds): array => array_intersect_key($this->published, array_flip($userIds)));

		$this->deviceMapper = $this->createMock(MatrixDeviceMapper::class);
		$this->deviceMapper->method('findForUser')->willReturnCallback(function (string $accountId, string $mxid): array {
			$devices = [];
			foreach ($this->devices as $device) {
				if ($device->getMxid() === $mxid) {
					$devices[$device->getDeviceId()] = $device;
				}
			}
			return $devices;
		});
		$this->deviceMapper->method('insert')->willReturnCallback(function (MatrixDevice $device): MatrixDevice {
			$this->devices[$device->getMxid() . '/' . $device->getDeviceId()] = $device;
			return $device;
		});
		$this->deviceMapper->method('delete')->willReturnCallback(function (MatrixDevice $device): MatrixDevice {
			unset($this->devices[$device->getMxid() . '/' . $device->getDeviceId()]);
			return $device;
		});
		$this->deviceMapper->method('markOutdated')->willReturnCallback(function (string $accountId, string $mxid): void {
			foreach ($this->devices as $device) {
				if ($device->getMxid() === $mxid) {
					$device->setOutdated(true);
				}
			}
		});

		$olmSessionMapper = $this->createMock(OlmSessionMapper::class);
		$olmSessionMapper->method('findForDevice')->willReturnCallback(fn (string $accountId, string $senderKey): array => array_values(array_filter($this->olmSessions, static fn (OlmSession $session): bool => $session->getSenderKey() === $senderKey)));
		$olmSessionMapper->method('insert')->willReturnCallback(function (OlmSession $session): OlmSession {
			$this->olmSessions[] = $session;
			return $session;
		});

		$groupSessionMapper = $this->createMock(InboundGroupSessionMapper::class);
		$groupSessionMapper->method('find')->willReturnCallback(fn (string $accountId, string $roomId, string $sessionId): ?InboundGroupSession => $this->groupSessions[$roomId . '/' . $sessionId] ?? null);
		$groupSessionMapper->method('insert')->willReturnCallback(function (InboundGroupSession $session): InboundGroupSession {
			$this->groupSessions[$session->getRoomId() . '/' . $session->getSessionId()] = $session;
			return $session;
		});

		$outboundMapper = $this->createMock(OutboundGroupSessionMapper::class);
		$outboundMapper->method('find')->willReturnCallback(fn (string $accountId, string $roomId): ?OutboundGroupSession => $this->outboundSessions[$roomId] ?? null);
		$outboundMapper->method('insert')->willReturnCallback(fn (OutboundGroupSession $session): OutboundGroupSession => $this->outboundSessions[$session->getRoomId()] = OutboundGroupSession::fromRow([
			'id' => (string)(count($this->outboundSessions) + 1),
			'account_id' => $session->getAccountId(),
			'room_id' => $session->getRoomId(),
			'pickle' => $session->getPickle(),
			'shared_with' => $session->getSharedWith(),
		]));
		$olmSessionMapper->method('update')->willReturnArgument(0);
		$this->client->method('claimKeys')->willReturnCallback(fn (array $devices): array => array_intersect_key($this->oneTimeKeys, $devices));
		$this->client->method('sendToDevice')->willReturnCallback(function (string $type, array $messages): void {
			$this->toDevice[] = [$type, $messages];
		});
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn('txn');
		$this->verificationService = $this->createMock(VerificationService::class);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'encrypted:' . $plain);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $cipher): string => substr($cipher, strlen('encrypted:')));

		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn(new ArrayCache(''));

		$this->service = new CryptoService(
			$this->createMock(AccountMapper::class),
			$this->deviceMapper,
			$olmSessionMapper,
			$groupSessionMapper,
			$outboundMapper,
			$this->verificationService,
			new ExclusiveLock($this->createMock(ILockingProvider::class)),
			$secureRandom,
			$crypto,
			$this->createMock(ITimeFactory::class),
			$this->createMock(LoggerInterface::class),
			$cacheFactory,
		);
	}

	private static function batch(array $raw = []): SyncBatch {
		return SyncBatch::fromArray($raw + ['next_batch' => 'next']);
	}

	/**
	 * First sync of the account, returns its published device keys and one of its one-time keys
	 *
	 * @return array{0: DeviceKeys, 1: string}
	 */
	private function bootstrap(): array {
		$this->service->processSync($this->account, $this->client, self::batch());
		[$deviceKeys, $oneTimeKeys] = $this->uploads[0];
		return [DeviceKeys::fromArray('@alice:hs', 'ALICE', $deviceKeys), (string)reset($oneTimeKeys)['key']];
	}

	/**
	 * A device of another user that publishes its keys
	 */
	private function device(string $mxid, string $deviceId): OlmAccount {
		$olm = OlmAccount::create();
		$this->published[$mxid][$deviceId] = $olm->deviceKeys($mxid, $deviceId);
		return $olm;
	}

	/**
	 * To-device event with the room key of the outbound session, Olm encrypted for our device
	 */
	private static function roomKeyEvent(OlmAccount $from, string $sender, string $deviceId, array $ourDevice, OutboundSession $outbound): array {
		[$ourKeys, $ourOneTimeKey] = $ourDevice;
		$session = Session::createOutbound($from, Base64::decode($ourKeys->curve25519), Base64::decode($ourOneTimeKey));
		return [
			'type' => 'm.room.encrypted',
			'sender' => $sender,
			'content' => OlmEnvelope::encrypt($from, $sender, $deviceId, $session, $ourKeys, 'm.room_key', MegolmEnvelope::roomKeyContent($outbound, '!room:hs')),
		];
	}

	private static function roomEvent(OlmAccount $from, OutboundSession $outbound, string $sender, string $body): Event {
		return new Event('$event', 'm.room.encrypted', $sender, MegolmEnvelope::encrypt($outbound, $from, 'DEV', '!room:hs', 'm.room.message', ['msgtype' => 'm.text', 'body' => $body]));
	}

	public function testFirstSyncPublishesKeys(): void {
		[$deviceKeys] = $this->bootstrap();

		self::assertCount(1, $this->uploads);
		[, $oneTimeKeys, $fallbackKeys] = $this->uploads[0];
		self::assertCount(CryptoService::ONE_TIME_KEYS, $oneTimeKeys);
		self::assertCount(1, $fallbackKeys);
		self::assertTrue($deviceKeys->supportsMegolm());
		self::assertStringStartsWith('encrypted:', (string)$this->account->getOlmAccount());

		$this->service->processSync($this->account, $this->client, self::batch(['device_one_time_keys_count' => ['signed_curve25519' => 40], 'device_unused_fallback_key_types' => ['signed_curve25519']]));
		self::assertCount(1, $this->uploads, 'Nothing is uploaded while enough keys are there');
	}

	public function testUsedKeysAreReplaced(): void {
		$this->bootstrap();

		$this->service->processSync($this->account, $this->client, self::batch(['device_one_time_keys_count' => ['signed_curve25519' => 10], 'device_unused_fallback_key_types' => []]));

		self::assertCount(2, $this->uploads);
		[, $oneTimeKeys, $fallbackKeys] = $this->uploads[1];
		self::assertCount(CryptoService::ONE_TIME_KEYS - 10, $oneTimeKeys);
		self::assertCount(1, $fallbackKeys);
	}

	public function testRoomKeyDecryptsMessagesOfTheSender(): void {
		$ourDevice = $this->bootstrap();
		$bob = $this->device('@bob:hs', 'BOB');
		$outbound = OutboundSession::create(0);

		$roomKeys = $this->service->processSync($this->account, $this->client, self::batch(['to_device' => ['events' => [self::roomKeyEvent($bob, '@bob:hs', 'BOB', $ourDevice, $outbound)]]]));

		self::assertSame([['!room:hs', $outbound->getId()]], $roomKeys);
		self::assertSame('@bob:hs', $this->groupSessions['!room:hs/' . $outbound->getId()]->getSender());
		self::assertCount(1, $this->olmSessions);

		$decrypted = $this->service->decryptRoomEvent($this->account, '!room:hs', self::roomEvent($bob, $outbound, '@bob:hs', 'Hello'));
		self::assertSame('m.room.message', $decrypted?->type);
		self::assertSame('Hello', $decrypted->getBody());
		self::assertSame($outbound->getId(), $decrypted->megolmSession);
		self::assertSame(0, $decrypted->megolmIndex);

		self::assertNull($this->service->decryptRoomEvent($this->account, '!room:hs', self::roomEvent($bob, $outbound, '@carol:hs', 'Not from Bob')), 'Only messages of the user that shared the key are decrypted');
		self::assertNull($this->service->decryptRoomEvent($this->account, '!other:hs', self::roomEvent($bob, OutboundSession::create(0), '@bob:hs', 'Unknown key')));
	}

	public function testRoomKeyFromDeviceOfAnotherUserIsIgnored(): void {
		$ourDevice = $this->bootstrap();
		$this->device('@bob:hs', 'BOB');
		$mallory = OlmAccount::create();

		$roomKeys = $this->service->processSync($this->account, $this->client, self::batch(['to_device' => ['events' => [self::roomKeyEvent($mallory, '@bob:hs', 'MALLORY', $ourDevice, OutboundSession::create(0))]]]));

		self::assertSame([], $roomKeys);
		self::assertSame([], $this->groupSessions);
	}

	public function testChangedDeviceKeysAreNotTrusted(): void {
		$ourDevice = $this->bootstrap();
		$this->device('@bob:hs', 'BOB');
		$this->service->processSync($this->account, $this->client, self::batch(['to_device' => ['events' => [self::roomKeyEvent(OlmAccount::create(), '@bob:hs', 'BOB', $ourDevice, OutboundSession::create(0))]]]));
		self::assertArrayHasKey('@bob:hs/BOB', $this->devices, 'The devices of Bob were queried');

		// The device is published again with other keys
		$impostor = $this->device('@bob:hs', 'BOB');
		[, $oneTimeKeys] = $this->uploads[0];
		$roomKeys = $this->service->processSync($this->account, $this->client, self::batch([
			'device_lists' => ['changed' => ['@bob:hs']],
			'to_device' => ['events' => [self::roomKeyEvent($impostor, '@bob:hs', 'BOB', [$ourDevice[0], (string)array_values($oneTimeKeys)[1]['key']], OutboundSession::create(0))]],
		]));

		self::assertSame([], $roomKeys);
		self::assertFalse($this->devices['@bob:hs/BOB']->getOutdated());
		self::assertNotSame($impostor->getIdentityKeyBase64(), $this->devices['@bob:hs/BOB']->getCurve25519());
	}

	public function testWipe(): void {
		$this->bootstrap();
		$this->deviceMapper->expects(self::once())->method('deleteForAccount')->with('7');

		$this->service->wipe($this->account);

		self::assertNull($this->account->getOlmAccount());
	}

	public function testVerificationEventsOfOwnDevices(): void {
		$this->bootstrap();
		$phone = $this->device('@alice:hs', 'PHONE');
		$this->verificationService->expects(self::once())
			->method('handle')
			->with($this->account, $this->client, 'm.key.verification.ready', ['transaction_id' => 'txn'], self::callback(static fn (\Closure $deviceEd25519): bool => $deviceEd25519('PHONE') === $phone->getSigningKeyBase64() && $deviceEd25519('OTHER') === null));

		$this->service->processSync($this->account, $this->client, self::batch(['to_device' => ['events' => [
			['type' => 'm.key.verification.ready', 'sender' => '@alice:hs', 'content' => ['transaction_id' => 'txn']],
			['type' => 'm.key.verification.ready', 'sender' => '@mallory:hs', 'content' => ['transaction_id' => 'txn']],
		]]]));
	}

	public function testFailingVerificationEventDoesNotBlockTheSync(): void {
		$ourDevice = $this->bootstrap();
		$bob = $this->device('@bob:hs', 'BOB');
		$outbound = OutboundSession::create(0);
		$this->verificationService->method('handle')->willThrowException(new \TypeError('Malformed'));

		$roomKeys = $this->service->processSync($this->account, $this->client, self::batch(['to_device' => ['events' => [
			['type' => 'm.key.verification.ready', 'sender' => '@alice:hs', 'content' => ['transaction_id' => 'txn']],
			self::roomKeyEvent($bob, '@bob:hs', 'BOB', $ourDevice, $outbound),
		]]]));

		self::assertSame([['!room:hs', $outbound->getId()]], $roomKeys);
	}

	public function testSigningKey(): void {
		self::assertNull($this->service->getSigningKey($this->account));
		[$deviceKeys] = $this->bootstrap();
		self::assertSame($deviceKeys->ed25519, $this->service->getSigningKey($this->account));
	}

	/**
	 * A device of another user that publishes its keys and a one-time key
	 */
	private function deviceWithOneTimeKey(string $mxid, string $deviceId): OlmAccount {
		$olm = $this->device($mxid, $deviceId);
		$olm->generateOneTimeKeys(1);
		$this->oneTimeKeys[$mxid][$deviceId] = $olm->keysForUpload($mxid, $deviceId)['one_time_keys'];
		return $olm;
	}

	/**
	 * The room key the device got with the last to-device message
	 */
	private function receivedRoomKey(OlmAccount $olm, string $mxid, string $deviceId): InboundSession {
		[$type, $messages] = end($this->toDevice);
		self::assertSame('m.room.encrypted', $type);
		$content = $messages[$mxid][$deviceId];
		$ciphertext = OlmEnvelope::ourCiphertext($content, $olm);
		['plaintext' => $plaintext] = Session::fromPreKeyMessage($olm, $ciphertext['body'], Base64::decode($content['sender_key']));
		$payload = OlmEnvelope::validatePayload($plaintext, '@alice:hs', $olm, $mxid);
		self::assertSame('m.room_key', $payload['type']);
		return InboundSession::fromSessionKey($payload['content']['session_key']);
	}

	public function testEncryptRoomEvent(): void {
		[$ourKeys] = $this->bootstrap();
		$bob = $this->deviceWithOneTimeKey('@bob:hs', 'BOB');

		$encrypted = $this->service->encryptRoomEvent($this->account, $this->client, '!room:hs', ['@alice:hs', '@bob:hs'], 'm.room.message', ['body' => 'Hello', 'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$parent']]]);

		self::assertSame(['m.in_reply_to' => ['event_id' => '$parent']], $encrypted['m.relates_to'], 'Relations stay readable for the homeserver');
		self::assertSame($ourKeys->curve25519, $encrypted['sender_key']);
		$roomKey = $this->receivedRoomKey($bob, '@bob:hs', 'BOB');
		self::assertSame('Hello', MegolmEnvelope::decrypt($roomKey, $encrypted, '!room:hs')['content']['body']);
		self::assertSame('@alice:hs', $this->groupSessions['!room:hs/' . $encrypted['session_id']]->getSender(), 'Our own messages can be decrypted');
		self::assertSame(['@bob:hs|BOB'], json_decode($this->outboundSessions['!room:hs']->getSharedWith(), true));

		$second = $this->service->encryptRoomEvent($this->account, $this->client, '!room:hs', ['@alice:hs', '@bob:hs'], 'm.room.message', ['body' => 'Again']);
		self::assertCount(1, $this->toDevice, 'The room key is only shared once');
		self::assertSame($encrypted['session_id'], $second['session_id']);
		self::assertSame(['type' => 'm.room.message', 'content' => ['body' => 'Again'], 'index' => 1], MegolmEnvelope::decrypt($roomKey, $second, '!room:hs'));
	}

	public function testNewRoomKeyWhenMemberLeft(): void {
		$this->bootstrap();
		$this->deviceWithOneTimeKey('@bob:hs', 'BOB');
		$first = $this->service->encryptRoomEvent($this->account, $this->client, '!room:hs', ['@alice:hs', '@bob:hs'], 'm.room.message', ['body' => 'Hello']);

		$second = $this->service->encryptRoomEvent($this->account, $this->client, '!room:hs', ['@alice:hs'], 'm.room.message', ['body' => 'Bob left']);

		self::assertNotSame($first['session_id'], $second['session_id']);
		self::assertSame([], json_decode($this->outboundSessions['!room:hs']->getSharedWith(), true));
	}

	public function testDeviceWithForgedOneTimeKeyDoesNotGetTheRoomKey(): void {
		$this->bootstrap();
		$this->device('@bob:hs', 'BOB');
		$mallory = OlmAccount::create();
		$mallory->generateOneTimeKeys(1);
		// Signed by another device than the one of Bob
		$this->oneTimeKeys['@bob:hs']['BOB'] = $mallory->keysForUpload('@bob:hs', 'BOB')['one_time_keys'];

		$this->service->encryptRoomEvent($this->account, $this->client, '!room:hs', ['@bob:hs'], 'm.room.message', ['body' => 'Hello']);

		self::assertSame([], $this->toDevice);
		self::assertSame([], json_decode($this->outboundSessions['!room:hs']->getSharedWith(), true));
	}

	public function testEncryptWithoutKeys(): void {
		$this->expectException(CryptoException::class);
		$this->service->encryptRoomEvent($this->account, $this->client, '!room:hs', [], 'm.room.message', ['body' => 'Hello']);
	}
}
