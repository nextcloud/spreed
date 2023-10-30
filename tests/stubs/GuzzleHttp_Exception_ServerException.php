<?php

namespace GuzzleHttp\Exception;

use Psr\Http\Message\ResponseInterface;

class ServerException extends \RuntimeException {

	public function getResponse(): ResponseInterface {
	}
	public function hasResponse(): bool {
	}
}
