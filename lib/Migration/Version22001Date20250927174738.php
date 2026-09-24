<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCP\Migration\SimpleMigrationStep;

/**
 * Due to a missing return the migration content was never executed.
 * The changes are reapplied in @see Version25000Date20260923155555
 */
class Version22001Date20250927174738 extends SimpleMigrationStep {
}
