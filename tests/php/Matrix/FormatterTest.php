<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix;

use Nextcloud\Matrix\Model\Event;
use OCA\Talk\Matrix\Mapping\Formatter;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FormatterTest extends TestCase {
	private AccountMapper&MockObject $accountMapper;
	private MatrixMemberMapper&MockObject $memberMapper;
	private IUserManager&MockObject $userManager;
	private Formatter $formatter;

	protected function setUp(): void {
		parent::setUp();
		$this->accountMapper = $this->createMock(AccountMapper::class);
		$this->memberMapper = $this->createMock(MatrixMemberMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->formatter = new Formatter($this->accountMapper, $this->memberMapper, $this->userManager);

		$alice = new Account();
		$alice->setUserId('alice');
		$alice->setMxid('@alice:hs');
		$this->accountMapper->method('getByMxid')->willReturnCallback(static function (string $mxid) use ($alice): Account {
			if ($mxid === '@alice:hs') {
				return $alice;
			}
			throw new DoesNotExistException('nope');
		});
		$this->accountMapper->method('getByUserId')->willReturnCallback(static function (string $userId) use ($alice): Account {
			if ($userId === 'alice') {
				return $alice;
			}
			throw new DoesNotExistException('nope');
		});
		$this->userManager->method('userExists')->willReturnCallback(static fn (string $uid) => $uid === 'alice');
		$this->userManager->method('getDisplayName')->willReturnCallback(static fn (string $uid) => $uid === 'alice' ? 'Alice A.' : null);
		$bob = new MatrixMember();
		$bob->setMxid('@bob:hs');
		$bob->setDisplayName('Bob');
		$this->memberMapper->method('get')->willReturnCallback(static function (string $roomId, string $mxid) use ($bob): MatrixMember {
			if ($mxid === '@bob:hs') {
				return $bob;
			}
			throw new DoesNotExistException('nope');
		});
	}

	private function event(array $content, string $sender = '@bob:hs'): Event {
		return new Event('$e', 'm.room.message', $sender, 1000, $content, null, [], '!r:hs');
	}

	public function testIncomingHtmlBecomesMarkdownWithMentions(): void {
		$result = $this->formatter->incoming($this->event([
			'msgtype' => 'm.text',
			'body' => 'Hello Alice and Carol',
			'format' => 'org.matrix.custom.html',
			'formatted_body' => 'Hello <a href="https://matrix.to/#/@alice:hs">Alice</a> and <a href="https://matrix.to/#/@carol:hs">Carol</a>, <b>bold</b>',
		]), '!r:hs');
		self::assertSame('Hello @"alice" and Carol, **bold**', $result['message']);
		self::assertFalse($result['truncated']);
	}

	public function testIncomingPlainBodyStripsReplyFallback(): void {
		$result = $this->formatter->incoming($this->event([
			'msgtype' => 'm.text',
			'body' => "> <@alice:hs> earlier message\n> second line\n\nthe actual reply",
		]), '!r:hs');
		self::assertSame('the actual reply', $result['message']);
	}

	public function testIncomingEmoteAndRoomMention(): void {
		$result = $this->formatter->incoming($this->event([
			'msgtype' => 'm.emote',
			'body' => 'waves at @room',
			'm.mentions' => ['room' => true],
		]), '!r:hs');
		self::assertSame('* Bob waves at @all', $result['message']);
	}

	public function testIncomingTruncates(): void {
		$result = $this->formatter->incoming($this->event(['msgtype' => 'm.text', 'body' => str_repeat('x', 40000)]), '!r:hs');
		self::assertTrue($result['truncated']);
		self::assertSame(Formatter::MAX_LENGTH, mb_strlen($result['message']));
	}

	public function testOutgoingMentionsBecomePillsAndMentionList(): void {
		$result = $this->formatter->outgoing('Hi @"alice" and @"matrix/@bob:hs" and @all, see **this**', '!r:hs');
		self::assertSame('Hi Alice A. and Bob and @room, see **this**', $result['body']);
		self::assertSame('Hi <a href="https://matrix.to/#/%40alice%3Ahs">Alice A.</a> and <a href="https://matrix.to/#/%40bob%3Ahs">Bob</a> and @room, see <strong>this</strong>', $result['html']);
		self::assertSame(['@alice:hs', '@bob:hs'], $result['mentions']);
		self::assertTrue($result['mentionRoom']);
	}

	public function testOutgoingPlainTextHasNoHtml(): void {
		$result = $this->formatter->outgoing('just text', '!r:hs');
		self::assertSame('just text', $result['body']);
		self::assertNull($result['html']);
		self::assertSame([], $result['mentions']);
		self::assertFalse($result['mentionRoom']);
	}

	public function testOutgoingUnknownMentionStaysText(): void {
		$result = $this->formatter->outgoing('ping @"nobody"', '!r:hs');
		self::assertSame('ping @"nobody"', $result['body']);
		self::assertNull($result['html']);
	}
}
