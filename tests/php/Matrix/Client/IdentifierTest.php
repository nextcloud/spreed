<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Util\Identifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class IdentifierTest extends TestCase {
	public static function dataParseRoomReference(): array {
		return [
			'alias' => ['#room:example.org', ['#room:example.org', []]],
			'room id' => ['!abc:example.org', ['!abc:example.org', []]],
			'room id without server' => ['!abc', ['!abc', []]],
			'matrix.to alias' => ['https://matrix.to/#/%23room:example.org', ['#room:example.org', []]],
			'matrix.to room id with servers' => ['https://matrix.to/#/!abc:example.org?via=a.org&via=b.org', ['!abc:example.org', ['a.org', 'b.org']]],
			'matrix uri' => ['matrix:r/room:example.org', ['#room:example.org', []]],
			'matrix uri room id' => ['matrix:roomid/abc:example.org?via=a.org', ['!abc:example.org', ['a.org']]],
		];
	}

	#[DataProvider('dataParseRoomReference')]
	public function testParseRoomReference(string $input, array $expected): void {
		self::assertSame($expected, Identifier::parseRoomReference($input));
	}

	public static function dataParseRoomReferenceInvalid(): array {
		return [
			'plain text' => ['room'],
			'alias without server' => ['#room'],
			'user' => ['@alice:example.org'],
			'whitespace' => ['#my room:example.org'],
			'other link' => ['https://example.org/#/#room:example.org'],
		];
	}

	#[DataProvider('dataParseRoomReferenceInvalid')]
	public function testParseRoomReferenceInvalid(string $input): void {
		$this->expectException(\InvalidArgumentException::class);
		Identifier::parseRoomReference($input);
	}
}
