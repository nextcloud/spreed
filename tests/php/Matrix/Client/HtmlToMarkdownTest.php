<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Html\HtmlToMarkdown;
use OCA\Talk\Matrix\Client\Html\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class HtmlToMarkdownTest extends TestCase {
	public function testSanitizerRemovesDangerousContent(): void {
		$clean = Sanitizer::sanitize('<mx-reply><blockquote>old</blockquote></mx-reply><p onclick="x()">Hi <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://ok.org" target="_blank">ok</a> <img src="https://tracker/pixel.gif" alt="pix"> <img src="mxc://hs/id" alt="cat"> <marquee>text</marquee></p>');

		self::assertStringNotContainsString('mx-reply', $clean);
		self::assertStringNotContainsString('script', $clean);
		self::assertStringNotContainsString('javascript:', $clean);
		self::assertStringNotContainsString('onclick', $clean);
		self::assertStringNotContainsString('tracker', $clean);
		self::assertStringNotContainsString('marquee', $clean);
		self::assertStringContainsString('<a href="https://ok.org" target="_blank">ok</a>', $clean);
		self::assertStringContainsString('<img src="mxc://hs/id" alt="cat">', $clean);
		self::assertStringContainsString('pix', $clean);
		self::assertStringContainsString('text', $clean);
	}

	public static function dataConvert(): array {
		return [
			'inline formatting' => ['Hello <strong>world</strong> and <em>it</em> and <del>no</del> <code>code</code>', 'Hello **world** and *it* and ~~no~~ `code`'],
			'line break' => ['Line 1<br>Line 2', "Line 1\nLine 2"],
			'paragraphs' => ['<p>Para 1</p><p>Para 2</p>', "Para 1\n\nPara 2"],
			'heading and lists' => ['<h1>Title</h1><ul><li>one</li><li>two</li></ul><ol><li>a</li><li>b</li></ol>', "# Title\n\n- one\n- two\n\n1. a\n2. b"],
			'quote' => ['<blockquote>quoted<br>more</blockquote>after', "> quoted\n> more\n\nafter"],
			'code block' => ['<pre><code class="language-php">echo 1;</code></pre>', "```php\necho 1;\n```"],
			'links' => ['<a href="https://nextcloud.com">Nextcloud</a> <a href="https://x.org">https://x.org</a>', '[Nextcloud](https://nextcloud.com) https://x.org'],
			'reply fallback' => ['<mx-reply><blockquote><a href="https://matrix.to/#/!r:hs/$e">In reply to</a> <a href="https://matrix.to/#/@a:hs">@a:hs</a><br>old</blockquote></mx-reply>reply text', 'reply text'],
			'escaping' => ['a * b _ c', 'a \* b \_ c'],
			'table' => ['<table><tr><th>a</th><th>b</th></tr><tr><td>1</td><td>2</td></tr></table>', "| a | b |\n| --- | --- |\n| 1 | 2 |"],
			'spoiler' => ['<span data-mx-spoiler>secret</span>', '||secret||'],
		];
	}

	#[DataProvider('dataConvert')]
	public function testConvert(string $html, string $expected): void {
		self::assertSame($expected, (new HtmlToMarkdown())->convert($html));
	}

	public function testPillResolver(): void {
		$converter = new HtmlToMarkdown();
		$converter->setPillResolver(static fn (string $userId, string $text): ?string => $userId === '@alice:hs' ? '@"alice"' : null);

		self::assertSame('hi @"alice" and Bob', $converter->convert('hi <a href="https://matrix.to/#/@alice:hs">Alice</a> and <a href="https://matrix.to/#/@bob:hs">Bob</a>'));
		self::assertSame('hi @"alice"', $converter->convert('hi <a href="matrix:u/alice:hs">Alice</a>'));
	}
}
