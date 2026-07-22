<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Nextcloud\Rector\Rector\ReplaceInjectedMethodCallRector;
use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/appinfo',
		__DIR__ . '/lib',
		__DIR__ . '/templates',
		__DIR__ . '/tests/php',
	])
	->withSkipPath(__DIR__ . '/lib/Vendor')
	->withPhpSets(php83: true)
	->withSets([
		PHPUnitSetList::PHPUNIT_120,
		PHPUnitSetList::PHPUNIT_NARROW_ASSERTS,
		PHPUnitSetList::PHPUNIT_MOCK_TO_STUB,
		NextcloudSets::NEXTCLOUD_35,
	])
	->withSkip([
		ReplaceInjectedMethodCallRector::class,
	])
	->withTypeCoverageLevel(0);
