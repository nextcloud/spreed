<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Model\Attendee;
use OCP\Comments\ICommentsManager;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class AttachmentMapperTest extends TestCase {
	protected AttachmentMapper $mapper;
	protected int $roomId;

	public function setUp(): void {
		parent::setUp();

		$this->mapper = new AttachmentMapper(Server::get(IDBConnection::class));
		$this->roomId = random_int(1_000_000, 9_000_000);
	}

	public function tearDown(): void {
		$this->mapper->deleteByRoomId($this->roomId);
		parent::tearDown();
	}

	protected function createAttachment(int $messageId, string $actorId, ?\DateTime $expireDate = null): void {
		$attachment = new Attachment();
		$attachment->setRoomId($this->roomId);
		$attachment->setMessageId($messageId);
		$attachment->setMessageTime(time());
		$attachment->setObjectType(Attachment::TYPE_FILE);
		$attachment->setActorType(Attendee::ACTOR_USERS);
		$attachment->setActorId($actorId);
		$attachment->setExpireDate($expireDate);
		$this->mapper->insert($attachment);
	}

	public function testNeutralizeDeletedUser(): void {
		$this->createAttachment(1, 'deleted');
		$this->createAttachment(2, 'other');

		$this->mapper->neutralizeDeletedUser(Attendee::ACTOR_USERS, 'deleted');

		$attachments = array_map(static fn (Attachment $attachment): array => [
			$attachment->getActorType(),
			$attachment->getActorId(),
		], $this->mapper->getAttachmentsByType($this->roomId, Attachment::TYPE_FILE, 0, 10));

		$this->assertSame([
			[Attendee::ACTOR_USERS, 'other'],
			[ICommentsManager::DELETED_USER, ICommentsManager::DELETED_USER],
		], $attachments);
	}

	public function testDeleteExpired(): void {
		$this->createAttachment(1, 'expired', new \DateTime('-1 hour'));
		$this->createAttachment(2, 'future', new \DateTime('+1 hour'));
		$this->createAttachment(3, 'never');

		$this->mapper->deleteExpired(new \DateTime());

		$attachments = array_map(static fn (Attachment $attachment): int => $attachment->getMessageId(),
			$this->mapper->getAttachmentsByType($this->roomId, Attachment::TYPE_FILE, 0, 10));

		$this->assertSame([3, 2], $attachments);
	}
}
