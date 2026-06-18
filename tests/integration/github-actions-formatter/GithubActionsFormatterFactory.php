<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Talk\Tests\Integration\Behat\GithubActions;

use Behat\Testwork\Exception\ServiceContainer\ExceptionExtension;
use Behat\Testwork\Output\ServiceContainer\Formatter\FormatterFactory;
use Behat\Testwork\Output\ServiceContainer\OutputExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the {@see GithubActionsFormatter} as a Behat output formatter.
 */
final class GithubActionsFormatterFactory implements FormatterFactory {
	public function buildFormatter(ContainerBuilder $container): void {
		$definition = new Definition(GithubActionsFormatter::class, [
			['base_path' => null],
			new Definition(StdoutPrinter::class),
			// Reuse Behat's exception presenter so failure messages match the
			// `pretty` output, including PHPUnit comparison diffs.
			new Reference(ExceptionExtension::PRESENTER_ID),
		]);
		$definition->addTag(OutputExtension::FORMATTER_TAG, ['priority' => 100]);

		$container->setDefinition(
			OutputExtension::FORMATTER_TAG . '.github_actions',
			$definition,
		);
	}

	public function processFormatter(ContainerBuilder $container): void {
	}
}
