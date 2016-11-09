<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Tests\php;

use OCA\Spreed\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;

class NotifierTest extends \Test\TestCase {

	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $lFactory;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var Notifier */
	protected $notifier;

	public function setUp() {
		parent::setUp();

		$this->lFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->notifier = new Notifier(
			$this->lFactory,
			$this->url,
			$this->userManager
		);
	}

	public function dataPrepare() {
		return [
			['admin', 'Admin', 'Admin wants to have a call with you'],
			['test', 'Test user', 'Test user wants to have a call with you'],
		];
	}

	/**
	 * @dataProvider dataPrepare
	 * @param string $uid
	 * @param string $displayName
	 * @param string $parsedSubject
	 */
	public function testPrepare($uid, $displayName, $parsedSubject) {
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->exactly(2))
			->method('t')
			->will($this->returnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$u = $this->createMock(IUser::class);
		$u->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn($displayName);
		$this->userManager->expects($this->once())
			->method('get')
			->with($uid)
			->willReturn($u);

		$n->expects($this->once())
			->method('setIcon')
			->willReturnSelf();
		$n->expects($this->once())
			->method('setLink')
			->willReturnSelf();
		$n->expects($this->once())
			->method('setParsedSubject')
			->with($parsedSubject)
			->willReturnSelf();
		$n->expects($this->once())
			->method('setRichSubject')
			->with('{user} wants to have a call with you',[
				'user' => [
					'type' => 'user',
					'id' => $uid,
					'name' => $displayName,
				]
			])
			->willReturnSelf();

		$n->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$n->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$n->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$uid]);
		$n->expects($this->once())
			->method('getObjectType')
			->willReturn('one2one');

		$this->notifier->prepare($n, 'de');
	}

	public function dataPrepareThrows() {
		return [
			['Incorrect app', 'invalid-app', null, null, null],
			['Unknown subject', 'spreed', 'invalid-subject', null, null],
			['Unknown object type', 'spreed', 'invitation', ['admin'], 'invalid-object-type'],
			['Calling user does not exist anymore', 'spreed', 'invitation', ['admin'], 'one2one'],
		];
	}

	/**
	 * @dataProvider dataPrepareThrows
	 *
	 * @expectedException \InvalidArgumentException
	 *
	 * @param string $message
	 * @param string $app
	 * @param string|null $subject
	 * @param array|null $params
	 * @param string|null $objectType
	 */
	public function testPrepareThrows($message, $app, $subject, $params, $objectType) {
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);

		$this->lFactory->expects($subject === null ? $this->never() : $this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$n->expects($params === null ? $this->never() : $this->once())
			->method('setIcon')
			->willReturnSelf();
		$n->expects($params === null ? $this->never() : $this->once())
			->method('setLink')
			->willReturnSelf();

		$n->expects($this->once())
			->method('getApp')
			->willReturn($app);
		$n->expects($subject === null ? $this->never() : $this->once())
			->method('getSubject')
			->willReturn($subject);
		$n->expects($params === null ? $this->never() : $this->once())
			->method('getSubjectParameters')
			->willReturn($params);
		$n->expects($objectType === null ? $this->never() : $this->once())
			->method('getObjectType')
			->willReturn($objectType);

		$this->setExpectedException(\InvalidArgumentException::class, $message);
		$this->notifier->prepare($n, 'de');
	}
}
