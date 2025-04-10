<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Tests\php;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\GuestImportException;
use OCA\Talk\GuestManager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\RoomService;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class GuestManagerTest extends TestCase {
	protected Config&MockObject $talkConfig;
	protected IMailer&MockObject $mailer;
	protected Defaults&MockObject $defaults;
	protected IUserSession&MockObject $userSession;
	protected ParticipantService&MockObject $participantService;
	protected PollService&MockObject $pollService;
	protected RoomService&MockObject $roomService;
	protected IURLGenerator&MockObject $urlGenerator;
	protected IL10N&MockObject $l;
	protected IEventDispatcher&MockObject $dispatcher;
	protected LoggerInterface&MockObject $logger;
	private IDateTimeZone&MockObject $dateTime;

	public function setUp(): void {
		parent::setUp();
		$this->talkConfig = $this->createMock(Config::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->defaults = $this->createMock(Defaults::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->pollService = $this->createMock(PollService::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l = $this->createMock(IL10N::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->dateTime = $this->createMock(IDateTimeZone::class);
	}

	public function getGuestManager(array $methods = []): GuestManager|MockObject {
		if (!empty($methods)) {
			return $this->getMockBuilder(GuestManager::class)
				->setConstructorArgs([
					$this->talkConfig,
					$this->mailer,
					$this->defaults,
					$this->userSession,
					$this->participantService,
					$this->pollService,
					$this->roomService,
					$this->urlGenerator,
					$this->l,
					$this->dispatcher,
					$this->logger,
					$this->dateTime,
				])
				->onlyMethods($methods)
				->getMock();
		}

		$this->guestManager = new GuestManager(
			$this->talkConfig,
			$this->mailer,
			$this->defaults,
			$this->userSession,
			$this->participantService,
			$this->pollService,
			$this->roomService,
			$this->urlGenerator,
			$this->l,
			$this->dispatcher,
			$this->logger,
			$this->dateTime,
		);
	}

	public static function dataImportEmails(): array {
		return [
			[
				'import-valid-only-email.csv',
				1,
				0,
				[['valid@example.tld', null]],
			],
			[
				'import-valid-email-and-name.csv',
				1,
				0,
				[['valid@example.tld', 'Name']],
			],
			[
				'import-valid-filter-duplicates-by-email.csv',
				2,
				1,
				[['valid-1@example.tld', 'Valid 1'], ['valid-2@example.tld',null]],
				GuestImportException::REASON_ROWS,
				[4],
			],
		];
	}

	/**
	 * @dataProvider dataImportEmails
	 */
	public function testImportEmails(string $fileName, int $invites, int $duplicates, array $invited, ?string $reason = null, array $invalidLines = []): void {
		$this->mailer->method('validateMailAddress')
			->willReturnCallback(static fn (string $email): bool => str_starts_with($email, 'valid'));

		$actualInvites = [];
		$this->participantService->method('inviteEmailAddress')
			->willReturnCallback(function ($room, $actorId, string $email, ?string $name) use (&$actualInvites): Participant {
				$actualInvites[] = [$email, $name];
				return $this->createMock(Participant::class);
			});

		$room = $this->createMock(Room::class);

		try {
			$guestManager = $this->getGuestManager(['sendEmailInvitation']);
			$data = $guestManager->importEmails($room, __DIR__ . '/data/' . $fileName, false);
		} catch (GuestImportException $e) {
			if ($reason === null) {
				throw $e;
			}

			$data = $e->getData();
			$this->assertSame($invalidLines, $data['invalidLines']);
		}

		$this->assertSame($invited, $actualInvites);
		$this->assertSame($invites, $data['invites'], 'Invites count mismatch');
		$this->assertSame($duplicates, $data['duplicates'], 'Duplicates count mismatch');
	}
}
