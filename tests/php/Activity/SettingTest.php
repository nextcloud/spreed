<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Activity;

use OCA\Talk\Activity\Setting;
use OCP\Activity\ISetting;
use Test\TestCase;

class SettingTest extends TestCase {
	public static function dataSettings(): array {
		return [
			[Setting::class],
		];
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testImplementsInterface(string $settingClass): void {
		$setting = \OCP\Server::get($settingClass);
		$this->assertInstanceOf(ISetting::class, $setting);
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testGetIdentifier(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$this->assertIsString($setting->getIdentifier());
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testGetName(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$this->assertIsString($setting->getName());
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testGetPriority(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$priority = $setting->getPriority();
		$this->assertIsInt($setting->getPriority());
		$this->assertGreaterThanOrEqual(0, $priority);
		$this->assertLessThanOrEqual(100, $priority);
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testCanChangeStream(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$this->assertIsBool($setting->canChangeStream());
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testIsDefaultEnabledStream(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$this->assertIsBool($setting->isDefaultEnabledStream());
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testCanChangeMail(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$this->assertIsBool($setting->canChangeMail());
	}

	/**
	 * @dataProvider dataSettings
	 */
	public function testIsDefaultEnabledMail(string $settingClass): void {
		/** @var ISetting $setting */
		$setting = \OCP\Server::get($settingClass);
		$this->assertIsBool($setting->isDefaultEnabledMail());
	}
}
