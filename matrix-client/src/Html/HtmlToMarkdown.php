<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Html;

/**
 * Converts (sanitised) Matrix HTML to Markdown. The host can supply a pill
 * resolver that turns matrix.to / matrix: user links into its own mention
 * syntax; unresolved pills fall back to the link text.
 */
final class HtmlToMarkdown {
	/** @var null|callable(string $userId, string $text): ?string */
	private $pillResolver = null;

	/** @param null|callable(string $userId, string $text): ?string $resolver */
	public function setPillResolver(?callable $resolver): void {
		$this->pillResolver = $resolver;
	}

	public function convert(string $html): string {
		$doc = Sanitizer::parse(Sanitizer::sanitize($html));
		$body = $doc->getElementsByTagName('body')->item(0);
		if ($body === null) {
			return '';
		}
		$markdown = $this->children($body, 0);
		// Collapse 3+ newlines, trim
		$markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;
		return trim($markdown);
	}

	private function children(\DOMNode $node, int $listDepth, string $listType = ''): string {
		$out = '';
		$index = 0;
		foreach ($node->childNodes as $child) {
			$out .= $this->node($child, $listDepth, $listType, ++$index);
		}
		return $out;
	}

	private function node(\DOMNode $node, int $listDepth, string $listType, int $index): string {
		if ($node instanceof \DOMText) {
			$text = $node->wholeText;
			if ($node->parentNode instanceof \DOMElement && in_array(strtolower($node->parentNode->tagName), ['pre', 'code'], true)) {
				return $text;
			}
			$text = preg_replace('/\s+/u', ' ', $text) ?? $text;
			return $this->escape($text);
		}
		if (!$node instanceof \DOMElement) {
			return '';
		}
		$tag = strtolower($node->tagName);
		switch ($tag) {
			case 'br':
				return "\n";
			case 'hr':
				return "\n\n---\n\n";
			case 'p':
			case 'div':
				return "\n\n" . trim($this->children($node, $listDepth)) . "\n\n";
			case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
				return "\n\n" . str_repeat('#', (int)$tag[1]) . ' ' . trim($this->children($node, $listDepth)) . "\n\n";
			case 'strong': case 'b':
				return $this->wrap('**', $this->children($node, $listDepth));
			case 'em': case 'i':
				return $this->wrap('*', $this->children($node, $listDepth));
			case 'del': case 's': case 'strike':
				return $this->wrap('~~', $this->children($node, $listDepth));
			case 'u':
				return $this->children($node, $listDepth);
			case 'code':
				if ($node->parentNode instanceof \DOMElement && strtolower($node->parentNode->tagName) === 'pre') {
					return $node->textContent;
				}
				$code = $node->textContent;
				$fence = str_contains($code, '`') ? '``' : '`';
				return $fence . $code . $fence;
			case 'pre':
				$code = rtrim($node->textContent, "\n");
				$lang = '';
				$codeChild = $node->getElementsByTagName('code')->item(0);
				if ($codeChild !== null && preg_match('/language-([a-zA-Z0-9_+-]+)/', $codeChild->getAttribute('class'), $m)) {
					$lang = $m[1];
				}
				return "\n\n```" . $lang . "\n" . $code . "\n```\n\n";
			case 'blockquote':
				$inner = trim($this->children($node, $listDepth));
				$lines = explode("\n", $inner);
				return "\n\n" . implode("\n", array_map(static fn (string $l) => '> ' . $l, $lines)) . "\n\n";
			case 'ul': case 'ol':
				$items = $this->children($node, $listDepth + 1, $tag);
				return ($listDepth === 0 ? "\n\n" : "\n") . rtrim($items, "\n") . ($listDepth === 0 ? "\n\n" : "\n");
			case 'li':
				$marker = $listType === 'ol' ? $index . '. ' : '- ';
				$content = trim($this->children($node, $listDepth, $listType));
				$content = str_replace("\n", "\n" . str_repeat('  ', $listDepth), $content);
				return str_repeat('  ', max(0, $listDepth - 1)) . $marker . $content . "\n";
			case 'a':
				$href = $node->getAttribute('href');
				$text = trim($this->children($node, $listDepth));
				$pill = $this->resolvePill($href, $text);
				if ($pill !== null) {
					return $pill;
				}
				if ($href === '') {
					return $text;
				}
				if ($text === '' || $text === $href) {
					return $href;
				}
				return '[' . $text . '](' . $href . ')';
			case 'img':
				$alt = $node->getAttribute('alt') ?: $node->getAttribute('title');
				return $alt !== '' ? $alt : $node->getAttribute('src');
			case 'span':
				if ($node->hasAttribute('data-mx-spoiler')) {
					return '||' . $this->children($node, $listDepth) . '||';
				}
				return $this->children($node, $listDepth);
			case 'table':
				return "\n\n" . $this->table($node) . "\n\n";
			case 'details':
				return "\n\n" . trim($this->children($node, $listDepth)) . "\n\n";
			case 'summary':
				return '**' . trim($this->children($node, $listDepth)) . "**\n";
			default:
				return $this->children($node, $listDepth, $listType);
		}
	}

	private function table(\DOMElement $table): string {
		$rows = [];
		foreach ($table->getElementsByTagName('tr') as $tr) {
			$cells = [];
			foreach ($tr->childNodes as $cell) {
				if ($cell instanceof \DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
					$cells[] = str_replace(['|', "\n"], ['\|', ' '], trim($this->children($cell, 0)));
				}
			}
			if ($cells !== []) {
				$rows[] = $cells;
			}
		}
		if ($rows === []) {
			return '';
		}
		$width = max(array_map('count', $rows));
		$lines = [];
		foreach ($rows as $i => $cells) {
			$cells = array_pad($cells, $width, '');
			$lines[] = '| ' . implode(' | ', $cells) . ' |';
			if ($i === 0) {
				$lines[] = '|' . str_repeat(' --- |', $width);
			}
		}
		return implode("\n", $lines);
	}

	private function wrap(string $marker, string $inner): string {
		if (trim($inner) === '') {
			return $inner;
		}
		// Keep surrounding whitespace outside the markers
		preg_match('/^(\s*)(.*?)(\s*)$/su', $inner, $m);
		return $m[1] . $marker . $m[2] . $marker . $m[3];
	}

	private function resolvePill(string $href, string $text): ?string {
		$userId = null;
		if (preg_match('~^https?://matrix\.to/#/(@[^?/]+)~i', $href, $m)) {
			$userId = rawurldecode($m[1]);
		} elseif (preg_match('~^matrix:u/([^?/]+)~i', $href, $m)) {
			$userId = '@' . rawurldecode($m[1]);
		}
		if ($userId === null) {
			return null;
		}
		if ($this->pillResolver === null) {
			return $text !== '' ? $text : $userId;
		}
		// User pills degrade to their text when the host has no representation for that user
		return ($this->pillResolver)($userId, $text) ?? ($text !== '' ? $text : $userId);
	}

	private function escape(string $text): string {
		// Escape characters that would otherwise start Markdown constructs
		return preg_replace('/([\\\\`*_~])/', '\\\\$1', $text) ?? $text;
	}
}
