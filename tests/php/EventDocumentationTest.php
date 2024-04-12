<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

		$docs = file_get_contents(__DIR__ . '/../../docs/events.md');
		$eventIsDocumented = str_contains($docs, 'Before event: `' . $eventClass . '`')
			|| str_contains($docs, 'After event: `' . $eventClass . '`')
			|| str_contains($docs, 'Final event: `' . $eventClass . '`')
			|| str_contains($docs, 'Event: `' . $eventClass . '`');
		self::assertTrue($eventIsDocumented, 'Asserting that event ' . $eventClass . ' is documented');
	}
}
