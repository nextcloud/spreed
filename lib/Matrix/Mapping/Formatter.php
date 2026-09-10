<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Mapping;

use Nextcloud\Matrix\Html\HtmlToMarkdown;
use Nextcloud\Matrix\Html\MarkdownToHtml;
use Nextcloud\Matrix\Model\Event;
use Nextcloud\Matrix\Util\Identifier;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;

/**
 * Text conversion between Matrix (body + org.matrix.custom.html) and Talk
 * (Markdown with `@"userid"` mentions) in both directions.
 */
class Formatter {
	public const MAX_LENGTH = 32000;

	public function __construct(
		private readonly AccountMapper $accountMapper,
		private readonly MatrixMemberMapper $memberMapper,
		private readonly IUserManager $userManager,
	) {
	}

	/**
	 * Matrix → Talk. Pills of linked users become Talk mentions, other pills
	 * become their display text; `@room` becomes `@all`.
	 *
	 * @return array{message: string, truncated: bool}
	 */
	public function incoming(Event $event, string $matrixRoomId): array {
		$html = $event->getFormattedBody();
		$body = $event->getBody();
		$msgtype = $event->getMsgType();

		if ($html !== null && $html !== '') {
			$converter = new HtmlToMarkdown();
			$converter->setPillResolver(function (string $mxid, string $text): ?string {
				$account = $this->accountForMxid($mxid);
				return $account !== null ? '@"' . $account->getUserId() . '"' : null;
			});
			$message = $converter->convert($html);
			if ($message === '') {
				$message = $this->stripReplyFallback($body);
			}
		} else {
			$message = $this->stripReplyFallback($body);
		}

		if ($msgtype === 'm.emote') {
			$name = $this->memberName($matrixRoomId, $event->sender);
			$message = '* ' . $name . ' ' . $message;
		}

		if (($event->content['m.mentions']['room'] ?? false) === true && !str_contains($message, '@all')) {
			$message = str_replace('@room', '@all', $message);
		}

		$truncated = false;
		if (mb_strlen($message) > self::MAX_LENGTH) {
			$message = mb_substr($message, 0, self::MAX_LENGTH - 1) . '…';
			$truncated = true;
		}
		return ['message' => $message, 'truncated' => $truncated];
	}

	/**
	 * Talk → Matrix. Returns body, optional formatted_body and the mentioned
	 * Matrix user ids (for `m.mentions`).
	 *
	 * @return array{body: string, html: ?string, mentions: list<string>, mentionRoom: bool}
	 */
	public function outgoing(string $talkMessage, string $matrixRoomId): array {
		$mentions = [];
		$mentionRoom = false;
		$resolve = function (string $token) use (&$mentions, &$mentionRoom, $matrixRoomId): array|string|null {
			$id = trim(substr($token, 1), '"');
			if ($id === 'all') {
				$mentionRoom = true;
				return '@room';
			}
			if (str_starts_with($id, 'federated_user/') || str_starts_with($id, 'group/') || str_starts_with($id, 'team/')) {
				return null;
			}
			if (str_starts_with($id, 'matrix/') || str_starts_with($id, 'matrix_user/')) {
				$mxid = substr($id, strpos($id, '/') + 1);
				if (Identifier::isUserId($mxid)) {
					$mentions[] = $mxid;
					return ['mxid' => $mxid, 'name' => $this->memberName($matrixRoomId, $mxid)];
				}
				return null;
			}
			try {
				$account = $this->accountMapper->getByUserId($id);
			} catch (DoesNotExistException) {
				return null;
			}
			$mentions[] = $account->getMxid();
			return ['mxid' => $account->getMxid(), 'name' => $this->userManager->getDisplayName($id) ?? $id];
		};

		// Plain body: mentions become display names, keeps Markdown as-is (Matrix clients show body verbatim)
		$body = preg_replace_callback('/(?<=^|[\s(>])(@(?:"[^"]+"|[\w.@:\/\-]+))/u', static function (array $m) use ($resolve): string {
			$r = $resolve($m[1]);
			if ($r === null) {
				return $m[1];
			}
			return is_array($r) ? $r['name'] : $r;
		}, $talkMessage) ?? $talkMessage;
		$mentions = [];
		$mentionRoom = false;

		$converter = new MarkdownToHtml();
		$converter->setInlineHook(static function (string $token) use ($resolve): ?string {
			$r = $resolve($token);
			if ($r === null) {
				return null;
			}
			if (is_array($r)) {
				return '<a href="' . Identifier::matrixToUrl($r['mxid']) . '">' . htmlspecialchars($r['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</a>';
			}
			return $r;
		});
		$html = $converter->convert($talkMessage);

		return [
			'body' => $body,
			'html' => $html,
			'mentions' => array_values(array_unique($mentions)),
			'mentionRoom' => $mentionRoom,
		];
	}

	/**
	 * Legacy reply fallback in plain bodies: leading "> <@user> quoted" lines followed by a blank line.
	 */
	public function stripReplyFallback(string $body): string {
		if (!str_starts_with($body, '> ')) {
			return $body;
		}
		$lines = explode("\n", $body);
		$i = 0;
		while ($i < count($lines) && str_starts_with($lines[$i], '> ')) {
			$i++;
		}
		if ($i < count($lines) && trim($lines[$i]) === '') {
			return implode("\n", array_slice($lines, $i + 1));
		}
		return $body;
	}

	public function accountForMxid(string $mxid): ?Account {
		try {
			$account = $this->accountMapper->getByMxid($mxid);
		} catch (DoesNotExistException) {
			return null;
		}
		return $this->userManager->userExists($account->getUserId()) ? $account : null;
	}

	public function memberName(string $matrixRoomId, string $mxid): string {
		try {
			return $this->memberMapper->get($matrixRoomId, $mxid)->getName();
		} catch (DoesNotExistException) {
			return $mxid;
		}
	}
}
