<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php;

use OCA\Talk\TalkSession;
use OCP\ISession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TalkSessionTest extends TestCase {
	protected ISession&MockObject $session;
	protected ?TalkSession $talkSession = null;

	public function setUp(): void {
		parent::setUp();

		$this->session = $this->createMock(ISession::class);
		$this->talkSession = new TalkSession($this->session);
	}

	public static function dataGet(): array {
		return [
			'session is null' => [null, null],
			'corrupted json' => ['{invalid json', null],
			'no data for token' => [json_encode(['t2' => 'd2']), null],
			'valid case' => [json_encode(['t1' => 'd1']), 'd1'],
		];
	}

	#[DataProvider('dataGet')]
	public function testGetSessionForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn($sessionData);
		$this->assertSame($expected, $this->talkSession->getSessionForRoom('t1'));
	}

	#[DataProvider('dataGet')]
	public function testGetPasswordForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-password')
			->willReturn($sessionData);
		$this->assertSame($expected, $this->talkSession->getPasswordForRoom('t1'));
	}

	public static function dataSet(): array {
		return [
			'session is null' => [null, json_encode(['t1' => 'd1'])],
			'corrupted json' => ['{invalid json', json_encode(['t1' => 'd1'])],
			'no data for token' => [json_encode(['t2' => 'd2']), json_encode(['t2' => 'd2', 't1' => 'd1'])],
			'update data' => [json_encode(['t1' => 'd2']), json_encode(['t1' => 'd1'])],
		];
	}

	#[DataProvider('dataSet')]
	public function testSetSessionForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn($sessionData);
		$this->session->expects($this->once())
			->method('set')
			->with('spreed-session', $expected);
		$this->talkSession->setSessionForRoom('t1', 'd1');
	}

	#[DataProvider('dataSet')]
	public function testSetPasswordForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-password')
			->willReturn($sessionData);
		$this->session->expects($this->once())
			->method('set')
			->with('spreed-password', $expected);
		$this->talkSession->setPasswordForRoom('t1', 'd1');
	}

	public static function dataRemove(): array {
		return [
			'session is null' => [null, json_encode([])],
			'corrupted json' => ['{invalid json', json_encode([])],
			'no data for token' => [json_encode(['t2' => 'd2']), json_encode(['t2' => 'd2'])],
			'remove data' => [json_encode(['t2' => 'd2', 't1' => 'd1']), json_encode(['t2' => 'd2'])],
		];
	}

	#[DataProvider('dataRemove')]
	public function testRemoveSessionForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn($sessionData);
		$this->session->expects($this->once())
			->method('set')
			->with('spreed-session', $expected);
		$this->talkSession->removeSessionForRoom('t1');
	}

	#[DataProvider('dataRemove')]
	public function testRemovePasswordForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-password')
			->willReturn($sessionData);
		$this->session->expects($this->once())
			->method('set')
			->with('spreed-password', $expected);
		$this->talkSession->removePasswordForRoom('t1');
	}
}
