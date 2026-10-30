<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Import;

use OCA\Talk\Import\CanonicalForm;
use OCA\Talk\Import\ImportActor;
use OCA\Talk\Import\ImportMessage;
use OCA\Talk\Import\ImportReaction;
use Test\TestCase;

class CanonicalFormTest extends TestCase {
	/**
	 * The vectors were computed independently (Python's json.dumps with
	 * ensure_ascii=False and compact separators), so an importer in any
	 * language can check its implementation against them.
	 */
	public function testTextMessageVector(): void {
		$message = new ImportMessage(
			'msg-1',
			new ImportActor('users', 'alice'),
			new \DateTimeImmutable('2026-10-30T10:15:00+01:00'),
			'Hello 👋 <b>& "quoted" a/b',
			fileIds: [42, 7],
			reactions: [
				new ImportReaction(new ImportActor('users', 'bob'), '❤️', new \DateTimeImmutable('2026-10-30T09:20:00Z')),
				new ImportReaction(new ImportActor('imported', 'ph-1', 'Former colleague'), '👍', new \DateTimeImmutable('2026-10-30T09:21:00Z')),
			],
		);

		$this->assertSame('bc16b20454234bd447ed8768403d3a25ec7ae72476b8e29a5ba0c54e86cedb59', CanonicalForm::hashMessage($message));
	}

	public function testSystemMessageVector(): void {
		$message = new ImportMessage(
			'sys-1',
			new ImportActor('imported', 'ph-1', 'Former colleague'),
			new \DateTimeImmutable('2026-10-30T09:16:00Z'),
			systemKind: ImportMessage::SYSTEM_USER_ADDED,
			members: [
				new ImportActor('users', 'alice'),
				new ImportActor('imported', 'ph-2', 'Guest'),
			],
		);

		$this->assertSame('98cbc581bab42a37bcf6f2cbd7292fb8d444a557d9bbe948a2282afe6e782921', CanonicalForm::hashMessage($message));
	}

	public function testOrderAndNamesDoNotMatter(): void {
		$created = new \DateTimeImmutable('2026-01-01T00:00:00Z');
		$a = new ImportMessage('r', new ImportActor('imported', 'p', 'One name'), $created, 'x', fileIds: [3, 1, 2]);
		$b = new ImportMessage('r', new ImportActor('imported', 'p', 'Another name'), $created, 'x', fileIds: [1, 2, 3]);
		$this->assertSame(CanonicalForm::hashMessage($a), CanonicalForm::hashMessage($b));
	}

	public function testNumericStringsSortByteWise(): void {
		$created = new \DateTimeImmutable('2026-01-01T00:00:00Z');
		$this->assertSame(
			CanonicalForm::hash('r', '', 'users', 'u', $created, '', [], [['users', '10', '👍'], ['users', '9', '👍']], '', []),
			CanonicalForm::hash('r', '', 'users', 'u', $created, '', [], [['users', '9', '👍'], ['users', '10', '👍']], '', []),
		);
	}

	public function testContentChangesTheHash(): void {
		$created = new \DateTimeImmutable('2026-01-01T00:00:00Z');
		$base = CanonicalForm::hash('r', '', 'users', 'u', $created, 'text', [], [], '', []);
		$this->assertNotSame($base, CanonicalForm::hash('r', '', 'users', 'u', $created, 'text!', [], [], '', []));
		$this->assertNotSame($base, CanonicalForm::hash('r', 'p', 'users', 'u', $created, 'text', [], [], '', []));
		$this->assertNotSame($base, CanonicalForm::hash('r', '', 'users', 'u', $created->modify('+1 second'), 'text', [], [], '', []));
		$this->assertNotSame($base, CanonicalForm::hash('r', '', 'imported', 'u', $created, 'text', [], [], '', []));
	}
}
