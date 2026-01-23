<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\OpenMetrics;

use Generator;
use OCA\Talk\Service\MetricsService;
use OCP\OpenMetrics\IMetricFamily;
use OCP\OpenMetrics\Metric;
use OCP\OpenMetrics\MetricType;
use Override;

class ActiveCalls implements IMetricFamily {
	public function __construct(
		private MetricsService $metricsService,
	) {
	}

	#[Override]
	public function name(): string {
		return 'talk_active_calls';
	}

	#[Override]
	public function type(): MetricType {
		return MetricType::gauge;
	}

	#[Override]
	public function unit(): string {
		return 'calls';
	}

	#[Override]
	public function help(): string {
		return 'Number of active calls in talk';
	}

	#[Override]
	public function metrics(): Generator {
		yield new Metric($this->metricsService->getNumberOfActiveCalls(), [], time());
	}
}
