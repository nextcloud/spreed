<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit\Crypto;

use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\Keys;
use Nextcloud\Matrix\Crypto\Verification\Emoji;
use Nextcloud\Matrix\Crypto\Verification\SasVerification;
use PHPUnit\Framework\TestCase;

final class SasVerificationTest extends TestCase {
	public function testEmojiAndDecimalDerivation(): void {
		// 6 bytes → 7 six-bit indices: 0b000000 000001 000010 000011 000100 000101 000110
		$bytes = "\x00\x10\x83\x10\x51\x87";
		$emoji = Emoji::fromSasBytes($bytes);
		self::assertSame(['Dog', 'Cat', 'Lion', 'Horse', 'Unicorn', 'Pig', 'Elephant'], array_column($emoji, 'name'));
		self::assertSame([1000 + 0b0000000000010, 1000 + (0b000 << 10 | 0x83 << 2 | 0x10 >> 6), 1000 + ((0x10 & 0x3F) << 7 | 0x51 >> 1)], Emoji::decimalFromSasBytes($bytes));
		self::assertCount(64, Emoji::TABLE);
	}

	public function testFullFlowBetweenTwoDevices(): void {
		$user = '@alice:hs';
		$keyA = Base64::encode(Keys::ed25519KeyPair()['public']);
		$keyB = Base64::encode(Keys::ed25519KeyPair()['public']);
		$lookup = static fn (string $device) => $device === 'DEVA' ? $keyA : ($device === 'DEVB' ? $keyB : null);

		// A (Talk) requests; B is the existing client
		$a = SasVerification::request('txn1', $user, 'DEVA', $keyA, 1000);
		[$request] = $a->takeOutgoing();
		self::assertSame('m.key.verification.request', $request['type']);

		// B answers ready; A starts; B accepts; keys are exchanged
		$b = new SasVerification('txn1', $user, 'DEVB', $keyB, 1000);
		$b->state = SasVerification::STATE_READY; // B side after sending ready
		$a->handle('m.key.verification.ready', ['from_device' => 'DEVB', 'methods' => ['m.sas.v1'], 'transaction_id' => 'txn1'], $lookup);
		[$start] = $a->takeOutgoing();
		self::assertSame('m.key.verification.start', $start['type']);
		self::assertSame(SasVerification::STATE_STARTED, $a->state);

		$b->handle('m.key.verification.start', $start['content'], $lookup);
		[$accept] = $b->takeOutgoing();
		self::assertSame('m.key.verification.accept', $accept['type']);
		self::assertSame('hkdf-hmac-sha256.v2', $accept['content']['message_authentication_code']);

		$a->handle('m.key.verification.accept', $accept['content'], $lookup);
		[$keyFromA] = $a->takeOutgoing();
		self::assertSame('m.key.verification.key', $keyFromA['type']);
		$b->handle('m.key.verification.key', $keyFromA['content'], $lookup);
		[$keyFromB] = $b->takeOutgoing();
		self::assertSame('m.key.verification.key', $keyFromB['type']);
		$a->handle('m.key.verification.key', $keyFromB['content'], $lookup);

		self::assertSame(SasVerification::STATE_KEYS_EXCHANGED, $a->state);
		self::assertSame(SasVerification::STATE_KEYS_EXCHANGED, $b->state);
		self::assertSame($a->emoji(), $b->emoji(), 'both sides show the same emoji');
		self::assertSame($a->decimal(), $b->decimal());
		self::assertCount(7, $a->emoji());

		// Both users confirm; MACs cross; done
		$a->confirm();
		[$macA] = $a->takeOutgoing();
		self::assertSame('m.key.verification.mac', $macA['type']);
		$b->handle('m.key.verification.mac', $macA['content'], $lookup);
		self::assertSame(SasVerification::STATE_KEYS_EXCHANGED, $b->state, 'B waits for its own confirmation');
		$b->confirm();
		$outB = $b->takeOutgoing();
		self::assertSame(['m.key.verification.mac', 'm.key.verification.done'], array_column($outB, 'type'));
		self::assertSame(SasVerification::STATE_DONE, $b->state);
		$a->handle('m.key.verification.mac', $outB[0]['content'], $lookup);
		self::assertSame(SasVerification::STATE_DONE, $a->state);
		self::assertSame(['m.key.verification.done'], array_column($a->takeOutgoing(), 'type'));
		self::assertSame($keyB, $a->getTheirEd25519());

		// Pickle round trip keeps state
		self::assertSame(SasVerification::STATE_DONE, SasVerification::unpickle($a->pickle())->state);
	}

	public function testTamperedMacCancels(): void {
		$user = '@alice:hs';
		$keyA = Base64::encode(Keys::ed25519KeyPair()['public']);
		$keyB = Base64::encode(Keys::ed25519KeyPair()['public']);
		$lookup = static fn (string $device) => $device === 'DEVA' ? $keyA : $keyB;
		$a = SasVerification::request('t', $user, 'DEVA', $keyA, 1);
		$a->takeOutgoing();
		$b = new SasVerification('t', $user, 'DEVB', $keyB, 1);
		$b->state = SasVerification::STATE_READY;
		$a->handle('m.key.verification.ready', ['from_device' => 'DEVB', 'methods' => ['m.sas.v1']], $lookup);
		$b->handle('m.key.verification.start', $a->takeOutgoing()[0]['content'], $lookup);
		$a->handle('m.key.verification.accept', $b->takeOutgoing()[0]['content'], $lookup);
		$b->handle('m.key.verification.key', $a->takeOutgoing()[0]['content'], $lookup);
		$a->handle('m.key.verification.key', $b->takeOutgoing()[0]['content'], $lookup);
		$a->confirm();
		$mac = $a->takeOutgoing()[0]['content'];
		$mac['keys'] = 'AAAA';
		$b->handle('m.key.verification.mac', $mac, $lookup);
		self::assertSame(SasVerification::STATE_CANCELLED, $b->state);
		self::assertSame('m.key.verification.cancel', $b->takeOutgoing()[0]['type']);
	}
}
