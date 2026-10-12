<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client\Crypto;

use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\DeviceKeys;
use OCA\Talk\Matrix\Client\Crypto\Keys;
use OCA\Talk\Matrix\Client\Crypto\Megolm\InboundSession;
use OCA\Talk\Matrix\Client\Crypto\Megolm\OutboundSession;
use OCA\Talk\Matrix\Client\Crypto\Megolm\Ratchet;
use OCA\Talk\Matrix\Client\Crypto\MegolmEnvelope;
use OCA\Talk\Matrix\Client\Crypto\Olm\Account;
use OCA\Talk\Matrix\Client\Crypto\Olm\Message;
use OCA\Talk\Matrix\Client\Crypto\Olm\Session;
use OCA\Talk\Matrix\Client\Crypto\OlmEnvelope;
use OCA\Talk\Matrix\Client\Crypto\Wire;
use OCA\Talk\Matrix\Client\Util\Canonical;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class PrimitivesTest extends TestCase {
	public function testBase64(): void {
		self::assertSame('aGVsbG8', Base64::encode('hello'));
		self::assertSame('hello', Base64::decode('aGVsbG8'));
		self::assertSame('hello', Base64::decode('aGVsbG8='));
		$this->expectException(CryptoException::class);
		Base64::decode('***');
	}

	public function testCanonicalJson(): void {
		self::assertSame('{"a":1,"b":{"c":["y","x"],"d":"ä/"},"e":{}}', Canonical::encode(['e' => [], 'b' => ['d' => 'ä/', 'c' => ['y', 'x']], 'a' => 1.0]));
	}

	public function testWire(): void {
		self::assertSame("\x00", Wire::varint(0));
		self::assertSame("\x7f", Wire::varint(127));
		self::assertSame("\x80\x01", Wire::varint(128));
		self::assertSame("\xac\x02", Wire::varint(300));
		self::assertSame([300, 2], Wire::readVarint("\xac\x02", 0));
		self::assertSame([0xFFFFFFFF, 5], Wire::readVarint("\xff\xff\xff\xff\x0f", 0));
		$encoded = Wire::int(0x08, 5) . Wire::bytes(0x12, 'abc');
		self::assertSame([0x08 => 5, 0x12 => 'abc'], Wire::parse($encoded, 0, strlen($encoded)));
	}

	public static function dataInvalidVarint(): array {
		return [
			'truncated' => ["\x80"],
			'above 32 bits' => ["\xff\xff\xff\xff\x1f"],
			'too long' => ["\x80\x80\x80\x80\x80\x01"],
		];
	}

	#[DataProvider('dataInvalidVarint')]
	public function testInvalidVarint(string $data): void {
		$this->expectException(CryptoException::class);
		Wire::readVarint($data, 0);
	}

	public function testJsonSigning(): void {
		$pair = Keys::ed25519KeyPair();
		$signed = Keys::signJson(['b' => 2, 'a' => 1, 'unsigned' => ['x' => 1]], '@u:hs', 'ed25519:DEV', $pair['secret']);
		self::assertArrayHasKey('ed25519:DEV', $signed['signatures']['@u:hs']);
		self::assertTrue(Keys::verifyJson($signed, '@u:hs', 'ed25519:DEV', $pair['public']));
		$signed['a'] = 2;
		self::assertFalse(Keys::verifyJson($signed, '@u:hs', 'ed25519:DEV', $pair['public']));
	}

	public function testEcdhRefusesInvalidKeys(): void {
		$pair = Keys::curve25519KeyPair();
		try {
			Keys::ecdh($pair['secret'], 'short');
			self::fail('Key with a wrong length was accepted');
		} catch (CryptoException) {
		}
		$this->expectException(CryptoException::class);
		Keys::ecdh($pair['secret'], str_repeat("\x00", 32));
	}

	public function testAccountKeysUploadAndPickle(): void {
		$account = Account::create();
		$account->generateOneTimeKeys(5);
		$account->generateFallbackKey();
		$keys = $account->deviceKeys('@u:hs', 'DEV');
		self::assertSame(Base64::encode($account->getIdentityKey()), $keys['keys']['curve25519:DEV']);
		self::assertTrue(Keys::verifyJson($keys, '@u:hs', 'ed25519:DEV', $account->getSigningKey()));
		$upload = $account->keysForUpload('@u:hs', 'DEV');
		self::assertCount(5, $upload['one_time_keys']);
		self::assertCount(1, $upload['fallback_keys']);
		foreach ($upload['one_time_keys'] as $id => $otk) {
			self::assertStringStartsWith('signed_curve25519:', $id);
			self::assertTrue(Keys::verifyJson($otk, '@u:hs', 'ed25519:DEV', $account->getSigningKey()));
		}
		$account->markKeysAsPublished();
		self::assertSame([], $account->getUnpublishedOneTimeKeys());
		$restored = Account::unpickle($account->pickle());
		self::assertSame($account->getIdentityKeyBase64(), $restored->getIdentityKeyBase64());
		self::assertSame(5, $restored->countOneTimeKeys());
	}

	/**
	 * @return array{0: Account, 1: Account, 2: Session}
	 */
	private static function olmPair(): array {
		$alice = Account::create();
		$bob = Account::create();
		$bob->generateOneTimeKeys(1);
		$outbound = Session::createOutbound($alice, $bob->getIdentityKey(), Base64::decode(array_values($bob->getUnpublishedOneTimeKeys())[0]));
		return [$alice, $bob, $outbound];
	}

	public function testOlmRoundTripBothDirections(): void {
		[$alice, $bob, $outbound] = self::olmPair();
		$first = $outbound->encrypt('hello bob');
		self::assertSame(Message::TYPE_PREKEY, $first['type']);
		$second = $outbound->encrypt('again');
		self::assertSame(Message::TYPE_PREKEY, $second['type'], 'Stays a pre-key message until bob answers');

		['session' => $inbound, 'plaintext' => $plaintext] = Session::fromPreKeyMessage($bob, $first['body'], $alice->getIdentityKey());
		self::assertSame('hello bob', $plaintext);
		self::assertSame($outbound->getId(), $inbound->getId());
		self::assertSame(0, $bob->countOneTimeKeys(), 'One-time key is used up');
		self::assertTrue($inbound->matchesPreKeyMessage(Base64::decode($second['body'])));
		self::assertSame('again', $inbound->decrypt($second['type'], $second['body']));

		$reply = $inbound->encrypt('hi alice');
		self::assertSame(Message::TYPE_NORMAL, $reply['type']);
		self::assertSame('hi alice', $outbound->decrypt($reply['type'], $reply['body']));
		$third = $outbound->encrypt('third');
		self::assertSame(Message::TYPE_NORMAL, $third['type']);
		self::assertSame('third', $inbound->decrypt($third['type'], $third['body']));

		// Out of order delivery after pickling
		$m4 = $inbound->encrypt('four');
		$m5 = $inbound->encrypt('five');
		$restored = Session::unpickle($outbound->pickle());
		self::assertSame('five', $restored->decrypt($m5['type'], $m5['body']));
		self::assertSame('four', $restored->decrypt($m4['type'], $m4['body']));
		$this->expectException(CryptoException::class);
		$restored->decrypt($m4['type'], $m4['body']);
	}

	public function testForgedPreKeyMessageKeepsOneTimeKey(): void {
		[$alice, $bob, $outbound] = self::olmPair();
		$raw = Base64::decode($outbound->encrypt('secret')['body']);
		$raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0x01);

		try {
			Session::fromPreKeyMessage($bob, Base64::encode($raw));
			self::fail('Tampered message was decrypted');
		} catch (CryptoException) {
		}
		self::assertSame(1, $bob->countOneTimeKeys());
	}

	public function testPreKeyMessageOfOtherSenderIsRefused(): void {
		[, $bob, $outbound] = self::olmPair();
		$this->expectException(CryptoException::class);
		Session::fromPreKeyMessage($bob, $outbound->encrypt('secret')['body'], Account::create()->getIdentityKey());
	}

	public function testFailedDecryptionKeepsSession(): void {
		[, $bob, $outbound] = self::olmPair();
		['session' => $inbound] = Session::fromPreKeyMessage($bob, $outbound->encrypt('one')['body']);
		$outbound->decrypt(...array_values($inbound->encrypt('reply')));

		// A forged message with a new ratchet key and a wrong MAC must not advance the ratchet
		$message = $outbound->encrypt('two');
		$raw = Base64::decode($message['body']);
		$forged = Message::encodeBody(Keys::curve25519KeyPair()['public'], 0, 'garbage') . str_repeat("\x00", Message::MAC_LENGTH);
		try {
			$inbound->decrypt(Message::TYPE_NORMAL, Base64::encode($forged));
			self::fail('Forged message was decrypted');
		} catch (CryptoException) {
		}
		self::assertSame('two', $inbound->decrypt(Message::TYPE_NORMAL, Base64::encode($raw)));
	}

	public function testRatchetAdvanceToMatchesStepping(): void {
		$a = Ratchet::fromBytes(str_repeat("\x01", 128), 0);
		$b = Ratchet::fromBytes(str_repeat("\x01", 128), 0);
		for ($i = 0; $i < 600; $i++) {
			$a->advance();
		}
		$b->advanceTo(600);
		self::assertSame(600, $b->getCounter());
		self::assertSame($a->toBytes(), $b->toBytes());

		$c = Ratchet::fromBytes(str_repeat("\x01", 128), 0);
		$c->advanceTo(70000);
		$d = Ratchet::fromBytes(str_repeat("\x01", 128), 0);
		$d->advanceTo(65536);
		$d->advanceTo(70000);
		self::assertSame($c->toBytes(), $d->toBytes());
		$this->expectException(CryptoException::class);
		$d->advanceTo(1);
	}

	/**
	 * Computed with a separate port of libolm's megolm_advance_to, starting
	 * with the parts filled with 0x00, 0x01, 0x02 and 0x03
	 */
	public static function dataRatchetKnownAnswers(): array {
		return [
			[1, 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICOGFF3jhUO2Mrjrn8AXHq+tJbEUI/oAKsQIPAqPN1eG4='],
			[255, 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICfGWFtmS/AGEMg0/YXdgQReN8jEn2Mnnmy79u+PK14aE='],
			[256, 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAafTKqAG2kIb/VqcP5hwnTERaHBz7TGwX/lOCuGo73PNJIL7S1sSmkCaOfB9ia5mAD9nCOMhfMbmEFGBSddleSE='],
			[0x01020304, 'ZiCzHykkuMAVR3RfQYJdMiM2+D67E9cjZ4eJ1VTYo+8sZx+A0i67N4iSYISmQ161c+KezU5Rl6ZZkiw0R5bhBQfV7C4qecAl4s0rRce/yJc29GyDyTRkO2WY1rgYMvkOaJBagO0CZVncvhYwqU12x/lcmDo0mK/PDcYMPwRlzC8='],
		];
	}

	#[DataProvider('dataRatchetKnownAnswers')]
	public function testRatchetKnownAnswers(int $target, string $expected): void {
		$ratchet = Ratchet::fromBytes(str_repeat("\x00", 32) . str_repeat("\x01", 32) . str_repeat("\x02", 32) . str_repeat("\x03", 32), 0);
		$ratchet->advanceTo($target);
		self::assertSame($target, $ratchet->getCounter());
		self::assertSame($expected, base64_encode($ratchet->toBytes()));
	}

	public function testMegolmRoundTripAndExport(): void {
		$outbound = OutboundSession::create(1000);
		$inbound = InboundSession::fromSessionKey($outbound->sessionKey());
		self::assertSame($outbound->getId(), $inbound->getId());
		self::assertTrue($inbound->isSignatureVerified());
		$c0 = $outbound->encrypt('{"a":0}');
		$c1 = $outbound->encrypt('{"a":1}');
		self::assertSame(['plaintext' => '{"a":1}', 'index' => 1], $inbound->decrypt($c1));
		self::assertSame(['plaintext' => '{"a":0}', 'index' => 0], $inbound->decrypt($c0), 'Older messages stay decryptable');

		$late = InboundSession::fromSessionKey($outbound->sessionKey());
		self::assertSame(2, $late->getFirstKnownIndex());
		$c2 = $outbound->encrypt('{"a":2}');
		self::assertSame('{"a":2}', $late->decrypt($c2)['plaintext']);
		try {
			$late->decrypt($c1);
			self::fail('Message before the first known index was decrypted');
		} catch (CryptoException) {
		}

		$exported = InboundSession::fromExportedKey($late->export());
		self::assertFalse($exported->isSignatureVerified());
		self::assertSame('{"a":2}', $exported->decrypt($c2)['plaintext']);
		self::assertSame('{"a":0}', InboundSession::unpickle($inbound->pickle())->decrypt($c0)['plaintext']);
		self::assertSame(3, OutboundSession::unpickle($outbound->pickle())->getMessageIndex());
	}

	public function testMegolmTamperingDetected(): void {
		$outbound = OutboundSession::create(0);
		$inbound = InboundSession::fromSessionKey($outbound->sessionKey());
		$raw = Base64::decode($outbound->encrypt('x'));
		$raw[5] = chr(ord($raw[5]) ^ 0xFF);
		$this->expectException(CryptoException::class);
		$inbound->decrypt(Base64::encode($raw));
	}

	public function testForgedSessionKeyIsRefused(): void {
		$raw = Base64::decode(OutboundSession::create(0)->sessionKey());
		$raw[10] = chr(ord($raw[10]) ^ 0x01);
		$this->expectException(CryptoException::class);
		InboundSession::fromSessionKey(Base64::encode($raw));
	}

	public function testMegolmEnvelopeKeepsRelationUnencrypted(): void {
		$account = Account::create();
		$outbound = OutboundSession::create(0);
		$inbound = InboundSession::fromSessionKey(MegolmEnvelope::roomKeyContent($outbound, '!room:hs')['session_key']);
		$relation = ['rel_type' => 'm.thread', 'event_id' => '$root'];
		$encrypted = MegolmEnvelope::encrypt($outbound, $account, 'DEV', '!room:hs', 'm.room.message', ['body' => 'Hi', 'm.relates_to' => $relation]);
		self::assertSame($relation, $encrypted['m.relates_to']);
		self::assertSame($outbound->getId(), $encrypted['session_id']);

		self::assertSame(
			['type' => 'm.room.message', 'content' => ['body' => 'Hi', 'm.relates_to' => $relation], 'index' => 0],
			MegolmEnvelope::decrypt($inbound, $encrypted, '!room:hs'),
		);
		$this->expectException(CryptoException::class);
		MegolmEnvelope::decrypt($inbound, $encrypted, '!other:hs');
	}

	public function testOlmEnvelope(): void {
		[$alice, $bob, $outbound] = self::olmPair();
		$bobDevice = DeviceKeys::fromArray('@bob:hs', 'BOB', $bob->deviceKeys('@bob:hs', 'BOB'));
		$encrypted = OlmEnvelope::encrypt($alice, '@alice:hs', 'ALICE', $outbound, $bobDevice, 'm.room_key', ['session_id' => 'x']);
		self::assertSame($alice->getIdentityKeyBase64(), $encrypted['sender_key']);

		$ciphertext = OlmEnvelope::ourCiphertext($encrypted, $bob);
		['plaintext' => $plaintext] = Session::fromPreKeyMessage($bob, $ciphertext['body'], Base64::decode($encrypted['sender_key']));
		self::assertSame([
			'type' => 'm.room_key',
			'content' => ['session_id' => 'x'],
			'sender' => '@alice:hs',
			'senderDevice' => 'ALICE',
			'claimedEd25519' => $alice->getSigningKeyBase64(),
		], OlmEnvelope::validatePayload($plaintext, '@alice:hs', $bob, '@bob:hs'));

		$this->expectException(CryptoException::class);
		OlmEnvelope::validatePayload($plaintext, '@mallory:hs', $bob, '@bob:hs');
	}

	public function testDeviceKeys(): void {
		$account = Account::create();
		$device = DeviceKeys::fromArray('@u:hs', 'DEV', $account->deviceKeys('@u:hs', 'DEV'));
		self::assertSame($account->getIdentityKeyBase64(), $device->curve25519);
		self::assertSame($account->getSigningKeyBase64(), $device->ed25519);
		self::assertTrue($device->supportsOlm());
		self::assertTrue($device->supportsMegolm());

		$this->expectException(CryptoException::class);
		DeviceKeys::fromArray('@u:hs', 'OTHER', $account->deviceKeys('@u:hs', 'DEV'));
	}

	public function testDeviceKeysWithChangedKeyAreRefused(): void {
		$keys = Account::create()->deviceKeys('@u:hs', 'DEV');
		$keys['keys']['curve25519:DEV'] = Account::create()->getIdentityKeyBase64();
		$this->expectException(CryptoException::class);
		DeviceKeys::fromArray('@u:hs', 'DEV', $keys);
	}
}
