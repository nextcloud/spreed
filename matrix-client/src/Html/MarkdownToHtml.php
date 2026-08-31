<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Html;

/**
 * Small, dependency-free Markdown → Matrix HTML converter covering the subset
 * chat clients use: paragraphs, headings, block quotes, fenced code, inline
 * code, bold/italic/strike, links, lists. Output only uses tags from
 * {@see Sanitizer::ALLOWED}. Returns null when the result carries no markup,
 * so callers can omit `formatted_body`.
 */
final class MarkdownToHtml {
	/** @var null|callable(string $text): ?string  Turns e.g. a mention token into a pill <a> */
	private $inlineHook = null;

	/** @param null|callable(string $text): ?string $hook Called with each text run; may return HTML */
	public function setInlineHook(?callable $hook): void {
		$this->inlineHook = $hook;
	}

	public function convert(string $markdown): ?string {
		$markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
		$lines = explode("\n", $markdown);
		$html = $this->blocks($lines);
		// A single paragraph → return the inner HTML only; and when that carries
		// no markup beyond line breaks, there is nothing worth a formatted_body.
		if (preg_match('~^<p>(.*)</p>$~s', $html, $m) && !str_contains($m[1], '<p>')) {
			$inner = str_replace("\n", '<br>', $m[1]);
			$withoutBreaks = str_replace('<br>', "\n", $inner);
			if (strip_tags($withoutBreaks) === $withoutBreaks && html_entity_decode($withoutBreaks, ENT_QUOTES | ENT_HTML5, 'UTF-8') === trim($markdown)) {
				return null;
			}
			return $inner;
		}
		return $html;
	}

	/** @param list<string> $lines */
	private function blocks(array $lines): string {
		$out = '';
		$i = 0;
		$n = count($lines);
		while ($i < $n) {
			$line = $lines[$i];
			if (trim($line) === '') {
				$i++;
				continue;
			}
			// Fenced code
			if (preg_match('/^```\s*([a-zA-Z0-9_+-]*)\s*$/', $line, $m)) {
				$lang = $m[1];
				$code = [];
				$i++;
				while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) {
					$code[] = $lines[$i];
					$i++;
				}
				$i++; // closing fence
				$class = $lang !== '' ? ' class="language-' . $lang . '"' : '';
				$out .= '<pre><code' . $class . '>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code></pre>';
				continue;
			}
			// Heading
			if (preg_match('/^(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
				$level = strlen($m[1]);
				$out .= '<h' . $level . '>' . $this->inlineText($m[2]) . '</h' . $level . '>';
				$i++;
				continue;
			}
			// Horizontal rule
			if (preg_match('/^(-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
				$out .= '<hr>';
				$i++;
				continue;
			}
			// Blockquote
			if (preg_match('/^\s{0,3}>/', $line)) {
				$quote = [];
				while ($i < $n && preg_match('/^\s{0,3}>\s?(.*)$/', $lines[$i], $m)) {
					$quote[] = $m[1];
					$i++;
				}
				$out .= '<blockquote>' . $this->blocks($quote) . '</blockquote>';
				continue;
			}
			// Lists
			if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+/', $line)) {
				$out .= $this->list($lines, $i);
				continue;
			}
			// Paragraph
			$para = [];
			while ($i < $n && trim($lines[$i]) !== '' && !preg_match('/^(```|#{1,6}\s|\s{0,3}>|(\s*)([-*+]|\d+[.)])\s+|(-{3,}|\*{3,}|_{3,})\s*$)/', $lines[$i])) {
				$para[] = $lines[$i];
				$i++;
			}
			if ($para === []) {
				$para[] = $lines[$i++];
			}
			$out .= '<p>' . implode('<br>', array_map($this->inlineText(...), $para)) . '</p>';
		}
		return $out;
	}

