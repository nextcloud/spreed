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

namespace OCA\Talk\Tests\Unit;

use OCA\Talk\Capabilities;
use OCA\Talk\Config;
use OCP\Capabilities\IPublicCapability;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CapabilitiesTest extends TestCase {

	/** @var IConfig|MockObject */
	protected $serverConfig;
	/** @var Config|MockObject */
	protected $talkConfig;
	/** @var IUserSession|MockObject */
	protected $userSession;

	public function setUp(): void {
		parent::setUp();
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->userSession = $this->createMock(IUserSession::class);
	}

	public function testGetCapabilitiesGuest(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->userSession
		);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->talkConfig->expects($this->never())
			->method('isDisabledForUser');

		$this->serverConfig->expects($this->once())
			->method('getSystemValueString')
			->with('version', '0.0.0')
			->willReturn('16.0.1');

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([
			'spreed' => [
				'features' => [
					'audio',
					'video',
					'chat-v2',
					'conversation-v2',
					'guest-signaling',
					'empty-group-room',
					'guest-display-names',
					'multi-room-users',
					'favorites',
					'last-room-activity',
					'no-ping',
					'system-messages',
					'mention-flag',
					'in-call-flags',
					'notification-levels',
					'invite-groups-and-mails',
					'locked-one-to-one-rooms',
					'read-only-rooms',
					'chat-read-marker',
					'webinary-lobby',
					'start-call-flag',
					'chat-replies',
					'circles-support',
					'force-mute',
				],
				'config' => [
					'attachments' => [
						'allowed' => false,
					],
					'chat' => [
						'max-length' => 1000,
					],
					'conversations' => [
						'can-create' => false,
					],
				],
			],
		], $capabilities->getCapabilities());
	}

	public function dataGetCapabilitiesUserAllowed(): array {
		return [
			[true, false],
			[false, true],
		];
	}

	/**
	 * @dataProvider dataGetCapabilitiesUserAllowed
	 * @param bool $isNotAllowed
	 * @param bool $canCreate
	 */
	public function testGetCapabilitiesUserAllowed(bool $isNotAllowed, bool $canCreate): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->userSession
		);

		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('uid');
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->talkConfig->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		$this->talkConfig->expects($this->once())
			->method('getAttachmentFolder')
			->with('uid')
			->willReturn('/Talk');

		$this->talkConfig->expects($this->once())
			->method('isNotAllowedToCreateConversations')
			->with($user)
			->willReturn($isNotAllowed);

		$this->serverConfig->expects($this->once())
			->method('getSystemValueString')
			->with('version', '0.0.0')
			->willReturn('16.0.2');

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([
			'spreed' => [
				'features' => [
					'audio',
					'video',
					'chat-v2',
					'conversation-v2',
					'guest-signaling',
					'empty-group-room',
					'guest-display-names',
					'multi-room-users',
					'favorites',
					'last-room-activity',
					'no-ping',
					'system-messages',
					'mention-flag',
					'in-call-flags',
					'notification-levels',
					'invite-groups-and-mails',
					'locked-one-to-one-rooms',
					'read-only-rooms',
					'chat-read-marker',
					'webinary-lobby',
					'start-call-flag',
					'chat-replies',
					'circles-support',
					'force-mute',
				],
				'config' => [
					'attachments' => [
						'allowed' => true,
						'folder' => '/Talk',
					],
					'chat' => [
						'max-length' => 32000,
					],
					'conversations' => [
						'can-create' => $canCreate,
					],
				],
			],
		], $capabilities->getCapabilities());
	}

	public function testGetCapabilitiesUserDisallowed(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->userSession
		);

		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->talkConfig->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(true);

		$this->serverConfig->expects($this->never())
			->method('getSystemValueString');

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([], $capabilities->getCapabilities());
	}
}
