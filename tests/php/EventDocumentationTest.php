<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\Unit;

use Test\TestCase;

class EventDocumentationTest extends TestCase {
	public static function dataEventDocumentation(): array {
		$dir = new \DirectoryIterator(__DIR__ . '/../../lib/Events');

		$data = [];
		foreach ($dir as $fileinfo) {
			if (!$fileinfo->isDot()) {
				$data[] = ['OCA\\Talk\\Events\\' . substr($fileinfo->getFilename(), 0, -4)];
			}
		}
		sort($data);
		return $data;
	}

	/**
	 * @dataProvider dataEventDocumentation
	 */
	public function testEventDocumentation(string $eventClass): void {
		$reflectionClass = new \ReflectionClass($eventClass);
		if ($reflectionClass->isAbstract()) {
			self::assertTrue(true, 'Abstract event class ' . $eventClass . ' does not have to be documented');
			return;
		}

		$classDocBlock = $reflectionClass->getDocComment();
		if (is_string($classDocBlock) && str_contains($classDocBlock, '@deprecated')) {
			self::assertTrue(true, 'Deprecated event ' . $eventClass . ' does not have to be documented');
			return;
		}
		if (is_string($classDocBlock) && str_contains($classDocBlock, '@internal')) {
			self::assertTrue(true, 'Internal event ' . $eventClass . ' does not have to be documented');
			return;
		}

		$docs = file_get_contents(__DIR__ . '/../../docs/events.md');
		$eventIsDocumented = str_contains($docs, 'Before event: `' . $eventClass . '`')
			|| str_contains($docs, 'After event: `' . $eventClass . '`')
			|| str_contains($docs, 'Final event: `' . $eventClass . '`')
			|| str_contains($docs, 'Event: `' . $eventClass . '`');
		self::assertTrue($eventIsDocumented, 'Asserting that event ' . $eventClass . ' is documented');
	}
}