	/** @param list<string> $lines */
	private function list(array $lines, int &$i): string {
		$n = count($lines);
		preg_match('/^(\s*)([-*+]|\d+[.)])\s+/', $lines[$i], $m);
		$indent = strlen($m[1]);
		$ordered = ctype_digit($m[2][0]);
		$tag = $ordered ? 'ol' : 'ul';
		$start = $ordered ? (int)$m[2] : 1;
		$items = [];
		while ($i < $n) {
			$line = $lines[$i];
			if (trim($line) === '') {
				// Blank line ends the list unless the next line continues it
				if ($i + 1 < $n && preg_match('/^(\s*)([-*+]|\d+[.)])\s+/', $lines[$i + 1], $mm) && strlen($mm[1]) >= $indent && ctype_digit($mm[2][0]) === $ordered) {
					$i++;
					continue;
				}
				break;
			}
			if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.*)$/', $line, $mm)) {
				$thisIndent = strlen($mm[1]);
				if ($thisIndent < $indent || ($thisIndent === $indent && ctype_digit($mm[2][0]) !== $ordered)) {
					break;
				}
				if ($thisIndent > $indent) {
					// Nested list → attach to previous item
					$nested = $this->list($lines, $i);
					if ($items === []) {
						$items[] = $nested;
					} else {
						$items[count($items) - 1] .= $nested;
					}
					continue;
				}
				$items[] = $this->inlineText($mm[3]);
				$i++;
				continue;
			}
			// Continuation line of the previous item
			if ($items !== [] && preg_match('/^\s+(.*)$/', $line, $mm)) {
				$items[count($items) - 1] .= '<br>' . $this->inlineText($mm[1]);
				$i++;
				continue;
			}
			break;
		}
		$attr = $ordered && $start !== 1 ? ' start="' . $start . '"' : '';
		return '<' . $tag . $attr . '>' . implode('', array_map(static fn (string $it) => '<li>' . $it . '</li>', $items)) . '</' . $tag . '>';
	}

	private function inlineText(string $text): string {
		return $this->inline(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false));
	}

	/** $text is already HTML-escaped */
	private function inline(string $text): string {
		// Protect inline code spans first
		$codes = [];
		$text = preg_replace_callback('/(`+)(.+?)\1/s', static function (array $m) use (&$codes): string {
			$codes[] = '<code>' . $m[2] . '</code>';
			return "\x00" . (count($codes) - 1) . "\x00";
		}, $text) ?? $text;

		// Links [text](url) and bare URLs
		$text = preg_replace_callback('/\[([^\]]+)\]\(((?:https?|mailto|matrix):[^)\s]+)\)/i', static fn (array $m) => '<a href="' . $m[2] . '">' . $m[1] . '</a>', $text) ?? $text;
		$text = preg_replace_callback('~(?<![">\w/=])(https?://[^\s<]+[^\s<.,;:!?)\]])~i', static fn (array $m) => '<a href="' . $m[1] . '">' . $m[1] . '</a>', $text) ?? $text;

		// Emphasis
		$text = preg_replace('/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/s', '<strong>$2</strong>', $text) ?? $text;
		$text = preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/s', '<em>$1</em>', $text) ?? $text;
		$text = preg_replace('/(?<!\w)_(?=\S)(.+?)(?<=\S)_(?!\w)/s', '<em>$1</em>', $text) ?? $text;
		$text = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $text) ?? $text;
		$text = preg_replace('/\|\|(.+?)\|\|/s', '<span data-mx-spoiler>$1</span>', $text) ?? $text;

		if ($this->inlineHook !== null) {
			// $text is HTML-escaped at this point, so quotes appear as &quot;
			$text = preg_replace_callback('/(?<=^|[\s(>])(@(?:&quot;[^&]+&quot;|[\w.@:\-]+))/u', function (array $m): string {
				return ($this->inlineHook)(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $m[1];
			}, $text) ?? $text;
		}

		// Restore code spans
		return preg_replace_callback("/\x00(\d+)\x00/", static fn (array $m) => $codes[(int)$m[1]], $text) ?? $text;
	}
}
