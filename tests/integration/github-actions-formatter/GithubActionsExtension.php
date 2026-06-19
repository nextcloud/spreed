<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Talk\Tests\Integration\Behat\GithubActions;

use Behat\Testwork\Output\ServiceContainer\OutputExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Behat extension that wires up the GitHub Actions output formatter.
 *
 * Enable it in behat.yml:
 *
 *     default:
 *       extensions:
 *         OCA\Talk\Tests\Integration\Behat\GithubActions\GithubActionsExtension: ~
 *
 * and select the `github_actions` formatter (e.g. `behat -f github_actions`).
 */
final class GithubActionsExtension implements Extension {
	public function getConfigKey(): string {
		return 'github_actions';
	}

	public function initialize(ExtensionManager $extensionManager): void {
		$output = $extensionManager->getExtension('formatters');
		if ($output instanceof OutputExtension) {
			$output->registerFormatterFactory(new GithubActionsFormatterFactory());
		}
	}

	public function configure(ArrayNodeDefinition $builder): void {
	}

	public function load(ContainerBuilder $container, array $config): void {
	}

	public function process(ContainerBuilder $container): void {
	}
}
