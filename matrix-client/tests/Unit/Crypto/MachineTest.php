<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit\Crypto;

use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Crypto\Machine;
use Nextcloud\Matrix\Crypto\MemoryCryptoStore;
use Nextcloud\Matrix\Crypto\MissingSessionException;
use Nextcloud\Matrix\Crypto\Trust;
use Nextcloud\Matrix\Http\Transport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class MachineTest extends TestCase {
	private FakeHomeserver $hsAlice;
	private FakeHomeserver $hsBob;
	private Machine $alice;
	private Machine $bob;
	private MemoryCryptoStore $bobStore;

	protected function setUp(): void {
		$factory = new Psr17Factory();
		$this->hsAlice = new FakeHomeserver('@alice:hs', 'ALICE');
		$this->hsBob = new FakeHomeserver('@bob:hs', 'BOB');
		$this->hsAlice->linkWith($this->hsBob);
		$aliceClient = new Client((new Transport('https://hs', $this->hsAlice, $factory, $factory))->withAccessToken('a'));
		$bobClient = new Client((new Transport('https://hs', $this->hsBob, $factory, $factory))->withAccessToken('b'));
		$this->bobStore = new MemoryCryptoStore();
		$this->alice = new Machine($aliceClient, new MemoryCryptoStore(), '@alice:hs', 'ALICE', txnIdFactory: static fn () => 'txn');
		$this->bob = new Machine($bobClient, $this->bobStore, '@bob:hs', 'BOB', txnIdFactory: static fn () => 'txn');
		$this->alice->publishKeys(true);
		$this->bob->publishKeys(true);
		$this->alice->flush();
		$this->bob->flush();
	}

	public function testDeviceKeysAreUploadedEvenWhenOnlyCountsArePublished(): void {
		$factory = new Psr17Factory();
		$hs = new FakeHomeserver('@carol:hs', 'CAROL');
		$machine = new Machine(new Client((new Transport('https://hs', $hs, $factory, $factory))->withAccessToken('c')), new MemoryCryptoStore(), '@carol:hs', 'CAROL');
		$machine->publishKeys(false, ['signed_curve25519' => 0]);
		self::assertArrayHasKey('CAROL', $hs->deviceKeys['@carol:hs'], 'device keys are published on the first upload no matter what');
		$hs->deviceKeys = [];
		$machine->publishKeys(false, ['signed_curve25519' => 0]);
		self::assertSame([], $hs->deviceKeys, 'and not again afterwards');
	}

	public function testKeysArePublished(): void {
		self::assertArrayHasKey('ALICE', $this->hsAlice->deviceKeys['@alice:hs']);
		self::assertCount(Machine::MIN_ONE_TIME_KEYS, $this->hsAlice->oneTimeKeys['@alice:hs']['ALICE']);
		self::assertCount(1, $this->hsAlice->fallbackKeys['@alice:hs']['ALICE']);
		// A later publish with enough keys on the server uploads nothing new
		$before = count($this->hsAlice->oneTimeKeys['@alice:hs']['ALICE']);
		$this->alice->publishKeys(false, ['signed_curve25519' => 60]);
		self::assertSame($before, count($this->hsAlice->oneTimeKeys['@alice:hs']['ALICE']));
		$this->alice->publishKeys(false, ['signed_curve25519' => 10]);
		self::assertSame($before + 40, count($this->hsAlice->oneTimeKeys['@alice:hs']['ALICE']));
	}

	public function testRoomKeySharingAndDecryption(): void {
		$roomId = '!room:hs';
		$devicesBefore = $this->alice->devicesFor(['@bob:hs']);
		self::assertArrayHasKey('BOB', $devicesBefore['@bob:hs']);
		self::assertSame(Trust::UNKNOWN, $this->alice->getAccount() ? Trust::UNKNOWN : Trust::UNKNOWN);

		// Alice encrypts a room message: creates the session and shares it with Bob via Olm
		$result = $this->alice->encryptRoomEvent($roomId, 'm.room.message', ['msgtype' => 'm.text', 'body' => 'hi bob'], ['@alice:hs', '@bob:hs'], 1000, null, null);
		self::assertSame(1, $result['sharedWith']);
		self::assertSame(0, $result['unreachable']);
		self::assertSame('m.megolm.v1.aes-sha2', $result['content']['algorithm']);
		self::assertCount(1, $this->hsAlice->toDevice);
		self::assertSame('m.room.encrypted', $this->hsAlice->toDevice[0]['type']);
		$olmContent = $this->hsAlice->toDevice[0]['messages']['@bob:hs']['BOB'];

		// Bob cannot read it yet
		try {
			$this->bob->decryptRoomEvent($roomId, $result['content']);
			self::fail('expected missing session');
		} catch (MissingSessionException $e) {
			self::assertSame($result['content']['session_id'], $e->sessionId);
		}

		// Bob receives the to-device room key (pre-key Olm message → new inbound session)
		$payload = $this->bob->decryptToDevice('@alice:hs', $olmContent);
		self::assertNotNull($payload);
		self::assertSame('m.room_key', $payload['type']);
		self::assertSame('@alice:hs', $payload['sender']);
		self::assertSame($this->alice->getSigningKey(), $payload['claimedEd25519']);
		self::assertTrue($this->bob->receiveRoomKey($payload['content'], $payload['senderKey'], $payload['claimedEd25519'], '@alice:hs', 'ALICE'));
		self::assertFalse($this->bob->receiveRoomKey($payload['content'], $payload['senderKey'], $payload['claimedEd25519'], '@alice:hs', 'ALICE'), 'duplicate key is ignored');

		$decrypted = $this->bob->decryptRoomEvent($roomId, $result['content']);
		self::assertSame('m.room.message', $decrypted['type']);
		self::assertSame('hi bob', $decrypted['content']['body']);
		self::assertSame(0, $decrypted['index']);

		// Second message reuses the session, no new key share
		$second = $this->alice->encryptRoomEvent($roomId, 'm.room.message', ['msgtype' => 'm.text', 'body' => 'again'], ['@alice:hs', '@bob:hs'], 1001, null, null);
		self::assertSame(0, $second['sharedWith']);
		self::assertCount(1, $this->hsAlice->toDevice);
		self::assertSame('again', $this->bob->decryptRoomEvent($roomId, $second['content'])['content']['body']);

		// Alice reads her own messages back
		self::assertSame('hi bob', $this->alice->decryptRoomEvent($roomId, $result['content'])['content']['body']);

		// Bob answers in the room: Olm session is reused (normal message), Alice gets Bob's key
		$bobMsg = $this->bob->encryptRoomEvent($roomId, 'm.room.message', ['msgtype' => 'm.text', 'body' => 'hello alice'], ['@alice:hs', '@bob:hs'], 1002, null, null);
		$olmToAlice = $this->hsBob->toDevice[1]['messages']['@alice:hs']['ALICE'];
		self::assertSame(1, $olmToAlice['ciphertext'][$this->alice->getIdentityKey()]['type'], 'established Olm session → normal message');
		$payload = $this->alice->decryptToDevice('@bob:hs', $olmToAlice);
		self::assertTrue($this->alice->receiveRoomKey($payload['content'], $payload['senderKey'], $payload['claimedEd25519'], '@bob:hs', 'BOB'));
		self::assertSame('hello alice', $this->alice->decryptRoomEvent($roomId, $bobMsg['content'])['content']['body']);
	}

	public function testRotationOnMemberLeaveAndMessageCount(): void {
		$roomId = '!room:hs';
		$first = $this->alice->encryptRoomEvent($roomId, 'm.room.message', ['body' => '1'], ['@alice:hs', '@bob:hs'], 1000, null, 2);
		$second = $this->alice->encryptRoomEvent($roomId, 'm.room.message', ['body' => '2'], ['@alice:hs', '@bob:hs'], 1000, null, 2);
		self::assertSame($first['content']['session_id'], $second['content']['session_id']);
		$third = $this->alice->encryptRoomEvent($roomId, 'm.room.message', ['body' => '3'], ['@alice:hs', '@bob:hs'], 1000, null, 2);
		self::assertNotSame($first['content']['session_id'], $third['content']['session_id'], 'rotated after rotation_period_msgs');

		$fourth = $this->alice->encryptRoomEvent($roomId, 'm.room.message', ['body' => '4'], ['@alice:hs'], 1000, null, 100);
		self::assertNotSame($third['content']['session_id'], $fourth['content']['session_id'], 'rotated because bob left');
	}

	public function testBlockedDevicesGetNoKeys(): void {
		$this->alice->devicesFor(['@bob:hs']);
		$store = new \ReflectionProperty(Machine::class, 'store');
		/** @var MemoryCryptoStore $aliceStore */
		$aliceStore = $store->getValue($this->alice);
		$aliceStore->setDeviceTrust('@bob:hs', 'BOB', Trust::BLOCKED);
		$result = $this->alice->encryptRoomEvent('!r:hs', 'm.room.message', ['body' => 'x'], ['@alice:hs', '@bob:hs'], 1000, null, null);
		self::assertSame(0, $result['sharedWith']);
		self::assertSame([], $this->hsAlice->toDevice);
	}

	public function testVerifiedOnlyPolicy(): void {
		$result = $this->alice->encryptRoomEvent('!r:hs', 'm.room.message', ['body' => 'x'], ['@alice:hs', '@bob:hs'], 1000, null, null, true);
		self::assertSame(0, $result['sharedWith'], 'unknown devices are skipped when only trusted devices may receive keys');
	}
}
