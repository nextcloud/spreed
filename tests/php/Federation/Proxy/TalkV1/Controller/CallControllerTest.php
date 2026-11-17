<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\Controller\CallController;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSException;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CallControllerTest extends TestCase {
	private ProxyRequest&MockObject $proxy;
	private Room&MockObject $room;
	private Participant $participant;
	private CallController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->proxy = $this->createMock(ProxyRequest::class);
		$this->room = $this->createMock(Room::class);
		$attendee = new Attendee();
		$attendee->setInvitedCloudId('user@remote');
		$attendee->setAccessToken('access-token');
		$this->participant = new Participant($this->room, $attendee, null);
		$this->controller = new CallController($this->proxy, $this->createMock(UserConverter::class));
	}

	protected function mockRemoteStatus(int $statusCode): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($statusCode);
		$this->proxy->method('post')->willReturn($response);
	}

	public function testRingAttendee(): void {
		$this->mockRemoteStatus(Http::STATUS_OK);
		$this->assertSame(Http::STATUS_OK, $this->controller->ringAttendee($this->room, $this->participant, 42)->getStatus());
	}

	public function testRingAttendeeLobby(): void {
		$this->mockRemoteStatus(Http::STATUS_PRECONDITION_FAILED);
		$this->expectException(OCSException::class);
		$this->expectExceptionCode(Http::STATUS_PRECONDITION_FAILED);
		$this->controller->ringAttendee($this->room, $this->participant, 42);
	}

	public function testRingAttendeeUnexpectedStatus(): void {
		$this->mockRemoteStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
		$this->expectException(CannotReachRemoteException::class);
		$this->controller->ringAttendee($this->room, $this->participant, 42);
	}
}
