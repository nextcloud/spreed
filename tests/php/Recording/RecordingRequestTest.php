<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Recording;

use OCA\Talk\Recording\RecordingRequest;
use OCA\Talk\Room;
use OCA\Talk\Vendor\CuyZ\Valinor\Mapper\Source\Source;
use OCA\Talk\Vendor\CuyZ\Valinor\MapperBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class RecordingRequestTest extends TestCase {
	public static function dataRequests(): array {
		return [
			[
				[
					'type' => 'started',
					'started' => [
						'token' => '0123456789',
						'actor' => [
							'type' => 'users',
							'id' => 'user1',
						],
						'status' => Room::RECORDING_VIDEO,
					],
				],
			],
			[
				[
					'type' => 'started',
					'started' => [
						'token' => '0123456789',
						'actor' => [
							'type' => 'users',
							'id' => 'user1',
						],
						'status' => Room::RECORDING_AUDIO,
					],
				],
			],

			[
				[
					'type' => 'stopped',
					'stopped' => [
						'token' => '0123456789',
						'actor' => [
							'type' => 'users',
							'id' => 'user1',
						],
					],
				],
			],
			[
				[
					'type' => 'stopped',
					'stopped' => [
						'token' => '0123456789',
					],
				],
			],

			[
				[
					'type' => 'failed',
					'failed' => [
						'token' => '0123456789',
					],
				],
			],
		];
	}

	#[DataProvider('dataRequests')]
	public function testRequests(array $requestArray): void {
		$json = json_encode($requestArray, JSON_THROW_ON_ERROR);

		$request = (new MapperBuilder())
			->mapper()
			->map(RecordingRequest::class, Source::json($json));

		$this->assertInstanceOf(RecordingRequest::class, $request);
	}
}
