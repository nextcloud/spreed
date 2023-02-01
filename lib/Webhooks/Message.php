<?php

namespace OCA\Talk\Webhooks;

use OCA\Talk\Room;
use OCP\Comments\IComment;
use OCP\IURLGenerator;

class Message implements \JsonSerializable {
	protected IURLGenerator $url;
	protected Room $room;
	protected IComment $comment;

	public function jsonSerialize(): array {
		return [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'type' => 'Create',
			'id' => $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $this->room->getToken()]) . '#message_' . $this->comment->getId(),
			'name' => '',
			'summary' => 'Sally did something to a note',
			'actor' => [
				'id' => 'http://example.org/people/sally',
				'type' => 'Person',
				'name' => 'Sally',
			],
			'object' => [
				'type' => 'Note',
				'name' => 'A Note',
				'content' => 'This is a simple note',
				'published' => $this->comment->getCreationDateTime()->format(\DateTimeInterface::ATOM), // Set timezone?
				'tag' => [
					[
						'type' => 'Mention',
						'href' => 'http://example.org/people/sally',
						'name' => '@sally'
					],
				]
			],
		];
	}

	public function jsonSerializeDelete(): array {
		return [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'type' => 'Delete',
			// ID of the delete action
			'id' => $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $this->room->getToken()]) . '#message_' . $this->comment->getId(),
			'name' => '',
			'summary' => 'Sally did something to a note',
			'actor' => [
				'type' => 'Person',
				'name' => 'Sally',
			],
			// ID of the deleted message
			'object' => $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $this->room->getToken()]) . '#message_' . $this->comment->getId(),
		];
	}
}
