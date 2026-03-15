<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Model\ConversationSection;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ConversationSectionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type TalkConversationSection from ResponseDefinitions
 */
class ConversationSectionController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected ConversationSectionService $sectionService,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all conversation sections for the current user
	 *
	 * Required capability: `conversation-sections`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationSection>, array{}>
	 *
	 * 200: Sections returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/sections', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function getSections(): DataResponse {
		$sections = $this->sectionService->getSections($this->userId);
		return new DataResponse(array_values(array_map([$this, 'formatSection'], $sections)));
	}

	/**
	 * Create a new conversation section
	 *
	 * Required capability: `conversation-sections`
	 *
	 * @param string $name Name of the section
	 * @return DataResponse<Http::STATUS_CREATED, TalkConversationSection, array{}>
	 *
	 * 201: Section created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/sections', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function createSection(string $name): DataResponse {
		$section = $this->sectionService->createSection($this->userId, $name);
		return new DataResponse($this->formatSection($section), Http::STATUS_CREATED);
	}

	/**
	 * Update a conversation section
	 *
	 * Required capability: `conversation-sections`
	 *
	 * @param int $sectionId ID of the section
	 * @param string $name New name for the section
	 * @return DataResponse<Http::STATUS_OK, TalkConversationSection, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Section updated
	 * 404: Section not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/sections/{sectionId}', requirements: [
		'apiVersion' => '(v4)',
		'sectionId' => '\d+',
	])]
	public function updateSection(int $sectionId, string $name): DataResponse {
		try {
			$section = $this->sectionService->updateSection($sectionId, $this->userId, $name);
			return new DataResponse($this->formatSection($section));
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a conversation section
	 *
	 * Required capability: `conversation-sections`
	 *
	 * @param int $sectionId ID of the section
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Section deleted
	 * 404: Section not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/sections/{sectionId}', requirements: [
		'apiVersion' => '(v4)',
		'sectionId' => '\d+',
	])]
	public function deleteSection(int $sectionId): DataResponse {
		try {
			$this->sectionService->deleteSection($sectionId, $this->userId);
			return new DataResponse(null);
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Reorder conversation sections
	 *
	 * Required capability: `conversation-sections`
	 *
	 * @param list<int> $orderedIds Ordered list of section IDs
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationSection>, array{}>
	 *
	 * 200: Sections reordered
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/sections/reorder', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function reorderSections(array $orderedIds): DataResponse {
		$this->sectionService->reorderSections($this->userId, $orderedIds);
		$sections = $this->sectionService->getSections($this->userId);
		return new DataResponse(array_values(array_map([$this, 'formatSection'], $sections)));
	}

	/**
	 * @return TalkConversationSection
	 */
	protected function formatSection(ConversationSection $section): array {
		return [
			'id' => $section->getId(),
			'name' => $section->getName(),
			'sortOrder' => $section->getSortOrder(),
			'collapsed' => $section->isCollapsed(),
		];
	}
}
