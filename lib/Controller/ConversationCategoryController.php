<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Model\ConversationCategory;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ConversationCategoryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type TalkConversationCategory from ResponseDefinitions
 */
class ConversationCategoryController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected ConversationCategoryService $categoryService,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all conversation categories for the current user
	 *
	 * Required capability: `conversation-categories`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationCategory>, array{}>
	 *
	 * 200: Categories returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/categories', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function getCategories(): DataResponse {
		$categories = $this->categoryService->getCategories($this->userId);
		return new DataResponse(array_values(array_map([$this, 'formatCategory'], $categories)));
	}

	/**
	 * Create a new conversation category
	 *
	 * Required capability: `conversation-categories`
	 *
	 * @param string $name Name of the category
	 * @return DataResponse<Http::STATUS_CREATED, TalkConversationCategory, array{}>
	 *
	 * 201: Category created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/categories', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function createCategory(string $name): DataResponse {
		$category = $this->categoryService->createCategory($this->userId, $name);
		return new DataResponse($this->formatCategory($category), Http::STATUS_CREATED);
	}

	/**
	 * Update a conversation category
	 *
	 * Required capability: `conversation-categories`
	 *
	 * @param int $categoryId ID of the category
	 * @param string $name New name for the category
	 * @return DataResponse<Http::STATUS_OK, TalkConversationCategory, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Category updated
	 * 404: Category not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/categories/{categoryId}', requirements: [
		'apiVersion' => '(v4)',
		'categoryId' => '\d+',
	])]
	public function updateCategory(int $categoryId, string $name): DataResponse {
		try {
			$category = $this->categoryService->updateCategory($categoryId, $this->userId, $name);
			return new DataResponse($this->formatCategory($category));
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a conversation category
	 *
	 * Required capability: `conversation-categories`
	 *
	 * @param int $categoryId ID of the category
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Category deleted
	 * 404: Category not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/categories/{categoryId}', requirements: [
		'apiVersion' => '(v4)',
		'categoryId' => '\d+',
	])]
	public function deleteCategory(int $categoryId): DataResponse {
		try {
			$this->categoryService->deleteCategory($categoryId, $this->userId);
			return new DataResponse(null);
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Reorder conversation categories
	 *
	 * Required capability: `conversation-categories`
	 *
	 * @param list<string> $orderedIds Ordered list of category IDs
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationCategory>, array{}>
	 *
	 * 200: Categories reordered
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/categories/reorder', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function reorderCategories(array $orderedIds): DataResponse {
		$this->categoryService->reorderCategories($this->userId, $orderedIds);
		$categories = $this->categoryService->getCategories($this->userId);
		return new DataResponse(array_values(array_map([$this, 'formatCategory'], $categories)));
	}

	/**
	 * Set the collapsed state of a conversation category
	 *
	 * Required capability: `conversation-categories`
	 *
	 * @param int $categoryId ID of the category
	 * @param bool $collapsed Whether the category should be collapsed
	 * @return DataResponse<Http::STATUS_OK, TalkConversationCategory, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Collapsed state updated
	 * 404: Category not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/categories/{categoryId}/collapsed', requirements: [
		'apiVersion' => '(v4)',
		'categoryId' => '\d+',
	])]
	public function updateCategoryCollapsed(int $categoryId, bool $collapsed): DataResponse {
		try {
			$category = $this->categoryService->setCollapsed($categoryId, $this->userId, $collapsed);
			return new DataResponse($this->formatCategory($category));
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @return TalkConversationCategory
	 */
	protected function formatCategory(ConversationCategory $category): array {
		return [
			'id' => (string)$category->getId(),
			'name' => $category->getName(),
			'sortOrder' => $category->getSortOrder(),
			'collapsed' => $category->isCollapsed(),
			'type' => $category->getType(),
		];
	}
}
