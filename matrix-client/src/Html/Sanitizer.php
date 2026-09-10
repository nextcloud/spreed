<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Html;

/**
 * Sanitises `formatted_body` HTML to the subset the Matrix spec recommends
 * clients to render (spec §11.2.1.1 m.room.message msgtypes → HTML), returning
 * a DOM fragment that {@see HtmlToMarkdown} and others can consume.
 */
final class Sanitizer {
	/** @var array<string, list<string>> tag → allowed attributes */
	public const ALLOWED = [
		'font' => ['data-mx-bg-color', 'data-mx-color', 'color'],
		'del' => [], 's' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
		'blockquote' => [], 'p' => [], 'a' => ['name', 'target', 'href'], 'ul' => [], 'ol' => ['start'],
		'sup' => [], 'sub' => [], 'li' => [], 'b' => [], 'i' => [], 'u' => [], 'strong' => [], 'em' => [],
		'strike' => [], 'code' => ['class'], 'hr' => [], 'br' => [], 'div' => [], 'table' => [], 'thead' => [],
		'tbody' => [], 'tr' => [], 'th' => [], 'td' => [], 'caption' => [], 'pre' => [],
		'span' => ['data-mx-bg-color', 'data-mx-color', 'data-mx-spoiler', 'data-mx-maths'],
		'img' => ['width', 'height', 'alt', 'title', 'src'], 'details' => [], 'summary' => [],
	];

	public const ALLOWED_URL_SCHEMES = ['https', 'http', 'ftp', 'mailto', 'magnet', 'matrix'];

	/**
	 * Returns sanitised HTML. `mx-reply` fallback blocks are removed.
	 */
	public static function sanitize(string $html): string {
		$doc = self::parse($html);
		$body = $doc->getElementsByTagName('body')->item(0);
		if ($body === null) {
			return '';
		}
		self::clean($body, $doc);
		$out = '';
		foreach ($body->childNodes as $child) {
			$out .= $doc->saveHTML($child);
		}
		return $out;
	}

	public static function parse(string $html): \DOMDocument {
		$doc = new \DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		// Wrap to force UTF-8 and a body element
		$doc->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		return $doc;
	}

	private static function clean(\DOMNode $node, \DOMDocument $doc): void {
		// Iterate over a static copy – we mutate the tree
		$children = [];
		foreach ($node->childNodes as $child) {
			$children[] = $child;
		}
		foreach ($children as $child) {
			if ($child instanceof \DOMText) {
				continue;
			}
			if (!$child instanceof \DOMElement) {
				$node->removeChild($child);
				continue;
			}
			$tag = strtolower($child->tagName);
			if ($tag === 'mx-reply' || $tag === 'script' || $tag === 'style') {
				$node->removeChild($child);
				continue;
			}
			if (!isset(self::ALLOWED[$tag])) {
				// Unwrap: keep children, drop the element
				while ($child->firstChild !== null) {
					$node->insertBefore($child->firstChild, $child);
				}
				$node->removeChild($child);
				continue;
			}
			$allowedAttributes = self::ALLOWED[$tag];
			$attributes = [];
			foreach ($child->attributes ?? [] as $attr) {
				$attributes[] = $attr;
			}
			foreach ($attributes as $attr) {
				/** @var \DOMAttr $attr */
				$name = strtolower($attr->name);
				if (!in_array($name, $allowedAttributes, true)) {
					$child->removeAttribute($attr->name);
					continue;
				}
				if ($name === 'href' && !self::isSafeUrl($attr->value)) {
					$child->removeAttribute($attr->name);
				} elseif ($name === 'src' && !str_starts_with($attr->value, 'mxc://')) {
					// Only mxc:// images may be rendered (prevents tracking pixels)
					$child->removeAttribute($attr->name);
				} elseif ($name === 'class' && $tag === 'code' && !preg_match('/^language-[a-zA-Z0-9_+-]+$/', $attr->value)) {
					$child->removeAttribute($attr->name);
				} elseif ($name === 'target' && $attr->value !== '_blank') {
					$child->removeAttribute($attr->name);
				}
			}
			if ($tag === 'img' && !$child->hasAttribute('src')) {
				// Image without a permitted source: replace by its alt text
				$alt = $child->getAttribute('alt') ?: $child->getAttribute('title');
				$node->replaceChild($doc->createTextNode($alt), $child);
				continue;
			}
			self::clean($child, $doc);
		}
	}

	public static function isSafeUrl(string $url): bool {
		$url = trim($url);
		if ($url === '' || str_starts_with($url, '#')) {
			return false;
		}
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		return in_array($scheme, self::ALLOWED_URL_SCHEMES, true);
	}
}
