<?php

namespace GuzzleHttp\Exception;

class ServerException extends \RuntimeException {
	public function getResponse() {
	}

	public function hasResponse(): bool {
	}
}
