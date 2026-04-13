<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Vote;
use OCA\Talk\Service\PollExportService;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;
use ZipArchive;

class PollExportServiceTest extends TestCase {
	protected PollExportService $service;

	public function setUp(): void {
		parent::setUp();
		$this->service = new PollExportService();
	}

	private function createPoll(int $status = Poll::STATUS_CLOSED, int $resultMode = Poll::MODE_PUBLIC): Poll {
		$poll = new Poll();
		$poll->setRoomId(1);
		$poll->setActorType('users');
		$poll->setActorId('admin');
		$poll->setDisplayName('Admin');
		$poll->setQuestion('What is the best color?');
		$poll->setOptions(['Red', 'Blue', 'Green']);
		$poll->setVotes(json_encode([0 => 2, 1 => 3, 2 => 1]));
		$poll->setNumVoters(6);
		$poll->setStatus($status);
		$poll->setResultMode($resultMode);
		$poll->setMaxVotes(1);
		return $poll;
	}

	/**
	 * @return list<Vote>
	 */
	private function createVotes(): array {
		$votes = [];

		$vote1 = new Vote();
		$vote1->setPollId(1);
		$vote1->setRoomId(1);
		$vote1->setActorType('users');
		$vote1->setActorId('user1');
		$vote1->setDisplayName('User One');
		$vote1->setOptionId(0);
		$votes[] = $vote1;

		$vote2 = new Vote();
		$vote2->setPollId(1);
		$vote2->setRoomId(1);
		$vote2->setActorType('users');
		$vote2->setActorId('user2');
		$vote2->setDisplayName('User Two');
		$vote2->setOptionId(1);
		$votes[] = $vote2;

		$vote3 = new Vote();
		$vote3->setPollId(1);
		$vote3->setRoomId(1);
		$vote3->setActorType('users');
		$vote3->setActorId('user3');
		$vote3->setDisplayName('User Three');
		$vote3->setOptionId(1);
		$votes[] = $vote3;

		return $votes;
	}

	public static function dataFormats(): array {
		return [
			['xlsx'],
			['ods'],
		];
	}

	#[DataProvider('dataFormats')]
	public function testExportProducesValidZipArchive(string $format): void {
		$poll = $this->createPoll();
		$votes = $this->createVotes();

		$content = $this->service->exportToSpreadsheet($poll, $votes, $format);

		$this->assertNotEmpty($content);

		// Write to temp file and verify it's a valid ZIP
		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$result = $zip->open($tempFile);
		$this->assertTrue($result === true, 'Generated file should be a valid ZIP archive');
		$zip->close();

		unlink($tempFile);
	}

	public function testXlsxContainsRequiredFiles(): void {
		$poll = $this->createPoll();
		$votes = $this->createVotes();

		$content = $this->service->exportToSpreadsheet($poll, $votes, 'xlsx');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$this->assertNotFalse($zip->locateName('[Content_Types].xml'), 'Should contain [Content_Types].xml');
		$this->assertNotFalse($zip->locateName('_rels/.rels'), 'Should contain _rels/.rels');
		$this->assertNotFalse($zip->locateName('xl/workbook.xml'), 'Should contain xl/workbook.xml');
		$this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'), 'Should contain Summary sheet');
		$this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'), 'Should contain Votes sheet when details available');
		$this->assertNotFalse($zip->locateName('xl/styles.xml'), 'Should contain xl/styles.xml');
		$this->assertNotFalse($zip->locateName('xl/sharedStrings.xml'), 'Should contain xl/sharedStrings.xml');

		$zip->close();
		unlink($tempFile);
	}

	public function testXlsxSummarySheetContainsPollData(): void {
		$poll = $this->createPoll();

		$content = $this->service->exportToSpreadsheet($poll, [], 'xlsx');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
		$this->assertStringContainsString('What is the best color?', $sharedStrings);
		$this->assertStringContainsString('Red', $sharedStrings);
		$this->assertStringContainsString('Blue', $sharedStrings);
		$this->assertStringContainsString('Green', $sharedStrings);
		$this->assertStringContainsString('Question', $sharedStrings);
		$this->assertStringContainsString('Option', $sharedStrings);
		$this->assertStringContainsString('Votes', $sharedStrings);
		$this->assertStringContainsString('Percentage', $sharedStrings);

		$zip->close();
		unlink($tempFile);
	}

	public function testXlsxWithoutVotesHasNoSecondSheet(): void {
		$poll = $this->createPoll();

		$content = $this->service->exportToSpreadsheet($poll, [], 'xlsx');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$this->assertFalse($zip->locateName('xl/worksheets/sheet2.xml'), 'Should not contain Votes sheet when no details');

		// Workbook should only reference one sheet
		$workbook = $zip->getFromName('xl/workbook.xml');
		$this->assertStringNotContainsString('Votes', $workbook);

		$zip->close();
		unlink($tempFile);
	}

	public function testXlsxVotesSheetContainsVoterData(): void {
		$poll = $this->createPoll();
		$votes = $this->createVotes();

		$content = $this->service->exportToSpreadsheet($poll, $votes, 'xlsx');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
		$this->assertStringContainsString('User One', $sharedStrings);
		$this->assertStringContainsString('User Two', $sharedStrings);
		$this->assertStringContainsString('User Three', $sharedStrings);
		$this->assertStringContainsString('Voter', $sharedStrings);

		$zip->close();
		unlink($tempFile);
	}

