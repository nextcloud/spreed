<?php


namespace OCA\Talk\Exceptions;

/**
 * Exception that is thrown when an API error happened. The message itself is already translated and can be handed out to the user.
 *
 * This exception should be used for the code flow and not for logging
 *
 * This exception indicates a problem with the server, API, conenction or responses.
 */
class HostedSignalingServerAPIException extends \Exception {
}
