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

use OC\L10N\L10N;
use OCA\Spreed\Chat\AutoComplete\SearchPlugin;
use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Controller\ChatController;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Comments\IComment;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Constraint\Callback;

class ChatControllerTest extends \Test\TestCase {

	/** @var string */
	private $userId;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var TalkSession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;

	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;

	/** @var ChatManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $chatManager;

	/** @var GuestManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $guestManager;

	/** @var MessageParser|\PHPUnit_Framework_MockObject_MockObject */
	protected $messageParser;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $autoCompleteManager;

	/** @var SearchPlugin|\PHPUnit_Framework_MockObject_MockObject */
	protected $searchPlugin;

	/** @var ISearchResult|\PHPUnit_Framework_MockObject_MockObject */
	protected $searchResult;

	/** @var L10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l;

	/** @var Room|\PHPUnit_Framework_MockObject_MockObject */
	protected $room;

	/** @var \OCA\Spreed\Controller\ChatController */
	private $controller;

	/** @var Callback */
	private $newMessageDateTimeConstraint;

	public function setUp() {
		parent::setUp();

		$this->userId = 'testUser';
		$this->userManager = $this->createMock(IUserManager::class);
		$this->session = $this->createMock(TalkSession::class);
		$this->manager = $this->createMock(Manager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->autoCompleteManager = $this->createMock(IManager::class);
		$this->searchPlugin = $this->createMock(SearchPlugin::class);
		$this->searchResult = $this->createMock(ISearchResult::class);
		$this->l = $this->createMock(L10N::class);

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
			$this->createMock(IRequest::class),
			$this->userManager,
			$this->session,
			$this->manager,
			$this->chatManager,
			$this->guestManager,
			$this->messageParser,
			$this->autoCompleteManager,
			$this->searchPlugin,
			$this->searchResult,
			$this->l
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
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
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
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->willThrowException(new RoomNotFoundException());

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->willReturn($this->room);

		$this->room->expects($this->once())
			->method('getParticipant')
			->with($this->userId);

		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
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
			->method('getSessionForRoom')
			->with('testToken')
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
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
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
			->method('getSessionForRoom')
			->with('testToken')
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
			->method('getSessionForRoom')
			->with('testToken')
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

	public function testReceiveHistoryByUser() {
		$this->session->expects($this->exactly(2))
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('getHistory')
			->with($this->room, $offset, $limit)
			->willReturn([
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$this->userManager->expects($this->exactly(4))
			->method('get')
			->withConsecutive(['testUser'], ['testUser'], ['testUnknownUser'], ['testUser'])
			->willReturn($testUser, $testUser, null, $testUser);

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage')
			->withConsecutive($comment4, $comment3, $comment2, $comment1)
			->willReturn(
				['testMessage4', ['testMessageParameters4']],
				['testMessage3', ['testMessageParameters3']],
				['testMessage2', ['testMessageParameters2']],
				['testMessage1', ['testMessageParameters1']]
			);

		$response = $this->controller->receiveMessages('testToken', 0, $limit, $offset);
		$expected = new DataResponse([
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4', 'messageParameters'=>['testMessageParameters4'], 'systemMessage' => ''],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3', 'messageParameters'=>['testMessageParameters3'], 'systemMessage' => ''],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2', 'messageParameters'=>['testMessageParameters2'], 'systemMessage' => ''],
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1', 'messageParameters'=>['testMessageParameters1'], 'systemMessage' => '']
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 108);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByUserNotJoinedButInRoom() {
		$this->session->expects($this->exactly(2))
			->method('getSessionForRoom')
			->with('testToken')
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

		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('getHistory')
			->with($this->room, $offset, $limit)
			->willReturn([
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$this->userManager->expects($this->exactly(4))
			->method('get')
			->withConsecutive(['testUser'], ['testUser'], ['testUnknownUser'], ['testUser'])
			->willReturn($testUser, $testUser, null, $testUser);

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage')
			->withConsecutive($comment4, $comment3, $comment2, $comment1)
			->willReturn(
				['testMessage4', ['testMessageParameters4']],
				['testMessage3', ['testMessageParameters3']],
				['testMessage2', ['testMessageParameters2']],
				['testMessage1', ['testMessageParameters1']]
			);

		$response = $this->controller->receiveMessages('testToken', 0, $limit, $offset);
		$expected = new DataResponse([
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4', 'messageParameters'=>['testMessageParameters4'], 'systemMessage' => ''],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3', 'messageParameters'=>['testMessageParameters3'], 'systemMessage' => ''],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2', 'messageParameters'=>['testMessageParameters2'], 'systemMessage' => ''],
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1', 'messageParameters'=>['testMessageParameters1'], 'systemMessage' => '']
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 108);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByUserNotJoinedNorInRoom() {
		$this->session->expects($this->once())
			->method('getSessionForRoom')
			->with('testToken')
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

		$limit = 5;
		$offset = 23;
		$this->chatManager->expects($this->never())
			->method('getHistory');

		$response = $this->controller->receiveMessages('testToken', 0, $limit, $offset);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByGuest() {
		$this->userId = null;
		$this->recreateChatController();

		$this->session->expects($this->any())
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('getHistory')
			->with($this->room, $offset, $limit)
			->willReturn([
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$this->userManager->expects($this->exactly(4))
			->method('get')
			->withConsecutive([null], ['testUser'], ['testUnknownUser'], ['testUser'])
			->willReturn(null, $testUser, null, $testUser);

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage')
			->withConsecutive($comment4, $comment3, $comment2, $comment1)
			->willReturn(
				['testMessage4', ['testMessageParameters4']],
				['testMessage3', ['testMessageParameters3']],
				['testMessage2', ['testMessageParameters2']],
				['testMessage1', ['testMessageParameters1']]
			);

		$response = $this->controller->receiveMessages('testToken', 0, $limit, $offset);
		$expected = new DataResponse([
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4', 'messageParameters'=>['testMessageParameters4'], 'systemMessage' => ''],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3', 'messageParameters'=>['testMessageParameters3'], 'systemMessage' => ''],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2', 'messageParameters'=>['testMessageParameters2'], 'systemMessage' => ''],
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1', 'messageParameters'=>['testMessageParameters1'], 'systemMessage' => '']
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 108);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByGuestNotJoined() {
		$this->userId = null;
		$this->recreateChatController();

		$this->session->expects($this->once())
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn(null);

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, null)
			->will($this->throwException(new RoomNotFoundException()));

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->never())
			->method('getHistory');

		$response = $this->controller->receiveMessages('testToken', 0, $limit, $offset);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesFromInvalidRoom() {
		$this->session->expects($this->once())
			->method('getSessionForRoom')
			->with('testToken')
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
			->method('getHistory');

		$response = $this->controller->receiveMessages('testToken', 0);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesByUser() {
		$this->session->expects($this->exactly(2))
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn('testUser');
		$testUser->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn('Test User');

		$offset = 23;
		$limit = 4;
		$timeout = 10;
		$this->chatManager->expects($this->once())
			->method('waitForNewMessages')
			->with($this->room, $offset, $limit, $timeout, $testUser)
			->willReturn([
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
			]);

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnMap([
				['testUser', $testUser],
				['testUnknownUser', null]
			]);

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage')
			->withConsecutive($comment1, $comment2, $comment3, $comment4)
			->willReturn(
				['testMessage1', ['testMessageParameters1']],
				['testMessage2', ['testMessageParameters2']],
				['testMessage3', ['testMessageParameters3']],
				['testMessage4', ['testMessageParameters4']]
			);

		$response = $this->controller->receiveMessages('testToken', 1, $limit, $offset, $timeout);
		$expected = new DataResponse([
			['id'=>108, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000004, 'message'=>'testMessage1', 'messageParameters'=>['testMessageParameters1'], 'systemMessage' => ''],
			['id'=>109, 'token'=>'testToken', 'actorType'=>'guests', 'actorId'=>'testSpreedSession', 'actorDisplayName'=>null, 'timestamp'=>1000000008, 'message'=>'testMessage2', 'messageParameters'=>['testMessageParameters2'], 'systemMessage' => ''],
			['id'=>110, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUnknownUser', 'actorDisplayName'=>null, 'timestamp'=>1000000015, 'message'=>'testMessage3', 'messageParameters'=>['testMessageParameters3'], 'systemMessage' => ''],
			['id'=>111, 'token'=>'testToken', 'actorType'=>'users', 'actorId'=>'testUser', 'actorDisplayName'=>'Test User', 'timestamp'=>1000000016, 'message'=>'testMessage4', 'messageParameters'=>['testMessageParameters4'], 'systemMessage' => ''],
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 111);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesTimeoutExpired() {
		$this->session->expects($this->exactly(2))
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn('testUser');

		$offset = 23;
		$limit = 4;
		$timeout = 3;
		$this->chatManager->expects($this->once())
			->method('waitForNewMessages')
			->with($this->room, $offset, $limit, $timeout, $testUser)
			->willReturn([]);

		$this->userManager->expects($this->any())
			->method('get')
			->with('testUser')
			->willReturn($testUser);

		$response = $this->controller->receiveMessages('testToken', 1, $limit, $offset, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_MODIFIED);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesTimeoutTooLarge() {
		$this->session->expects($this->exactly(2))
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn('testUser');

		$offset = 23;
		$timeout = 100000;
		$maximumTimeout = 30;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('waitForNewMessages')
			->with($this->room, $offset, $limit, $maximumTimeout, $testUser)
			->willReturn([]);

		$this->userManager->expects($this->any())
			->method('get')
			->with('testUser')
			->willReturn($testUser);

		$response = $this->controller->receiveMessages('testToken', 1, $limit, $offset, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_MODIFIED);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesFromInvalidRoom() {
		$this->session->expects($this->once())
			->method('getSessionForRoom')
			->with('testToken')
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
			->method('waitForNewMessages');

		$response = $this->controller->receiveMessages('testToken', 1);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}

	public function dataMentions() {
		return [
			['tes', 10, ['exact' => []], []],
			['foo', 20, [
				'exact' => [
					'users' => [
						['label' => 'Foo Bar', 'value' => ['shareWith' => 'foo', 'shareType' => 'user']],
					]
				],
				'users' => [
					['label' => 'FooBar', 'value' => ['shareWith' => 'foobar', 'shareType' => 'user']],
				]], [
					['id' => 'foo', 'label' => 'Foo Bar', 'source' => 'users'],
					['id' => 'foobar', 'label' => 'FooBar', 'source' => 'users'],
			]],
		];
	}

	/**
	 * @dataProvider dataMentions
	 */
	public function testMentions($search, $limit, $result, $expected) {
		$this->session->expects($this->once())
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willReturn($this->room);

		$this->manager->expects($this->never())
			->method('getRoomForParticipantByToken');

		$this->room->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->searchPlugin->expects($this->once())
			->method('setContext')
			->with([
				'itemType' => 'chat',
				'itemId' => $this->room->getId(),
				'room' => $this->room,
			]);

		$this->searchResult->expects($this->once())
			->method('asArray')
			->willReturn($result);

		$response = $this->controller->mentions('testToken', $search, $limit);
		$expected = new DataResponse($expected, Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}

	public function testMentionsInvalidRoom() {
		$this->session->expects($this->once())
			->method('getSessionForRoom')
			->with('testToken')
			->willReturn('testSpreedSession');

		$this->manager->expects($this->once())
			->method('getRoomForSession')
			->with($this->userId, 'testSpreedSession')
			->willThrowException(new RoomNotFoundException());

		$this->manager->expects($this->once())
			->method('getRoomForParticipantByToken')
			->with('testToken', $this->userId)
			->willThrowException(new RoomNotFoundException());

		$this->searchPlugin->expects($this->never())
			->method('setContext');

		$this->searchResult->expects($this->never())
			->method('asArray');

		$response = $this->controller->mentions('testToken', 'foo', 10);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $response);
	}
}
