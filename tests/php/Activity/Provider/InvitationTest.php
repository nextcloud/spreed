<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Activity\Provider;

use OCA\Talk\Activity\Provider\Invitation;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Federation\ICloudIdManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class InvitationTest
 *
 * @package OCA\Talk\Tests\php\Activity
 */
class InvitationTest extends TestCase {
	protected IFactory&MockObject $l10nFactory;
	protected IURLGenerator&MockObject $url;
	protected Config&MockObject $config;
	protected IManager&MockObject $activityManager;
	protected IUserManager&MockObject $userManager;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected ParticipantService&MockObject $participantService;
	protected AvatarService&MockObject $avatarService;
	protected Manager&MockObject $manager;

	public function setUp(): void {
		parent::setUp();

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(Config::class);
		$this->activityManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->manager = $this->createMock(Manager::class);
	}

	/**
	 * @param string[] $methods
	 * @return Invitation|MockObject
	 */
	protected function getProvider(array $methods = []) {
		if (!empty($methods)) {
			return $this->getMockBuilder(Invitation::class)
				->setConstructorArgs([
					$this->l10nFactory,
					$this->url,
					$this->config,
					$this->activityManager,
					$this->userManager,
					$this->cloudIdManager,
					$this->participantService,
					$this->avatarService,
					$this->manager,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new Invitation(
			$this->l10nFactory,
			$this->url,
			$this->config,
			$this->activityManager,
			$this->userManager,
			$this->cloudIdManager,
			$this->participantService,
			$this->avatarService,
			$this->manager
		);
	}

	public function testParseThrowsWrongSubject(): void {
		/** @var IEvent&MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$event->expects($this->once())
			->method('getSubject')
			->willReturn('call');
		$event->expects($this->once())
			->method('getAffectedUser')
			->willReturn('user');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('user')
			->willReturn($user);
		$this->config->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		$provider = $this->getProvider();
		$this->expectException(UnknownActivityException::class);
		$provider->parse('en', $event);
	}

	public static function dataParse(): array {
		return [
			['en', true, ['room' => 23, 'user' => 'test1'], ['actor' => ['actor-data'], 'call' => ['call-data']]],
			['de', false, ['room' => 42, 'user' => 'test2'], ['actor' => ['actor-data'], 'call' => ['call-unknown']]],
		];
	}

	#[DataProvider('dataParse')]
	public function testParse(string $lang, bool $roomExists, array $params, array $expectedParams): void {
		$provider = $this->getProvider(['setSubjects', 'getUser', 'getRoom', 'getFormerRoom']);

		/** @var IL10N&MockObject $l */
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		/** @var IEvent&MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$event->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$event->expects($this->once())
			->method('getSubjectParameters')
			->willReturn($params);
		$event->expects($this->exactly($roomExists ? 2 : 1))
			->method('getAffectedUser')
			->willReturn('user');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('user')
			->willReturn($user);
		$this->config->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		if ($roomExists) {
			/** @var Room&MockObject $room */
			$room = $this->createMock(Room::class);

			$this->manager->expects($this->once())
				->method('getRoomById')
				->with($params['room'])
				->willReturn($room);

			$provider->expects($this->once())
				->method('getRoom')
				->with($room, 'user')
				->willReturn(['call-data']);
			$provider->expects($this->never())
				->method('getFormerRoom');
		} else {
			$this->manager->expects($this->once())
				->method('getRoomById')
				->with($params['room'])
				->willThrowException(new RoomNotFoundException());

			$provider->expects($this->never())
				->method('getRoom');
			$provider->expects($this->once())
				->method('getFormerRoom')
				->with($l)
				->willReturn(['call-unknown']);
		}

		$this->l10nFactory->expects($this->once())
			->method('get')
			->with('spreed', $lang)
			->willReturn($l);

		$provider->expects($this->once())
			->method('getUser')
			->with($params['user'])
			->willReturn(['actor-data']);
		$provider->expects($this->once())
			->method('setSubjects')
			->with($event, '{actor} invited you to {call}', $expectedParams);
		$provider->expects($this->once())
			->method('getUser')
			->with($params['user'])
			->willReturn(['actor-data']);

		$provider->parse($lang, $event);
	}
}
