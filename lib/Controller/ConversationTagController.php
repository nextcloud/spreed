<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\InvalidTagNameException;
use OCA\Talk\Exceptions\TagLimitExceededException;
use OCA\Talk\Exceptions\TagNameAlreadyInUseException;
use OCA\Talk\Exceptions\TagNotCustomException;
use OCA\Talk\Model\ConversationTag;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\ConversationTagService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type TalkConversationTag from ResponseDefinitions
 */
class ConversationTagController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected ConversationTagService $tagService,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all conversation tags for the current user
	 *
	 * Required capability: `conversation-tags`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationTag>, array{}>
	 *
	 * 200: Tags returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/tags', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function getTags(): DataResponse {
		$tags = $this->tagService->getTags($this->userId);
		return new DataResponse(array_map([$this, 'formatTag'], $tags));
	}

	/**
	 * Create a new conversation tag
	 *
	 * Required capability: `conversation-tags`
	 *
	 * @param string $name Name of the tag
	 * @return DataResponse<Http::STATUS_CREATED, TalkConversationTag, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'name'|'limit'}, array{}>
	 *
	 * 201: Tag created
	 * 400: Invalid or duplicate name, or the user has reached the tag limit
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/tags', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function createTag(string $name): DataResponse {
		try {
			$tag = $this->tagService->createTag($this->userId, $name);
		} catch (InvalidTagNameException|TagNameAlreadyInUseException) {
			return new DataResponse(['error' => 'name'], Http::STATUS_BAD_REQUEST);
		} catch (TagLimitExceededException) {
			return new DataResponse(['error' => 'limit'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->formatTag($tag), Http::STATUS_CREATED);
	}

	/**
	 * Update a conversation tag
	 *
	 * Required capability: `conversation-tags`
	 *
	 * @param string $tagId ID of the tag
	 * @param string $name New name for the tag
	 * @return DataResponse<Http::STATUS_OK, TalkConversationTag, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'name'|'type'}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Tag updated
	 * 400: Invalid or duplicate name, or the tag is a built-in and cannot be renamed
	 * 404: Tag not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/tags/{tagId}', requirements: [
		'apiVersion' => '(v4)',
		'tagId' => '\d+',
	])]
	public function updateTag(string $tagId, string $name): DataResponse {
		try {
			$tag = $this->tagService->updateTag($tagId, $this->userId, $name);
			return new DataResponse($this->formatTag($tag));
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		} catch (InvalidTagNameException|TagNameAlreadyInUseException) {
			return new DataResponse(['error' => 'name'], Http::STATUS_BAD_REQUEST);
		} catch (TagNotCustomException) {
			return new DataResponse(['error' => 'type'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a conversation tag
	 *
	 * Required capability: `conversation-tags`
	 *
	 * @param string $tagId ID of the tag
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'type'}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Tag deleted
	 * 400: The tag is a built-in and cannot be deleted
	 * 404: Tag not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/tags/{tagId}', requirements: [
		'apiVersion' => '(v4)',
		'tagId' => '\d+',
	])]
	public function deleteTag(string $tagId): DataResponse {
		try {
			$this->tagService->deleteTag($tagId, $this->userId);
			return new DataResponse(null);
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		} catch (TagNotCustomException) {
			return new DataResponse(['error' => 'type'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Reorder conversation tags
	 *
	 * Required capability: `conversation-tags`
	 *
	 * @param list<string> $orderedIds Ordered list of tag IDs
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationTag>, array{}>
	 *
	 * 200: Tags reordered
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/tags/reorder', requirements: [
		'apiVersion' => '(v4)',
	])]
	public function reorderTags(array $orderedIds): DataResponse {
		$this->tagService->reorderTags($this->userId, $orderedIds);
		$tags = $this->tagService->getTags($this->userId);
		return new DataResponse(array_map([$this, 'formatTag'], $tags));
	}

	/**
	 * Set the collapsed state of a conversation tag
	 *
	 * Required capability: `conversation-tags`
	 *
	 * @param string $tagId ID of the tag
	 * @param bool $collapsed Whether the tag should be collapsed
	 * @return DataResponse<Http::STATUS_OK, TalkConversationTag, array{}>|DataResponse<Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Collapsed state updated
	 * 404: Tag not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/tags/{tagId}/collapsed', requirements: [
		'apiVersion' => '(v4)',
		'tagId' => '\d+',
	])]
	public function updateTagCollapsed(string $tagId, bool $collapsed): DataResponse {
		try {
			$tag = $this->tagService->setCollapsed($tagId, $this->userId, $collapsed);
			return new DataResponse($this->formatTag($tag));
		} catch (DoesNotExistException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @return TalkConversationTag
	 */
	protected function formatTag(ConversationTag $tag): array {
		return [
			'id' => (string)$tag->getId(),
			'name' => $tag->getName(),
			'sortOrder' => $tag->getSortOrder(),
			'collapsed' => $tag->isCollapsed(),
			'type' => $tag->getType(),
		];
	}
}
