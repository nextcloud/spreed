<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Formatter\JUnitFormatter;
use Behat\Config\Formatter\PrettyFormatter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use OCA\Talk\Tests\Integration\Behat\GithubActions\GithubActionsExtension;
use OCA\Talk\Tests\Integration\Contexts\FeatureContext;
use OCA\Talk\Tests\Integration\Contexts\SharingContext;

return (new Config())
	->withProfile((new Profile('default', [
		'autoload' => [
			'' => '%paths.base%/features/bootstrap',
		],
	]))
		->withFormatter((new JUnitFormatter())
			->withOutputPath('%paths.base%/output'))
		->withFormatter((new PrettyFormatter())
			->withOutputStyles([
				'comment' => [
					'bright-blue',
				],
			]))
		->withExtension(new Extension(GithubActionsExtension::class))
		->withSuite((new Suite('default'))
			->addContext(FeatureContext::class)
			->addContext(
				SharingContext::class,
				[
					'baseUrl' => 'http://localhost:8080/',
					'admin' => [
						'admin',
						'admin',
					],
					'regularUserPassword' => 123456,
				]
			)
			->withPaths('%paths.base%/features')));
