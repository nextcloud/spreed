<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\IndexLinkAttachments;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\AttachmentService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Comments\ICommentsManager;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class IndexLinkAttachmentsTest extends TestCase {
	protected ICommentsManager $commentsManager;
	protected AttachmentMapper $attachmentMapper;
	protected int $roomId;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->commentsManager = Server::get(ICommentsManager::class);
		$this->attachmentMapper = Server::get(AttachmentMapper::class);
		$this->roomId = random_int(1_000_000, 9_000_000);
	}

	#[\Override]
	public function tearDown(): void {
		$this->commentsManager->deleteCommentsAtObject('chat', (string)$this->roomId);
		$this->attachmentMapper->deleteByRoomId($this->roomId);
		parent::tearDown();
	}

	protected function createMessage(string $message, string $verb = ChatManager::VERB_MESSAGE): int {
		$comment = $this->commentsManager->create(Attendee::ACTOR_USERS, 'author', 'chat', (string)$this->roomId);
		$comment->setMessage($message);
		$comment->setVerb($verb);
		$this->commentsManager->save($comment);
		return (int)$comment->getId();
	}

	public function testRun(): void {
		$withLink = $this->createMessage('See https://nextcloud.com');
		$this->createMessage('No link');
		$this->createMessage('`https://nextcloud.com`');
		$this->createMessage('https://nextcloud.com', ChatManager::VERB_PRIVATE_REPLY);
		$markdown = $this->createMessage('[Docs](https://docs.nextcloud.com)');
		$newest = $this->createMessage('Newest https://nextcloud.com');
		$this->createMessage('After the upgrade https://nextcloud.com');

		$attachmentService = Server::get(AttachmentService::class);

		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->once())
			->method('add')
			->with(IndexLinkAttachments::class, ['beforeId' => $withLink]);

		$job = new class(Server::get(ITimeFactory::class), Server::get(IDBConnection::class), $attachmentService, $this->attachmentMapper, $jobList) extends IndexLinkAttachments {
			public const CHUNK_SIZE = 5;
		};
		self::invokePrivate($job, 'run', [['beforeId' => $newest + 1]]);

		$messageIds = array_map(
			static fn (Attachment $attachment): int => $attachment->getMessageId(),
			$this->attachmentMapper->getAttachmentsByType($this->roomId, Attachment::TYPE_LINK, 0, 10),
		);
		sort($messageIds);
		$this->assertSame([$withLink, $markdown, $newest], $messageIds);
	}
}
