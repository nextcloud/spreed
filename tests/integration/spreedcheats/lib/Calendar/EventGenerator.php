<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats\Calendar;

use Sabre\VObject\UUIDUtil;

class EventGenerator {
	public static function generateEvents(string $name, string $location, int $start, int $end): array {
		$files = scandir(__DIR__ . '/../../calendars/');
		$events = [];
		$startDate = (new \DateTime())->setTimestamp($start);
		$endDate = (new \DateTime())->setTimestamp($end);
		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			$calData = file_get_contents(__DIR__ . '/../../calendars/' . $file);
			if ($calData === false) {
				continue;
			}
			$interval = new \DateInterval('P1D');
			$startDate->add($interval);
			$endDate->add($interval);
			$uid = UUIDUtil::getUUID();
			$events[] = str_replace(['{{{NAME}}}', '{{{START}}}', '{{{END}}}', '{{{UID}}}', '{{{LOCATION}}}'], [$name, $startDate->format('Ymd\THis'), $endDate->format('Ymd\THis'), $uid, $location], $calData);
		}
		return $events;
	}
}
