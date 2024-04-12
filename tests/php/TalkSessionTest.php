<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Tests\php;

use OCA\Talk\TalkSession;
use OCP\ISession;
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

	/**
	 * @dataProvider dataGet
	 */
	public function testGetSessionForRoom(?string $sessionData, ?string $expected): void {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn($sessionData);
		$this->assertSame($expected, $this->talkSession->getSessionForRoom('t1'));
	}

	/**
	 * @dataProvider dataGet
	 */
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

	/**
	 * @dataProvider dataSet
	 */
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

	/**
	 * @dataProvider dataSet
	 */
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

	/**
	 * @dataProvider dataRemove
	 */
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

	/**
	 * @dataProvider dataRemove
	 */
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