	public function testOdsContainsRequiredFiles(): void {
		$poll = $this->createPoll();
		$votes = $this->createVotes();

		$content = $this->service->exportToSpreadsheet($poll, $votes, 'ods');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$this->assertNotFalse($zip->locateName('mimetype'), 'Should contain mimetype');
		$this->assertNotFalse($zip->locateName('META-INF/manifest.xml'), 'Should contain manifest');
		$this->assertNotFalse($zip->locateName('content.xml'), 'Should contain content.xml');
		$this->assertNotFalse($zip->locateName('styles.xml'), 'Should contain styles.xml');
		$this->assertNotFalse($zip->locateName('meta.xml'), 'Should contain meta.xml');

		// Verify mimetype is correct
		$mimetype = $zip->getFromName('mimetype');
		$this->assertEquals('application/vnd.oasis.opendocument.spreadsheet', $mimetype);

		$zip->close();
		unlink($tempFile);
	}

	public function testOdsContentContainsPollData(): void {
		$poll = $this->createPoll();
		$votes = $this->createVotes();

		$content = $this->service->exportToSpreadsheet($poll, $votes, 'ods');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$contentXml = $zip->getFromName('content.xml');

		// Summary sheet data
		$this->assertStringContainsString('What is the best color?', $contentXml);
		$this->assertStringContainsString('Red', $contentXml);
		$this->assertStringContainsString('Blue', $contentXml);
		$this->assertStringContainsString('Green', $contentXml);
		$this->assertStringContainsString('table:name="Summary"', $contentXml);

		// Votes sheet data
		$this->assertStringContainsString('table:name="Votes"', $contentXml);
		$this->assertStringContainsString('User One', $contentXml);
		$this->assertStringContainsString('User Two', $contentXml);
		$this->assertStringContainsString('User Three', $contentXml);

		$zip->close();
		unlink($tempFile);
	}

	public function testOdsWithoutVotesHasNoVotesSheet(): void {
		$poll = $this->createPoll();

		$content = $this->service->exportToSpreadsheet($poll, [], 'ods');

		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);

		$contentXml = $zip->getFromName('content.xml');
		$this->assertStringContainsString('table:name="Summary"', $contentXml);
		$this->assertStringNotContainsString('table:name="Votes"', $contentXml);

		$zip->close();
		unlink($tempFile);
	}

	public function testSpecialCharactersAreEscaped(): void {
		$poll = new Poll();
		$poll->setRoomId(1);
		$poll->setActorType('users');
		$poll->setActorId('admin');
		$poll->setDisplayName('Admin');
		$poll->setQuestion('Is 5 > 3 & 2 < 4?');
		$poll->setOptions(['Yes "obviously"', 'No & never']);
		$poll->setVotes(json_encode([0 => 1, 1 => 0]));
		$poll->setNumVoters(1);
		$poll->setStatus(Poll::STATUS_CLOSED);
		$poll->setResultMode(Poll::MODE_PUBLIC);
		$poll->setMaxVotes(1);

		// Should not throw any XML parsing errors
		$xlsxContent = $this->service->exportToSpreadsheet($poll, [], 'xlsx');
		$this->assertNotEmpty($xlsxContent);

		$odsContent = $this->service->exportToSpreadsheet($poll, [], 'ods');
		$this->assertNotEmpty($odsContent);

		// Verify the XLSX shared strings contain properly escaped text
		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $xlsxContent);
		$zip = new ZipArchive();
		$zip->open($tempFile);
		$sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
		$this->assertStringContainsString('&amp;', $sharedStrings);
		$this->assertStringContainsString('&gt;', $sharedStrings);
		$this->assertStringContainsString('&lt;', $sharedStrings);
		$zip->close();
		unlink($tempFile);
	}

	public function testOpenPollShowsOpenStatus(): void {
		$poll = $this->createPoll(Poll::STATUS_OPEN);

		$content = $this->service->exportToSpreadsheet($poll, [], 'xlsx');
		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);
		$sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
		$this->assertStringContainsString('Open', $sharedStrings);
		$zip->close();
		unlink($tempFile);
	}

	public function testClosedPollShowsClosedStatus(): void {
		$poll = $this->createPoll(Poll::STATUS_CLOSED);

		$content = $this->service->exportToSpreadsheet($poll, [], 'xlsx');
		$tempFile = tempnam(sys_get_temp_dir(), 'poll_test_');
		file_put_contents($tempFile, $content);

		$zip = new ZipArchive();
		$zip->open($tempFile);
		$sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
		$this->assertStringContainsString('Closed', $sharedStrings);
		$zip->close();
		unlink($tempFile);
	}

	public function testEmptyVotesGeneratesValidFile(): void {
		$poll = new Poll();
		$poll->setRoomId(1);
		$poll->setActorType('users');
		$poll->setActorId('admin');
		$poll->setDisplayName('Admin');
		$poll->setQuestion('Empty poll');
		$poll->setOptions(['A', 'B']);
		$poll->setVotes(json_encode([]));
		$poll->setNumVoters(0);
		$poll->setStatus(Poll::STATUS_OPEN);
		$poll->setResultMode(Poll::MODE_PUBLIC);
		$poll->setMaxVotes(1);

		$xlsxContent = $this->service->exportToSpreadsheet($poll, [], 'xlsx');
		$this->assertNotEmpty($xlsxContent);

		$odsContent = $this->service->exportToSpreadsheet($poll, [], 'ods');
		$this->assertNotEmpty($odsContent);
	}
}
