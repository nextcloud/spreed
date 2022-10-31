<?php

namespace OCA\Talk\OCP;

use OCA\Talk\Room;
use OCP\IURLGenerator;
use OCP\Talk\IConversation;

class Conversation implements IConversation {
	protected IURLGenerator $url;
	protected Room $room;

	public function __construct(IURLGenerator $url,
								Room $room) {
		$this->url = $url;
		$this->room = $room;
	}

	public function getId(): string {
		return $this->room->getToken();
	}

	public function getAbsoluteUrl(): string {
		return $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $this->room->getToken()]);
	}
}
