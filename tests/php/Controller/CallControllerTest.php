<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\CallController;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CallControllerTest extends TestCase {
	private ParticipantService&MockObject $participantService;
	private IEventDispatcher&MockObject $dispatcher;
	private ITimeFactory&MockObject $timeFactory;
	private IConfig&MockObject $serverConfig;
	private Room&MockObject $room;
	private CallController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->room = $this->createMock(Room::class);

		$arguments = [];
		foreach ((new \ReflectionClass(CallController::class))->getConstructor()->getParameters() as $parameter) {
			$type = (string)$parameter->getType();
			$arguments[] = match ($parameter->getName()) {
				'appName' => 'spreed',
				default => match ($type) {
					ParticipantService::class => $this->participantService,
					IEventDispatcher::class => $this->dispatcher,
					ITimeFactory::class => $this->timeFactory,
					IConfig::class => $this->serverConfig,
					default => $this->createMock($type),
				},
			};
		}
		$this->controller = new CallController(...$arguments);
	}

	protected function createParticipant(string $actorId): Participant {
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId($actorId);
		$attendee->setDisplayName($actorId);
		return new Participant($this->room, $attendee, null);
	}

	public function testDownloadParticipantsForCallLogsCriticalAction(): void {
		$this->room->method('isClassified')->willReturn(false);
		$this->room->method('getActiveSince')->willReturn(new \DateTime('-1 hour'));
		$this->room->method('getToken')->willReturn('abcdef');
		$this->room->method('getName')->willReturn('Team call');
		$this->participantService->method('getParticipantsJoinedCurrentCall')
			->willReturn([$this->createParticipant('alice'), $this->createParticipant('bob')]);
		$this->serverConfig->method('getUserValue')->willReturn('UTC');
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-16'));
		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($this->createParticipant('moderator'));

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.call_participants.exported'
				&& $event->getLogMessage() === 'Participants list of the call in conversation "{token}" was downloaded ({participants} participants)'
				&& $event->getParameters() === ['token' => 'abcdef', 'participants' => 2]));

		$this->assertInstanceOf(DataDownloadResponse::class, $this->controller->downloadParticipantsForCall());
	}
}
