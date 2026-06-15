<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php;

use OCA\Talk\TalkSession;
use OCP\IRequest;
use OCP\ISession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TalkSessionTest extends TestCase {
	protected ISession&MockObject $session;
	protected IRequest&MockObject $request;
	protected ?TalkSession $talkSession = null;

	public function setUp(): void {
		parent::setUp();

		$this->session = $this->createMock(ISession::class);
		$this->request = $this->createMock(IRequest::class);
		$this->talkSession = new TalkSession(
			$this->session,
			$this->request,
		);
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
			'remove data' => [json_encode(['t2' => 'd2', 't1' => 'd1', '12345' => 'd3']), json_encode(['t2' => 'd2', '12345' => 'd3'])],
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

	/**
	 * The password is stored during the HTML page request (no x-nextcloud-talk-session-tab-id
	 * header) and read back during a subsequent API call (which carries the header via the
	 * axios interceptor set up in init.js). The methods must ignore the tab-ID so that the
	 * two contexts share the same session key.
	 */
	public function testGetPasswordForRoomIgnoresTabId(): void {
		$this->request->method('getHeader')
			->with(TalkSession::HEADER_TAB_ID)
			->willReturn(str_repeat('a', 64));

		// Password was stored without a tab-ID suffix (page-request context)
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-password')
			->willReturn(json_encode(['t1' => 'secret']));

		// Must resolve to the value even though a tab-ID header is now present
		$this->assertSame('secret', $this->talkSession->getPasswordForRoom('t1'));
	}

	public function testSetPasswordForRoomIgnoresTabId(): void {
		$this->request->method('getHeader')
			->with(TalkSession::HEADER_TAB_ID)
			->willReturn(str_repeat('a', 64));

		$this->session->expects($this->once())
			->method('get')
			->with('spreed-password')
			->willReturn(null);
		// Key must be the bare token, not 't1$<tabId>'
		$this->session->expects($this->once())
			->method('set')
			->with('spreed-password', json_encode(['t1' => 'secret']));

		$this->talkSession->setPasswordForRoom('t1', 'secret');
	}

	public function testRemovePasswordForRoomIgnoresTabId(): void {
		$this->request->method('getHeader')
			->with(TalkSession::HEADER_TAB_ID)
			->willReturn(str_repeat('a', 64));

		$this->session->expects($this->once())
			->method('get')
			->with('spreed-password')
			->willReturn(json_encode(['t1' => 'secret', 't2' => 'other']));
		// 't1' must be removed by bare key, not 't1$<tabId>'
		$this->session->expects($this->once())
			->method('set')
			->with('spreed-password', json_encode(['t2' => 'other']));

		$this->talkSession->removePasswordForRoom('t1');
	}
}
