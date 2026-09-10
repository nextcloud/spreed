<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Nextcloud\Matrix\Model\Mxc;
use Nextcloud\Matrix\Util\Canonical;
use Nextcloud\Matrix\Util\Identifier;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase {
	public function testValidation(): void {
		self::assertTrue(Identifier::isUserId('@alice:example.org'));
		self::assertTrue(Identifier::isUserId('@alice:example.org:8448'));
		self::assertFalse(Identifier::isUserId('alice:example.org'));
		self::assertFalse(Identifier::isUserId('@alice'));
		self::assertTrue(Identifier::isRoomId('!abc:example.org'));
		self::assertTrue(Identifier::isRoomId('!abcDEF')); // room v12 ids have no server part
		self::assertTrue(Identifier::isRoomAlias('#room:example.org'));
		self::assertTrue(Identifier::isEventId('$abc'));
		self::assertSame('example.org', Identifier::serverName('@alice:example.org'));
		self::assertSame('alice', Identifier::localpart('@alice:example.org'));
	}

	public function testNormalizeUserId(): void {
		self::assertSame('@alice:hs.org', Identifier::normalizeUserId('alice', 'hs.org'));
		self::assertSame('@alice:hs.org', Identifier::normalizeUserId('@alice:hs.org', 'other.org'));
		self::assertSame('@alice:hs.org', Identifier::normalizeUserId('alice:hs.org', 'other.org'));
		self::assertSame('@alice:hs.org', Identifier::normalizeUserId('alice@hs.org', 'other.org'));
		$this->expectException(\InvalidArgumentException::class);
		Identifier::normalizeUserId('not valid', 'hs.org');
	}

	public function testParseRoomReference(): void {
		self::assertSame(['type' => 'room', 'id' => '!r:hs', 'via' => []], Identifier::parseRoomReference('!r:hs'));
		self::assertSame(['type' => 'alias', 'id' => '#a:hs', 'via' => ['x.org']], Identifier::parseRoomReference('https://matrix.to/#/%23a%3Ahs?via=x.org'));
		self::assertSame(['type' => 'alias', 'id' => '#a:hs', 'via' => []], Identifier::parseRoomReference('matrix:r/a:hs'));
		self::assertSame(['type' => 'room', 'id' => '!r:hs', 'via' => ['a.org', 'b.org']], Identifier::parseRoomReference('matrix:roomid/r:hs?via=a.org&via=b.org'));
		self::assertSame(['type' => 'user', 'id' => '@u:hs', 'via' => []], Identifier::parseRoomReference('https://matrix.to/#/@u:hs'));
		$this->expectException(\InvalidArgumentException::class);
		Identifier::parseRoomReference('hello');
	}

	public function testMxc(): void {
		$mxc = Mxc::parse('mxc://hs.org/abc123');
		self::assertSame('hs.org', $mxc->serverName);
		self::assertSame('abc123', $mxc->mediaId);
		self::assertSame('mxc://hs.org/abc123', (string)$mxc);
		self::assertFalse(Mxc::isValid('https://evil/x'));
	}

	public function testCanonicalJson(): void {
		// Examples from the specification appendix
		self::assertSame('{}', Canonical::encode([]));
		self::assertSame('{"one":1,"two":"Two"}', Canonical::encode(['two' => 'Two', 'one' => 1]));
		self::assertSame('{"a":"1","b":"2"}', Canonical::encode(['b' => '2', 'a' => '1']));
		self::assertSame('{"auth":{"mxid":"@john.doe:example.com","profile":{"display_name":"John Doe","three_pids":[{"address":"john.doe@example.org","medium":"email"},{"address":"123456789","medium":"msisdn"}]},"success":true}}',
			Canonical::encode(['auth' => ['success' => true, 'mxid' => '@john.doe:example.com', 'profile' => ['display_name' => 'John Doe', 'three_pids' => [['medium' => 'email', 'address' => 'john.doe@example.org'], ['medium' => 'msisdn', 'address' => '123456789']]]]]));
		self::assertSame('{"a":"日本語"}', Canonical::encode(['a' => '日本語']));
		self::assertSame("{\"a\":\"\u{1F600}\"}", Canonical::encode(['a' => "\u{1F600}"]));
		self::assertSame('{"a":null}', Canonical::encode(['a' => null]));
		// PHP cannot distinguish an empty list from an empty map; empty maps are far more common in signed content
		self::assertSame('{"a":{}}', Canonical::encode(['a' => []]));
	}
}
