<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Recording;

use OCA\Talk\Recording\SpeakerAttribution;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class SpeakerAttributionTest extends TestCase {
	protected SpeakerAttribution $attribution;

	public function setUp(): void {
		parent::setUp();

		$this->attribution = new SpeakerAttribution();
	}

	public static function dataParseIntervals(): array {
		return [
			'file as sent by the recording backend' => [
				'{
					"recordingStartTimestamp": 1789382338127,
					"intervals": [
						{
							"participantId": "kHhoMTkBUsJh0JK1",
							"participantName": "James Bond",
							"participantUserId": "admin",
							"startTimestamp": 1789382340241,
							"startType": "speaking",
							"stopTimestamp": 1789382344840,
							"stopType": "peerStreamRemoved",
							"startTimestampRelative": 2114,
							"stopTimestampRelative": 6713
						}
					]
				}',
				[
					['start' => 2114, 'end' => 6713, 'name' => 'James Bond'],
				],
			],
			'absolute timestamps without relative ones are shifted by the recording start' => [
				'{"recordingStartTimestamp": 1000000, "intervals": [{"participantName": "Alice", "startTimestamp": 1000500, "stopTimestamp": 1002500}]}',
				[
					['start' => 500, 'end' => 2500, 'name' => 'Alice'],
				],
			],
			'relative timestamps win over absolute ones' => [
				'{"recordingStartTimestamp": 1000000, "intervals": [{"participantName": "Alice", "startTimestamp": 1000500, "stopTimestamp": 1002500, "startTimestampRelative": 10, "stopTimestampRelative": 20}]}',
				[
					['start' => 10, 'end' => 20, 'name' => 'Alice'],
				],
			],
			'sorts intervals by start and keeps only usable ones' => [
				'{"recordingStartTimestamp": 0, "intervals": [
					{"participantName": "Bob", "startTimestampRelative": 500, "stopTimestampRelative": 900},
					{"participantName": "Alice", "startTimestampRelative": 100, "stopTimestampRelative": 400},
					{"participantName": "Inverted", "startTimestampRelative": 700, "stopTimestampRelative": 700},
					{"participantName": "NoTimestamps"},
					"Not an object",
					{"participantUserId": "", "startTimestampRelative": 1, "stopTimestampRelative": 2}
				]}',
				[
					['start' => 100, 'end' => 400, 'name' => 'Alice'],
					['start' => 500, 'end' => 900, 'name' => 'Bob'],
				],
			],
			'falls back to the user id when the display name is missing' => [
				'{"recordingStartTimestamp": 0, "intervals": [{"participantName": "  ", "participantUserId": "user2", "startTimestampRelative": 0, "stopTimestampRelative": 10}]}',
				[
					['start' => 0, 'end' => 10, 'name' => 'user2'],
				],
			],
			'negative relative timestamps are kept' => [
				'{"recordingStartTimestamp": 50000, "intervals": [{"participantName": "Alice", "startTimestamp": 49000, "stopTimestamp": 51000}]}',
				[
					['start' => -1000, 'end' => 1000, 'name' => 'Alice'],
				],
			],
			'no usable intervals' => [
				'{"recordingStartTimestamp": 1, "intervals": []}',
				null,
			],
			'no intervals key' => [
				'{"recordingStartTimestamp": 1}',
				null,
			],
			'no recording start and only absolute timestamps' => [
				'{"intervals": [{"participantName": "Alice", "startTimestamp": 100, "stopTimestamp": 200}]}',
				null,
			],
			'not an object' => [
				'[]',
				null,
			],
		];
	}

	#[DataProvider('dataParseIntervals')]
	public function testParseIntervals(string $content, ?array $expected): void {
		$this->assertSame($expected, $this->attribution->parseIntervals($content));
	}

	public function testParseIntervalsThrowsOnInvalidJson(): void {
		$this->expectException(\JsonException::class);
		$this->attribution->parseIntervals('{invalid');
	}

	public static function dataParseEntries(): array {
		return [
			'standard srt' => [
				"1\r\n00:00:02,500 --> 00:00:04,500\r\nHello world\r\nSecond line\r\n\r\n2\r\n00:00:06,000 --> 00:00:06,500\r\nBye\r\n",
				[
					['index' => 1, 'start' => 2500, 'end' => 4500, 'text' => ['Hello world', 'Second line']],
					['index' => 2, 'start' => 6000, 'end' => 6500, 'text' => ['Bye']],
				],
			],
			'entries without an index get sequential ones' => [
				"00:00:01,000 --> 00:00:02,000\nFirst\n\n00:05:00,000 --> 00:05:02,000\nSecond\n",
				[
					['index' => 1, 'start' => 1000, 'end' => 2000, 'text' => ['First']],
					['index' => 2, 'start' => 300000, 'end' => 302000, 'text' => ['Second']],
				],
			],
			'period as milliseconds separator and short fraction' => [
				"1\n00:00:01.5 --> 00:00:02.125\nHello\n",
				[
					['index' => 1, 'start' => 1500, 'end' => 2125, 'text' => ['Hello']],
				],
			],
			'byte order mark tolerated' => [
				"\xEF\xBB\xBF1\n00:00:01,000 --> 00:00:02,000\nHello\n",
				[
					['index' => 1, 'start' => 1000, 'end' => 2000, 'text' => ['Hello']],
				],
			],
			'plain text without timing lines is kept as-is' => [
				"Not a subtitle file\n\nJust prose\n",
				[
					['index' => 1, 'start' => null, 'end' => null, 'text' => ['Not a subtitle file']],
					['index' => 2, 'start' => null, 'end' => null, 'text' => ['Just prose']],
				],
			],
			'numeric text lines are not eaten by the index' => [
				"1\n00:00:01,000 --> 00:00:02,000\nPage 42\n",
				[
					['index' => 1, 'start' => 1000, 'end' => 2000, 'text' => ['Page 42']],
				],
			],
			'numeric text after the timing line is kept as text' => [
				"00:00:01,000 --> 00:00:02,000\n42\n",
				[
					['index' => 1, 'start' => 1000, 'end' => 2000, 'text' => ['42']],
				],
			],
			'timing line with trailing settings' => [
				"1\n00:00:01,000 --> 00:00:02,000 line:2\nHello\n",
				[
					['index' => 1, 'start' => 1000, 'end' => 2000, 'text' => ['Hello']],
				],
			],
			'empty content' => [
				'',
				[],
			],
		];
	}

	#[DataProvider('dataParseEntries')]
	public function testParseEntries(string $content, array $expected): void {
		$entries = $this->attribution->parseEntries($content);
		$this->assertCount(count($expected), $entries);
		foreach ($entries as $index => $entry) {
			unset($entry['raw']);
			$this->assertEquals($expected[$index], $entry);
		}
	}

	public static function dataFindMatchingIntervals(): array {
		$alice = ['start' => 1000, 'end' => 5000, 'name' => 'Alice'];
		$bob = ['start' => 4000, 'end' => 9000, 'name' => 'Bob'];
		$carol = ['start' => 20000, 'end' => 25000, 'name' => 'Carol'];

		return [
			'no overlap within tolerance picks the closest interval' => [
				9100, 9800, [$alice, $bob, $carol], [$bob],
			],
			'no overlap beyond tolerance matches nothing' => [
				11500, 12000, [$alice, $bob, $carol], [],
			],
			'single overlapping speaker' => [
				2000, 3000, [$alice, $bob, $carol], [$alice],
			],
			'dominant speaker wins when the co-speaker overlaps too little' => [
				4800, 9000, [$alice, $bob, $carol], [$bob],
			],
			'simultaneous speakers are both listed, earlier interval first' => [
				4200, 4800, [$alice, $bob, $carol], [$alice, $bob],
			],
			'same speaker with two overlapping intervals is listed once' => [
				1000, 5000, [
					['start' => 1000, 'end' => 2500, 'name' => 'Alice'],
					['start' => 3500, 'end' => 5000, 'name' => 'Alice'],
				],
				[
					['start' => 1000, 'end' => 2500, 'name' => 'Alice'],
				],
			],
			'no intervals at all' => [
				1000, 2000, [], [],
			],
			'inverted entry matches nothing' => [
				5000, 1000, [$alice], [],
			],
		];
	}

	#[DataProvider('dataFindMatchingIntervals')]
	public function testFindMatchingIntervals(int $start, int $end, array $intervals, array $expected): void {
		$this->assertSame($expected, $this->attribution->findMatchingIntervals($start, $end, $intervals));
	}

	public function testAttributeWithRealisticInput(): void {
		$intervals = '{"recordingStartTimestamp": 1789382338127, "intervals": [
			{"participantName": "James Bond", "participantUserId": "admin", "startTimestampRelative": 2114, "stopTimestampRelative": 6713},
			{"participantName": "Alice", "startTimestampRelative": 30000, "stopTimestampRelative": 35000}
		]}';
		$subtitles = "1\n00:00:02,500 --> 00:00:04,500\nHello world\nSecond line\n\n2\n00:00:06,000 --> 00:00:06,500\nStill here\n\n3\n00:00:30,000 --> 00:00:34,000\nAnother speaker\n";

		$result = $this->attribution->attribute($subtitles, $intervals);

		$this->assertNotNull($result);
		$this->assertSame(
			"1\n00:00:02,500 --> 00:00:04,500\nJames Bond: Hello world\nSecond line\n\n"
			. "2\n00:00:06,000 --> 00:00:06,500\nJames Bond: Still here\n\n"
			. "3\n00:00:30,000 --> 00:00:34,000\nAlice: Another speaker\n",
			$result['srt']
		);
		// Consecutive entries of the same speaker are merged in the transcript
		$this->assertSame(
			"James Bond: Hello world Second line Still here\nAlice: Another speaker",
			$result['transcript']
		);
	}

	public function testAttributeWithSimultaneousSpeakers(): void {
		$intervals = '{"recordingStartTimestamp": 0, "intervals": [
			{"participantName": "Alice", "startTimestampRelative": 0, "stopTimestampRelative": 6000},
			{"participantName": "Bob", "startTimestampRelative": 4000, "stopTimestampRelative": 10000}
		]}';
		$subtitles = "1\n00:00:00,000 --> 00:00:10,000\nWe both talked\n";

		$result = $this->attribution->attribute($subtitles, $intervals);

		$this->assertNotNull($result);
		$this->assertStringContainsString('Alice & Bob: We both talked', $result['srt']);
	}

	public function testAttributeReturnsNullWhenNothingMatches(): void {
		$intervals = '{"recordingStartTimestamp": 0, "intervals": [{"participantName": "Alice", "startTimestampRelative": 100000, "stopTimestampRelative": 110000}]}';
		$subtitles = "1\n00:00:01,000 --> 00:00:02,000\nHello world\n";

		$this->assertNull($this->attribution->attribute($subtitles, $intervals));
	}

	public function testAttributeReturnsNullWithUnusableIntervals(): void {
		$subtitles = "1\n00:00:01,000 --> 00:00:02,000\nHello world\n";

		$this->assertNull($this->attribution->attribute($subtitles, '{"recordingStartTimestamp": 0, "intervals": []}'));
		$this->assertNull($this->attribution->attribute('', '{}'));
	}

	public function testAttributeNameContainingColonDoesNotBreakTranscript(): void {
		$intervals = '{"recordingStartTimestamp": 0, "intervals": [{"participantName": "Dr. Who: The", "startTimestampRelative": 0, "stopTimestampRelative": 10000}]}';
		$subtitles = "1\n00:00:00,000 --> 00:00:04,000\nFirst\n\n2\n00:00:05,000 --> 00:00:09,000\nSecond\n";

		$result = $this->attribution->attribute($subtitles, $intervals);

		$this->assertNotNull($result);
		$this->assertSame('Dr. Who: The: First Second', $result['transcript']);
	}

	public function testSrtToPlainText(): void {
		$subtitles = "1\n00:00:02,500 --> 00:00:04,500\nHello world\nSecond line\n\n2\n00:00:06,000 --> 00:00:06,500\nBye\n";
		$this->assertSame("Hello world Second line\nBye", $this->attribution->srtToPlainText($subtitles));
	}

	public function testSrtToPlainTextWithNonSubtitleContent(): void {
		$content = "Just a plain transcript\n\nSecond paragraph\n";
		$this->assertSame("Just a plain transcript\nSecond paragraph", $this->attribution->srtToPlainText($content));
	}
}
