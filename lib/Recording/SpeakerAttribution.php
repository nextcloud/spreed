<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

/**
 * Deterministically attributes subtitle entries to the speakers reported by the
 * recording backend in the "speaking times" sidecar JSON file.
 */
class SpeakerAttribution {
	/**
	 * Maximum gap in milliseconds between a subtitle entry and the closest
	 * speaking interval for that interval's speaker to still be used when
	 * nothing overlaps.
	 */
	public const NO_OVERLAP_TOLERANCE_MS = 2000;

	/**
	 * Minimum share of a subtitle entry's duration an interval must overlap to
	 * be listed as a co-speaker next to the most dominant one.
	 */
	public const MIN_OVERLAP_RATIO = 0.3;

	/**
	 * Parse the speaking intervals sidecar file into normalized entries.
	 * Relative timestamps are preferred, otherwise the recording start
	 * timestamp is subtracted from the absolute ones so both share the
	 * timeline of the generated subtitles.
	 *
	 * @return list<array{start: int, end: int, name: string}>|null Intervals sorted by start time, or null when none are usable
	 * @throws \JsonException When the content is not valid JSON
	 */
	public function parseIntervals(string $intervalsContent): ?array {
		$data = json_decode($intervalsContent, associative: true, flags: \JSON_THROW_ON_ERROR);
		if (!is_array($data)) {
			return null;
		}

		$recordingStart = $this->asTimestamp($data['recordingStartTimestamp'] ?? null);
		$rawIntervals = $data['intervals'] ?? null;
		if (!is_array($rawIntervals)) {
			return null;
		}

		$intervals = [];
		foreach ($rawIntervals as $rawInterval) {
			if (!is_array($rawInterval)) {
				continue;
			}

			$name = $this->asName($rawInterval['participantName'] ?? null)
				?? $this->asName($rawInterval['participantUserId'] ?? null);
			if ($name === null) {
				continue;
			}

			$start = $this->asTimestamp($rawInterval['startTimestampRelative'] ?? null);
			$end = $this->asTimestamp($rawInterval['stopTimestampRelative'] ?? null);
			if ($start === null || $end === null) {
				if ($recordingStart === null) {
					continue;
				}
				$absoluteStart = $this->asTimestamp($rawInterval['startTimestamp'] ?? null);
				$absoluteEnd = $this->asTimestamp($rawInterval['stopTimestamp'] ?? null);
				if ($absoluteStart === null || $absoluteEnd === null) {
					continue;
				}
				$start = $absoluteStart - $recordingStart;
				$end = $absoluteEnd - $recordingStart;
			}

			if ($start >= $end) {
				continue;
			}

			$intervals[] = ['start' => $start, 'end' => $end, 'name' => $name];
		}

		if ($intervals === []) {
			return null;
		}

		usort($intervals, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

		return $intervals;
	}

	/**
	 * Split subtitle content into entries. Blocks without a timing line are
	 * kept as-is (with null start and end) so non-subtitle output is not lost.
	 *
	 * @return list<array{index: int, start: int|null, end: int|null, text: list<string>, raw: string}>
	 */
	public function parseEntries(string $subtitleContent): array {
		$subtitleContent = ltrim($subtitleContent, "\xEF\xBB\xBF");
		$blocks = preg_split('/\r?\n\s*\r?\n/', trim($subtitleContent));
		if ($blocks === false) {
			return [];
		}

		$entries = [];
		$nextIndex = 1;
		foreach ($blocks as $block) {
			$lines = preg_split('/\r?\n/', trim($block));
			if ($lines === false || $lines === []) {
				continue;
			}

			$index = null;
			$start = null;
			$end = null;
			$text = [];
			foreach ($lines as $line) {
				$trimmed = trim($line);
				if ($trimmed === '') {
					continue;
				}
				if ($index === null && $text === [] && preg_match('/^\d+$/', $trimmed) === 1) {
					$index = (int)$trimmed;
					continue;
				}
				if ($start === null && preg_match('/^(\d+):(\d{2}):(\d{2})[,.](\d{1,3})\s*-->\s*(\d+):(\d{2}):(\d{2})[,.](\d{1,3})/', $trimmed, $matches) === 1) {
					$start = self::timestampToMilliseconds((int)$matches[1], (int)$matches[2], (int)$matches[3], $matches[4]);
					$end = self::timestampToMilliseconds((int)$matches[5], (int)$matches[6], (int)$matches[7], $matches[8]);
					continue;
				}
				$text[] = $line;
			}

			if ($text === []) {
				continue;
			}

			$entries[] = [
				'index' => $index ?? $nextIndex,
				'start' => $start,
				'end' => $end,
				'text' => $text,
				'raw' => implode("\n", $lines),
			];
			$nextIndex = ($index ?? $nextIndex) + 1;
		}

		return $entries;
	}

	/**
	 * Find the speaking intervals that best match a subtitle entry: every
	 * interval overlapping at least MIN_OVERLAP_RATIO of the entry duration,
	 * most overlapping one first. When nothing overlaps, the closest interval
	 * is used if its gap stays within NO_OVERLAP_TOLERANCE_MS.
	 *
	 * @param list<array{start: int, end: int, name: string}> $intervals Intervals sorted by start time
	 * @return list<array{start: int, end: int, name: string}> Matching intervals, most significant first, empty when none matched
	 */
	public function findMatchingIntervals(int $start, int $end, array $intervals): array {
		if ($intervals === [] || $end <= $start) {
			return [];
		}

		$overlaps = [];
		foreach ($intervals as $interval) {
			$overlap = min($end, $interval['end']) - max($start, $interval['start']);
			if ($overlap > 0) {
				$overlaps[] = ['interval' => $interval, 'overlap' => $overlap];
			}
		}

		if ($overlaps === []) {
			$closest = null;
			$closestGap = PHP_INT_MAX;
			foreach ($intervals as $interval) {
				$gap = max($interval['start'] - $end, $start - $interval['end'], 0);
				if ($gap < $closestGap) {
					$closestGap = $gap;
					$closest = $interval;
				}
			}
			if ($closest !== null && $closestGap <= self::NO_OVERLAP_TOLERANCE_MS) {
				return [$closest];
			}
			return [];
		}

		usort($overlaps, static fn (array $a, array $b): int => $b['overlap'] <=> $a['overlap'] ?: $a['interval']['start'] <=> $b['interval']['start']);

		$threshold = (float)($end - $start) * self::MIN_OVERLAP_RATIO;
		$matching = [];
		$names = [];
		foreach ($overlaps as $position => $entry) {
			// The most dominant interval is always kept, co-speakers only when
			// they overlap a significant part of the entry
			if ($position > 0 && $entry['overlap'] < $threshold) {
				continue;
			}
			if (in_array($entry['interval']['name'], $names, true)) {
				continue;
			}
			$names[] = $entry['interval']['name'];
			$matching[] = $entry['interval'];
		}

		return $matching;
	}

	/**
	 * Attribute every subtitle entry to its matching speaker(s).
	 *
	 * @return array{srt: string, transcript: string}|null Null when the intervals are unusable or no entry could be attributed
	 * @throws \JsonException When the intervals content is not valid JSON
	 */
	public function attribute(string $subtitleContent, string $intervalsContent): ?array {
		$intervals = $this->parseIntervals($intervalsContent);
		if ($intervals === null) {
			return null;
		}

		$entries = $this->parseEntries($subtitleContent);
		if ($entries === []) {
			return null;
		}

		$srtBlocks = [];
		$transcriptLines = [];
		$lastSpeaker = null;
		$anySpeakerMatched = false;

		foreach ($entries as $entry) {
			$speaker = '';
			if ($entry['start'] !== null && $entry['end'] !== null) {
				$matching = $this->findMatchingIntervals($entry['start'], $entry['end'], $intervals);
				if ($matching !== []) {
					$speaker = implode(' & ', array_column($matching, 'name'));
				}
			}

			if ($speaker !== '') {
				$anySpeakerMatched = true;
			}

			if ($entry['start'] === null || $entry['end'] === null) {
				$srtBlocks[] = $entry['raw'];
			} else {
				$text = $entry['text'];
				if ($speaker !== '') {
					$text[0] = $speaker . ': ' . $text[0];
				}
				$srtBlocks[] = $entry['index'] . "\n"
					. self::formatTimestamp($entry['start']) . ' --> ' . self::formatTimestamp($entry['end']) . "\n"
					. implode("\n", $text);
			}

			$text = trim(implode(' ', $entry['text']));
			if ($text === '') {
				continue;
			}
			if ($speaker !== '' && $speaker === $lastSpeaker && $transcriptLines !== []) {
				$transcriptLines[count($transcriptLines) - 1] .= ' ' . $text;
			} else {
				$transcriptLines[] = $speaker !== '' ? $speaker . ': ' . $text : $text;
				$lastSpeaker = $speaker !== '' ? $speaker : null;
			}
		}

		if (!$anySpeakerMatched) {
			return null;
		}

		return [
			'srt' => implode("\n\n", $srtBlocks) . "\n",
			'transcript' => implode("\n", $transcriptLines),
		];
	}

	/**
	 * Extract the plain text of subtitle entries, one line per entry.
	 */
	public function srtToPlainText(string $subtitleContent): string {
		$lines = [];
		foreach ($this->parseEntries($subtitleContent) as $entry) {
			$text = trim(implode(' ', $entry['text']));
			if ($text !== '') {
				$lines[] = $text;
			}
		}
		return implode("\n", $lines);
	}

	private function asTimestamp(mixed $value): ?int {
		if (is_int($value)) {
			return $value;
		}
		if (is_float($value) && is_finite($value) && floor($value) === $value) {
			return (int)$value;
		}
		return null;
	}

	private function asName(mixed $value): ?string {
		if (is_string($value)) {
			$value = trim($value);
			if ($value !== '') {
				return $value;
			}
		}
		return null;
	}

	private static function timestampToMilliseconds(int $hours, int $minutes, int $seconds, string $millis): int {
		return $hours * 3_600_000 + $minutes * 60_000 + $seconds * 1_000 + (int)str_pad($millis, 3, '0');
	}

	private static function formatTimestamp(int $ms): string {
		$hours = intdiv($ms, 3_600_000);
		$minutes = intdiv($ms % 3_600_000, 60_000);
		$seconds = intdiv($ms % 60_000, 1_000);
		$millis = $ms % 1_000;
		return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $seconds, $millis);
	}
}
