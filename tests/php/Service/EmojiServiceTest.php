<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Service\EmojiService;
use OCP\IEmojiHelper;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class EmojiServiceTest extends TestCase {
	protected ?EmojiService $service = null;

	public function setUp(): void {
		parent::setUp();

		$this->service = new EmojiService(
			Server::get(IEmojiHelper::class),
		);
	}

	public static function dataGetFirstCombinedEmoji(): array {
		return [
			['👋 Hello', '👋'],
			['Only leading emojis 🚀', ''],
			['👩🏽‍💻👩🏻‍💻👨🏿‍💻 Only one, but with all attributes', '👩🏽‍💻'],
		];
	}

	#[DataProvider('dataGetFirstCombinedEmoji')]
	public function testGetFirstCombinedEmoji(string $roomName, string $avatarEmoji): void {
		$this->assertSame($avatarEmoji, self::invokePrivate($this->service, 'getFirstCombinedEmoji', [$roomName]));
	}
}
