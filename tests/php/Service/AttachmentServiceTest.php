<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\AttachmentMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCP\Comments\IComment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AttachmentServiceTest extends TestCase {
	protected AttachmentMapper&MockObject $attachmentMapper;
	protected AttachmentService $service;

	public function setUp(): void {
		parent::setUp();
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->service = new AttachmentService($this->attachmentMapper);
	}

	public static function dataContainsLink(): array {
		return [
			['No link', false],
			['https://nextcloud.com', true],
			['Visit http://example.tld/path?x=1 now', true],
			["Line\nhttps://nextcloud.com\nnext", true],
			['nohttps://nextcloud.com', false],
			['ftp://nextcloud.com', false],
		];
	}

	#[DataProvider('dataContainsLink')]
	public function testContainsLink(string $message, bool $expected): void {
		$this->assertSame($expected, $this->service->containsLink($message));
	}

	public static function dataUpdateLinkAttachment(): array {
		return [
			'added' => ['https://nextcloud.com', false, true, false],
			'removed' => ['No link', true, false, true],
			'kept' => ['https://nextcloud.com', true, false, false],
			'none' => ['No link', false, false, false],
		];
	}

	#[DataProvider('dataUpdateLinkAttachment')]
	public function testUpdateLinkAttachment(string $message, bool $hadLink, bool $insert, bool $delete): void {
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(23);
		$comment = $this->createMock(IComment::class);
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getId')->willReturn('42');
		$comment->method('getCreationDateTime')->willReturn(new \DateTime());

		$this->attachmentMapper->expects($insert ? $this->once() : $this->never())
			->method('insert')
			->with($this->callback(static fn (Attachment $a): bool => $a->getObjectType() === Attachment::TYPE_LINK && $a->getMessageId() === 42));
		$this->attachmentMapper->expects($delete ? $this->once() : $this->never())
			->method('deleteByMessageIdAndType')
			->with(42, Attachment::TYPE_LINK);

		$this->service->updateLinkAttachment($room, $comment, $hadLink);
	}
}
