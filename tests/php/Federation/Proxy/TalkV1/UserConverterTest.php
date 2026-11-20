<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation\Proxy\TalkV1;

use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use Test\TestCase;

class UserConverterTest extends TestCase {
	public function testConvertThreadInfoConvertsMessageActors(): void {
		$room = $this->createMock(Room::class);
		$room->method('getRemoteServer')->willReturn('https://remote.example');
		$room->method('getToken')->willReturn('localtok');
		$room->method('getRemoteToken')->willReturn('remotetok');

		$converter = new UserConverter(
			$this->createMock(ParticipantService::class),
			$this->createMock(AvatarService::class),
		);

		$message = [
			'token' => 'remotetok',
			'actorType' => 'users',
			'actorId' => 'admin',
			'actorDisplayName' => 'Admin',
			'lastEditActorType' => 'users',
			'lastEditActorId' => 'editor',
			'lastEditActorDisplayName' => 'Editor',
			'messageParameters' => [],
		];

		$result = $converter->convertThreadInfo($room, [
			'thread' => ['roomToken' => 'remotetok'],
			'first' => $message,
			'last' => $message,
		]);

		$this->assertSame('localtok', $result['thread']['roomToken']);
		foreach (['first', 'last'] as $key) {
			$this->assertSame('localtok', $result[$key]['token']);
			$this->assertSame('federated_users', $result[$key]['actorType']);
			$this->assertSame('admin@remote.example', $result[$key]['actorId']);
			$this->assertSame('federated_users', $result[$key]['lastEditActorType']);
			$this->assertSame('editor@remote.example', $result[$key]['lastEditActorId']);
		}
	}
}
