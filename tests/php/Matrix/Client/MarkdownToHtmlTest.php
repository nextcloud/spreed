<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Html\HtmlToMarkdown;
use OCA\Talk\Matrix\Client\Html\MarkdownToHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class MarkdownToHtmlTest extends TestCase {
	public static function dataConvert(): array {
		return [
			'plain text' => ['plain text', null],
			'line break' => ["two\nlines", null],
			'html is plain text' => ['quotes "and" <angles> stay plain', null],
			'break and bold' => ["a\n**b**", 'a<br><strong>b</strong>'],
			'inline formatting' => ['Hello **world** and *it* and ~~no~~ `x`', 'Hello <strong>world</strong> and <em>it</em> and <del>no</del> <code>x</code>'],
			'heading and lists' => ["# Title\n\n- one\n- two\n\n1. a\n2. b", '<h1>Title</h1><ul><li>one</li><li>two</li></ul><ol><li>a</li><li>b</li></ol>'],
			'quote' => ["> quoted\n> more\n\nafter", '<blockquote><p>quoted<br>more</p></blockquote><p>after</p>'],
			'code block' => ["```php\necho 1 < 2;\n```", '<pre><code class="language-php">echo 1 &lt; 2;</code></pre>'],
			'links' => ['[Nextcloud](https://nextcloud.com) https://x.org', '<a href="https://nextcloud.com">Nextcloud</a> <a href="https://x.org">https://x.org</a>'],
			'escaping' => ['a <b> **c**', 'a &lt;b&gt; <strong>c</strong>'],
			'nested list' => ["- a\n  - b\n- c", '<ul><li>a<ul><li>b</li></ul></li><li>c</li></ul>'],
			'code is not formatted' => ['`**not bold**` **bold**', '<code>**not bold**</code> <strong>bold</strong>'],
		];
	}

	#[DataProvider('dataConvert')]
	public function testConvert(string $markdown, ?string $expected): void {
		self::assertSame($expected, (new MarkdownToHtml())->convert($markdown));
	}

	public function testInlineHook(): void {
		$converter = new MarkdownToHtml();
		$converter->setInlineHook(static fn (string $token): ?string => $token === '@"alice"' ? '<a href="https://matrix.to/#/@alice:hs">Alice</a>' : null);

		self::assertSame('hi <a href="https://matrix.to/#/@alice:hs">Alice</a> and @bob', $converter->convert('hi @"alice" and @bob'));
	}

	public function testRoundTrip(): void {
		$markdown = "Hello **world**\n\n- one\n- two\n\n> quote\n\n```\ncode\n```";
		$html = (new MarkdownToHtml())->convert($markdown);

		self::assertNotNull($html);
		self::assertSame($markdown, (new HtmlToMarkdown())->convert($html));
	}
}
