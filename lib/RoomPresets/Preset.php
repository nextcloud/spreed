<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\RoomPresets;


enum Preset: int
{
	case DEFAULT = 0;
	case WEBINAR = 1;
	case PRESENTATION = 2;
	case HALLWAY = 3;
}
