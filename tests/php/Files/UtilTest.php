<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Files;

use OCA\Talk\Files\Util;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UtilTest extends TestCase {
	protected ISession&MockObject $session;
	protected IShareManager&MockObject $shareManager;
	protected Util $util;

	public function setUp(): void {
		parent::setUp();

		$this->session = $this->createMock(ISession::class);
		$this->shareManager = $this->createMock(IShareManager::class);

		$this->util = new Util(
			$this->createMock(IRootFolder::class),
			$this->session,
			$this->shareManager,
			$this->createMock(IUserMountCache::class),
		);
	}

	public static function dataCanGuestAccessFile(): array {
		return [
			'no password' => [null, null, true],
			'password, not authenticated' => ['hash', null, false],
			'password, legacy single id' => ['hash', '42', false],
			'password, other share authenticated' => ['hash', ['23'], false],
			'password, authenticated' => ['hash', ['23', '42'], true],
		];
	}

	#[DataProvider('dataCanGuestAccessFile')]
	public function testCanGuestAccessFile(?string $password, mixed $authenticated, bool $expected): void {
		$share = $this->createMock(IShare::class);
		$share->method('getId')->willReturn('42');
		$share->method('getPassword')->willReturn($password);
		$this->shareManager->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->session->method('get')
			->with('public_link_authenticated')
			->willReturn($authenticated);

		$this->assertSame($expected, $this->util->canGuestAccessFile('token'));
	}

	public function testCanGuestAccessFileShareNotFound(): void {
		$this->shareManager->method('getShareByToken')
			->willThrowException(new ShareNotFound());

		$this->assertFalse($this->util->canGuestAccessFile('token'));
	}
}
