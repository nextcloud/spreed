<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

$requirementsWithPollId = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
	'pollId' => '\d+',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\PollController::createPoll() */
		['name' => 'Poll#createPoll', 'url' => '/api/{apiVersion}/poll/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\PollController::updateDraftPoll() */
		['name' => 'Poll#updateDraftPoll', 'url' => '/api/{apiVersion}/poll/{token}/draft/{pollId}', 'verb' => 'POST', 'requirements' => $requirementsWithPollId],
		/** @see \OCA\Talk\Controller\PollController::getAllDraftPolls() */
		['name' => 'Poll#getAllDraftPolls', 'url' => '/api/{apiVersion}/poll/{token}/drafts', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\PollController::showPoll() */
		['name' => 'Poll#showPoll', 'url' => '/api/{apiVersion}/poll/{token}/{pollId}', 'verb' => 'GET', 'requirements' => $requirementsWithPollId],
		/** @see \OCA\Talk\Controller\PollController::votePoll() */
		['name' => 'Poll#votePoll', 'url' => '/api/{apiVersion}/poll/{token}/{pollId}', 'verb' => 'POST', 'requirements' => $requirementsWithPollId],
		/** @see \OCA\Talk\Controller\PollController::closePoll() */
		['name' => 'Poll#closePoll', 'url' => '/api/{apiVersion}/poll/{token}/{pollId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithPollId],
	],
];
