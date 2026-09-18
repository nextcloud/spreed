<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit\Crypto;

use Nextcloud\Matrix\Crypto\Attachment;
use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Keys;
use Nextcloud\Matrix\Crypto\Megolm\InboundSession;
use Nextcloud\Matrix\Crypto\Megolm\OutboundSession;
use Nextcloud\Matrix\Crypto\Megolm\Ratchet;
use Nextcloud\Matrix\Crypto\Olm\Account;
use Nextcloud\Matrix\Crypto\Olm\Message;
use Nextcloud\Matrix\Crypto\Olm\Session;
use Nextcloud\Matrix\Crypto\Wire;
use PHPUnit\Framework\TestCase;

final class PrimitivesTest extends TestCase {
	public function testBase64(): void {
		self::assertSame('aGVsbG8', Base64::encode('hello'));
		self::assertSame('hello', Base64::decode('aGVsbG8'));
		self::assertSame('hello', Base64::decode('aGVsbG8='));
		self::assertSame('_-8', Base64::encodeUrl("\xff\xef"));
		self::assertSame("\xff\xef", Base64::decode('_-8'));
		$this->expectException(CryptoException::class);
		Base64::decode('***');
	}

	public function testWire(): void {
		self::assertSame("\x00", Wire::varint(0));
		self::assertSame("\x7f", Wire::varint(127));
		self::assertSame("\x80\x01", Wire::varint(128));
		self::assertSame("\xac\x02", Wire::varint(300));
		self::assertSame([300, 2], Wire::readVarint("\xac\x02", 0));
		$encoded = Wire::int(0x08, 5) . Wire::bytes(0x12, 'abc');
		self::assertSame([0x08 => 5, 0x12 => 'abc'], Wire::parse($encoded, 0, strlen($encoded)));
	}

	public function testJsonSigning(): void {
		$pair = Keys::ed25519KeyPair();
		$signed = Keys::signJson(['b' => 2, 'a' => 1, 'unsigned' => ['x' => 1]], '@u:hs', 'ed25519:DEV', $pair['secret']);
		self::assertArrayHasKey('ed25519:DEV', $signed['signatures']['@u:hs']);
		self::assertTrue(Keys::verifyJson($signed, '@u:hs', 'ed25519:DEV', $pair['public']));
		$signed['a'] = 2;
		self::assertFalse(Keys::verifyJson($signed, '@u:hs', 'ed25519:DEV', $pair['public']));
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

	public function testOlmRoundTripBothDirections(): void {
		$alice = Account::create();
		$bob = Account::create();
		$bob->generateOneTimeKeys(1);
		$bobOtk = Base64::decode(array_values($bob->getUnpublishedOneTimeKeys())[0]);

		$outbound = Session::createOutbound($alice, $bob->getIdentityKey(), $bobOtk);
		$first = $outbound->encrypt('hello bob');
		self::assertSame(Message::TYPE_PREKEY, $first['type']);
		$second = $outbound->encrypt('again');
		self::assertSame(Message::TYPE_PREKEY, $second['type'], 'stays pre-key until bob answers');

		$inbound = Session::createInbound($bob, Base64::decode($first['body']), $alice->getIdentityKey());
		self::assertSame($outbound->getId(), $inbound->getId());
		self::assertSame(0, $bob->countOneTimeKeys(), 'one-time key consumed');
		self::assertTrue($inbound->matchesPreKeyMessage(Base64::decode($second['body'])));
		self::assertSame('hello bob', $inbound->decrypt($first['type'], $first['body']));
		self::assertSame('again', $inbound->decrypt($second['type'], $second['body']));

		// Bob replies → ratchet advances; Alice decrypts; then Alice sends normal messages
		$reply = $inbound->encrypt('hi alice');
		self::assertSame(Message::TYPE_NORMAL, $reply['type']);
		self::assertSame('hi alice', $outbound->decrypt($reply['type'], $reply['body']));
		$third = $outbound->encrypt('third');
		self::assertSame(Message::TYPE_NORMAL, $third['type']);
		self::assertSame('third', $inbound->decrypt($third['type'], $third['body']));

		// Out-of-order delivery and pickling
		$m4 = $inbound->encrypt('four');
		$m5 = $inbound->encrypt('five');
		$restored = Session::unpickle($outbound->pickle());
		self::assertSame('five', $restored->decrypt($m5['type'], $m5['body']));
		self::assertSame('four', $restored->decrypt($m4['type'], $m4['body']));
		$this->expectException(CryptoException::class);
		$restored->decrypt($m4['type'], $m4['body']); // replay
	}

	public function testOlmTamperingDetected(): void {
		$alice = Account::create();
		$bob = Account::create();
		$bob->generateOneTimeKeys(1);
		$outbound = Session::createOutbound($alice, $bob->getIdentityKey(), Base64::decode(array_values($bob->getUnpublishedOneTimeKeys())[0]));
		$msg = $outbound->encrypt('secret');
		$raw = Base64::decode($msg['body']);
		$raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0x01);
		$inbound = Session::createInbound($bob, $raw);
		$this->expectException(CryptoException::class);
		$inbound->decrypt(Message::TYPE_PREKEY, Base64::encode($raw));
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

	public function testMegolmRoundTripAndExport(): void {
		$outbound = OutboundSession::create(1000);
		$inbound = InboundSession::fromSessionKey($outbound->sessionKey());
		self::assertSame($outbound->getId(), $inbound->getId());
		self::assertTrue($inbound->isSignatureVerified());
		$c0 = $outbound->encrypt('{"a":0}');
		$c1 = $outbound->encrypt('{"a":1}');
		self::assertSame(['plaintext' => '{"a":1}', 'index' => 1], $inbound->decrypt($c1));
		self::assertSame(['plaintext' => '{"a":0}', 'index' => 0], $inbound->decrypt($c0), 'older messages stay decryptable');

		// Key shared later only covers newer messages
		$late = InboundSession::fromSessionKey($outbound->sessionKey());
		self::assertSame(2, $late->getFirstKnownIndex());
		$c2 = $outbound->encrypt('{"a":2}');
		self::assertSame('{"a":2}', $late->decrypt($c2)['plaintext']);
		try {
			$late->decrypt($c1);
			self::fail('should not decrypt before first known index');
		} catch (CryptoException) {
		}

		$exported = InboundSession::fromExportedKey($late->export());
		self::assertFalse($exported->isSignatureVerified());
		self::assertSame('{"a":2}', $exported->decrypt($c2)['plaintext']);
		$restored = InboundSession::unpickle($inbound->pickle());
		self::assertSame('{"a":0}', $restored->decrypt($c0)['plaintext']);
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

	public function testAttachmentRoundTrip(): void {
		$encrypted = Attachment::encrypt('file content');
		self::assertSame('file content', Attachment::decrypt($encrypted['file'], $encrypted['ciphertext']));
		$this->expectException(CryptoException::class);
		Attachment::decrypt($encrypted['file'], $encrypted['ciphertext'] . 'x');
	}
}
