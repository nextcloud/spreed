<?php
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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
 */

namespace OCA\Talk\Tests\php\Command;

use OCP\App\IAppManager;
use Test\TestCase;

final class DocumentationTest extends TestCase {
	public function testCommandIsDocumented(): void {
		/** @var IAppManager */
		$appManager = \OC::$server->get(IAppManager::class);
		$info = $appManager->getAppInfo('spreed');
		foreach ($info['commands'] as $commandNamespace) {
			$path = str_replace('OCA\Talk', '', $commandNamespace);
			$path = str_replace('\\', '/', $path);
			$path = __DIR__ . '/../../../lib' . $path . '.php';
			$this->assertFileExists($path, 'The class of the follow namespase is not implemented: ' . $commandNamespace);
			$code = file_get_contents($path);
			preg_match("/->setName\('(?<command>[\w:\-_]+)'\)/", $code, $matches);
			if (!array_key_exists('command', $matches)) {
				preg_match("/\tname: '(?<command>[\w:\-_]+)',?\n/", $code, $matches);
			}
			$this->assertArrayHasKey('command', $matches, 'A command need to have a name.');
			$this->commandIsDocummented($matches['command']);
		}
	}

	public function commandIsDocummented(string $command): void {
		$docs = file_get_contents(__DIR__ . '/../../../docs/occ.md');
		self::assertStringContainsString(
			$command,
			$docs,
			'The command ' . $command . " haven't documentation. Run the command talk:developer:update-docs."
		);
	}
}
