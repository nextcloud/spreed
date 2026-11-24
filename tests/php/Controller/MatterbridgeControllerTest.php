<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\MatterbridgeController;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MatterbridgeControllerTest extends TestCase {
	protected IRequest&MockObject $request;
	protected MatterbridgeManager&MockObject $bridgeManager;
	protected Manager&MockObject $manager;
	protected MatterbridgeController $controller;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->bridgeManager = $this->createMock(MatterbridgeManager::class);
		$this->manager = $this->createMock(Manager::class);

		$this->controller = new MatterbridgeController(
			'spreed',
			$this->request,
			$this->bridgeManager,
			$this->manager,
			'participant1',
		);
	}

	protected function setRoom(bool $classified): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn($classified);
		$this->controller->setRoom($room);
		return $room;
	}

	public function testEditBridgeOfClassifiedRoomIsRejected(): void {
		$this->setRoom(true);

		$this->bridgeManager->expects($this->never())
			->method('editBridgeOfRoom');

		$response = $this->controller->editBridgeOfRoom(true, [['type' => 'nctalk']]);

		$this->assertSame(Http::STATUS_NOT_ACCEPTABLE, $response->getStatus());
		$this->assertSame(['error' => 'classified'], $response->getData());
	}

	public function testEditBridgeOfRegularRoomIsAllowed(): void {
		$room = $this->setRoom(false);

		$this->bridgeManager->expects($this->once())
			->method('editBridgeOfRoom')
			->with($room, 'participant1', true, [['type' => 'nctalk']])
			->willReturn(['running' => true]);

		$response = $this->controller->editBridgeOfRoom(true, [['type' => 'nctalk']]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['running' => true], $response->getData());
	}

	public function testEnablingTheBridgeOfATeamConversationIsRejected(): void {
		// Also for breakout rooms of team conversations, see Manager::getTeamRoomOf()
		$room = $this->setRoom(false);
		$this->manager->method('getTeamRoomOf')->with($room)->willReturn($room);

		$this->bridgeManager->expects($this->never())
			->method('editBridgeOfRoom');

		$response = $this->controller->editBridgeOfRoom(true, [['type' => 'matrix']]);

		$this->assertSame(Http::STATUS_NOT_ACCEPTABLE, $response->getStatus());
		$this->assertSame(['error' => 'team'], $response->getData());
	}

	public function testDisablingTheBridgeOfATeamConversationStaysPossible(): void {
		// E.g. a bridge that was enabled before the conversation became a team conversation
		$room = $this->setRoom(false);
		$this->manager->method('getTeamRoomOf')->willReturn($room);

		$this->bridgeManager->expects($this->once())
			->method('editBridgeOfRoom')
			->with($room, 'participant1', false, [])
			->willReturn(['running' => false]);

		$response = $this->controller->editBridgeOfRoom(false);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
