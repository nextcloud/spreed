<?php

namespace OC\Comments {

	use OCP\IDBConnection;

	class Comment implements \OCP\Comments\IComment {
		public function __construct(array $data = null) {
		}
		public function setMessage($message, $maxLength = self::MAX_MESSAGE_LENGTH) {
		}
	}
}
