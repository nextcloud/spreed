<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Tests\php\Controller;

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Controller\ChatController;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\IComment;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;

class ChatControllerTest extends \Test\TestCase {

	/** @var string */
	private $userId;

	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var \OCP\ISession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;

	/** @var \OCA\Spreed\Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;

	/** @var \OCA\Spreed\Chat\ChatManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $chatManager;

	/** @var \OCA\Spreed\Room|\PHPUnit_Framework_MockObject_MockObject */
	protected $room;

	/** @var \OCA\Spreed\Controller\ChatController */
	private $controller;

	/** @var \PHPUnit_Framework_Constraint_Callback */
	private $newMessageDateTimeConstraint;

	public function setUp() {
		parent::setUp();

		$this->userId = 'testUser';
		$this->userManager = $this->createMock(IUserManager::class);
		$this->session = $this->createMock(ISession::class);
		$this->manager = $this->createMock(Manager::class);
		$this->chatManager = $this->createMock(ChatManager::class);

		$this->room = $this->createMock(Room::class);

		$this->recreateChatController();

		// Verifies that the difference of the given DateTime and now is at most
		// five seconds, and that it uses the UTC time zone.
		$this->newMessageDateTimeConstraint = $this->callback(function(\DateTime $dateTime) {
			return abs((new \DateTime())->getTimestamp() - $dateTime->getTimestamp()) <= 5 &&
				(new \DateTimeZone('UTC'))->getName() === $dateTime->getTimezone()->getName();
		});
	}

	private function recreateChatController() {
		$this->controller = new ChatController(
			'spreed',
			$this->userId,
			$this->createMock(\OCP\IRequest::class),
			$this->userManager,
			$this->session,
			$this->manager,
			$this->chatManager
		);
	}

	private function newComment($id, $actorType, $actorId, $creationDateTime, $message) {
		$comment = $this->createMock(IComment::class);

		$comment->method('getId')->willReturn($id);
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getCreationDateTime')->willReturn($creationDateTime);
		$comment->method('getMessage')->willReturn($message);

		return $comment;
	}

	public function testSendMessageByUser() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with('1234',
				   'users',
				   $this->userId,
				   'testMessage',
				   $this->newMessageDateTimeConstraint
			);

		$response = $this->controller->sendMessage('testToken', 'testMessage');
		$expected = new DataResponse([], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByUserNotJoinedButInRoom() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->willReturn($this->room);

		$this->room->expects($this->once())
			->method('getParticipant')
			->with($this->userId);

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with('1234',
				   'users',
				   $this->userId,
				   'testMessage',
				   $this->newMessageDateTimeConstraint
			);

		$response = $this->controller->sendMessage('testToken', 'testMessage');
		$expected = new DataResponse([], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByUserNotJoinedNorInRoom() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->willReturn($this->room);

		$this->room->expects($this->once())
			->method('getParticipant')
			->with($this->userId)
			->will($this->throwException(new ParticipantNotFoundException()));

		$this->chatManager->expects($this->never())
			->method('sendMessage');

		$response = $this->controller->sendMessage('testToken', 'testMessage');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByGuest() {
		$this->userId = null;
		$this->recreateChatController();

		$this->session->expects($this->any())
			->method('get')
			->with('spreed-session')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with('1234',
				   'guests',
				   sha1('testSpreedSession'),
				   'testMessage',
				   $this->newMessageDateTimeConstraint
			);

		$response = $this->controller->sendMessage('testToken', 'testMessage');
		$expected = new DataResponse([], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByGuestNotJoined() {
		$this->userId = null;
		$this->recreateChatController();

		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->chatManager->expects($this->never())
			->method('sendMessage');

		$response = $this->controller->sendMessage('testToken', 'testMessage');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageToInvalidRoom() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->will($this->throwException(new RoomNotFoundException()));

		$this->chatManager->expects($this->never())
			->method('sendMessage');

		$response = $this->controller->sendMessage('testToken', 'testMessage');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByUser() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->once())
			->method('receiveMessages')
			->with('1234', $this->userId, $timeout, $offset, new \DateTime('@' . $timestamp))
			->willReturn([
				$this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$this->userManager->expects($this->exactly(3))
			->method('get')
			->withConsecutive(['testUser'], ['testUnknownUser'], ['testUser'])
			->willReturn($testUser, null, $testUser);

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4'],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3'],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2'],
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1']
		], Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByUserNotJoinedButInRoom() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->willReturn($this->room);

		$this->room->expects($this->once())
			->method('getParticipant')
			->with($this->userId);

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->once())
			->method('receiveMessages')
			->with('1234', $this->userId, $timeout, $offset, new \DateTime('@' . $timestamp))
			->willReturn([
				$this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$this->userManager->expects($this->exactly(3))
			->method('get')
			->withConsecutive(['testUser'], ['testUnknownUser'], ['testUser'])
			->willReturn($testUser, null, $testUser);

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4'],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3'],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2'],
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1']
		], Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByUserNotJoinedNorInRoom() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->willReturn($this->room);

		$this->room->expects($this->once())
			->method('getParticipant')
			->with($this->userId)
			->will($this->throwException(new ParticipantNotFoundException()));

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->never())
			->method('receiveMessages');

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByGuest() {
		$this->userId = null;
		$this->recreateChatController();

		$this->session->expects($this->any())
			->method('get')
			->with('spreed-session')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->once())
			->method('receiveMessages')
			->with('1234', null, $timeout, $offset, new \DateTime('@' . $timestamp))
			->willReturn([
				$this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$this->userManager->expects($this->exactly(3))
			->method('get')
			->withConsecutive(['testUser'], ['testUnknownUser'], ['testUser'])
			->willReturn($testUser, null, $testUser);

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4'],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3'],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2'],
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1']
		], Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByGuestNotJoined() {
		$this->userId = null;
		$this->recreateChatController();

		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->never())
			->method('receiveMessages');

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesTimeoutExpired() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->once())
			->method('receiveMessages')
			->with('1234', $this->userId, $timeout, $offset, new \DateTime('@' . $timestamp))
			->willReturn([]);

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([], Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesTimeoutTooLarge() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->once())
			->method('getId')
			->willReturn(1234);

		$timeout = 100000;
		$maximumTimeout = 60;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->once())
			->method('receiveMessages')
			->with('1234', $this->userId, $maximumTimeout, $offset, new \DateTime('@' . $timestamp))
			->willReturn([]);

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([], Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesFromInvalidRoom() {
		$this->session->expects($this->once())
			->method('get')
			->with('spreed-session')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->will($this->throwException(new RoomNotFoundException()));

		$timeout = 42;
		$offset = 23;
		$timestamp = 1000000000;
		$this->chatManager->expects($this->never())
			->method('receiveMessages');

		$response = $this->controller->receiveMessages('testToken', $offset, $timestamp, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

}
